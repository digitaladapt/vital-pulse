# VitalPulse — Design Considerations

VitalPulse is a lightweight personal health vitals tracker built with Symfony 8, Doctrine ORM, SQLite, and a vanilla HTML/Chart.js frontend. The codebase is clean, well-organized, and clearly the work of someone who cares about structure — the test suite is solid for the project's scope, the Docker setup is reasonable, and the documentation is thorough. The findings below focus on areas where modern best practices could be adopted without over-engineering what is, at its core, a single-user personal health app.

---

## Table of Contents

1. [Architecture & Code Structure](#1-architecture--code-structure)
2. [Security](#2-security)
3. [Validation & Error Handling](#3-validation--error-handling)
4. [Testing](#4-testing)
5. [Frontend](#5-frontend)
6. [Docker & Deployment](#6-docker--deployment)
7. [Dependencies & Configuration](#7-dependencies--configuration)
8. [API Design](#8-api-design)
9. [Project Hygiene](#9-project-hygiene)

---

## 1. Architecture & Code Structure

### 1.1 — Manual serialization is duplicated between controller methods 🔴 High

**Current state:** Both `createLog()` and `listLogs()` in `HealthApiController` manually construct the JSON response array by mapping each `HealthLog` property to a snake_case key. The exact same field mapping appears twice:

```php
return [
    'id' => $log->getId(),
    'timestamp' => $log->getTimestamp()->format('c'),
    'systolic' => $log->getSystolic(),
    // ... 5 more fields
];
```

**Suggestion:** Extract a private `serializeLog(HealthLog $log): array` method, or better yet, use the Symfony Serializer (already in `composer.json`) with a serialization group and `name_converter` to snake_case.

**Why:** Every time a field is added to `HealthLog` (e.g. a notes field, an oxygen saturation field), both serialization blocks must be updated in lockstep. A single method or Serializer-based approach eliminates that duplication and the risk of the two representations drifting apart. The Serializer component is already a dependency but sits unused.

### 1.2 — Validator instantiated ad-hoc instead of via DI 🔴 High

**Current state:** In `createLog()`, the validator is created with `Validation::createValidator()`, which bypasses the Symfony DI container. This means the validator is unaware of container-level configuration (e.g. the `not_compromised_password` setting in `validator.yaml`, or auto-mapping rules).

**Suggestion:** Inject `ValidatorInterface` into the controller constructor (autowiring will handle it automatically), then call `$this->validator->validate($log)`.

**Why:** The `Validation::createValidator()` static factory creates a standalone validator with no container context. In a Symfony application, the DI-injected `ValidatorInterface` respects configuration from `config/packages/validator.yaml`, supports auto-mapping, and is consistent with how the rest of Symfony expects validation to work. It's also trivially testable since it's injected.

### 1.3 — EntityManager injected directly into controller 🟡 Medium

**Current state:** `HealthApiController` receives `EntityManagerInterface` and calls `$this->entityManager->persist()` and `$this->entityManager->flush()` directly, as well as `$this->entityManager->getRepository(HealthLog::class)`.

**Suggestion:** Inject `HealthLogRepository` for data retrieval and keep the EntityManager only for persistence (or move the persist/flush into a small service class). This is a minor refactor.

**Why:** The repository already has `findByDateRange()` — the controller should call that method on the repository directly rather than going through the EntityManager to fetch the repository. This makes the controller's dependencies clearer (it depends on the repository, not the entire ORM) and makes the code more testable since the repository can be mocked. For a project this size, the current approach works, but the pattern should be tightened before more controllers are added.

### 1.4 — Missing `declare(strict_types=1)` 🟡 Medium

**Current state:** No PHP file in `src/` or `tests/` uses `declare(strict_types=1)`. The CONTRIBUTING.md says to use it, but it's not actually applied.

**Suggestion:** Add `declare(strict_types=1);` as the first line of every PHP file. This is a one-time mechanical change.

**Why:** PHP's weak typing can silently coerce values in unexpected ways (e.g. passing `"120"` to an `int` parameter succeeds without error). Strict types catch these bugs at the type level. Since the project targets PHP 8.4 and the contributing guide already mandates it, the codebase should follow its own rule. This is especially relevant for the API controller where user input is being cast with `(int)` and `(float)`.

### 1.5 — `SystemController::getVersion()` shells out to git 🟢 Low

**Current state:** The `/api/about` endpoint calls `@shell_exec('git describe --tags --abbrev=0 2>/dev/null')` to read the version. This works when running from a git checkout but returns the hardcoded fallback (`1.3.0`) inside a Docker container (since `.git` is excluded by `.dockerignore`).

**Suggestion:** Write the version to a file (e.g. `VERSION`) during the Docker build step (e.g. `RUN git describe --tags --abbrev=0 > VERSION` in the builder stage before `.git` is stripped), or embed it as an environment variable at build time.

**Why:** The `/api/about` endpoint currently always returns `1.3.0` in production Docker deployments. Embedding the version at build time makes the endpoint actually useful for monitoring which version is running, and it avoids a `shell_exec` call on every request (though the result could be cached, the broader issue is correctness).

---

## 2. Security

### 2.1 — Internal exception messages leaked in 500 responses 🔴 High

**Current state:** The `createLog()` catch block returns:

```php
return new JsonResponse(['error' => 'Failed to save log entry: ' . $e->getMessage()], 500);
```

This exposes Doctrine's internal error messages (which can include SQL queries, table names, and schema details) directly to the API consumer.

**Suggestion:** Log the full exception internally and return a generic message: `['error' => 'Failed to save log entry']`. Consider wiring Symfony's logger.

**Why:** Doctrine exceptions can contain the full SQL statement that failed, column names, constraint names, and sometimes database file paths. In a production API, this is information leakage that could help an attacker understand the database schema. The fix is a one-line change with no downside.

### 2.2 — API key stored in browser localStorage 🔴 High

**Current state:** The frontend stores the API key in `localStorage` via `localStorage.setItem('vitalpulse_api_key', apiKey)`. Any XSS vulnerability or browser extension with DOM access can read it.

**Suggestion:** This is a known tradeoff for a single-user app with no session system. If the app stays single-user with one API key, consider an alternative: serve the dashboard behind the same API key gate and inject the key server-side into the HTML, or use a cookie-based approach (even a simple `X-API-Key` cookie sent automatically with requests). A short-term mitigation is to add a Content Security Policy header to reduce XSS risk.

**Why:** `localStorage` is accessible to any JavaScript running on the page. Since the dashboard loads third-party JavaScript libraries (Chart.js, Luxon) directly from the server (not a CDN, which is good), the risk is reduced, but a CSP header would provide defense-in-depth. For a personal health app, the data sensitivity is moderate, but the pattern should be acknowledged as a conscious tradeoff rather than an oversight.

### 2.3 — API key accepted via query parameter 🟡 Medium

**Current state:** The `ApiKeySubscriber` accepts the API key from both the `X-API-Key` header and the `api_key` query parameter. Query parameters are logged by web servers, reverse proxies, and browser history.

**Suggestion:** Deprecate the query parameter path for the frontend (it's not used — the frontend always sends the header) and keep it only for documented curl/CLI convenience, or remove it entirely.

**Why:** The frontend uses the header exclusively. The query parameter path exists for convenience but creates a risk of the API key appearing in server access logs, browser history, or referrer headers. For a personal-use API this is low risk, but it's a known anti-pattern. If the parameter is kept, it should be documented as "for quick testing only."

### 2.4 — No security headers 🟡 Medium

**Current state:** No security headers are set anywhere — no CSP, no `X-Content-Type-Options: nosniff`, no `X-Frame-Options: DENY`, no `Referrer-Policy`. The `PublicAssetController` sets a Content-Type and cache headers but no security headers.

**Suggestion:** Add a small event subscriber (or middleware) that sets baseline security headers on all responses: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, and a reasonable CSP. This can also be done at the Caddy level in the Docker Caddyfile.

**Why:** Security headers are cheap defense-in-depth. `nosniff` prevents MIME-type sniffing attacks. `X-Frame-Options: DENY` prevents clickjacking. A CSP would limit the blast radius of any XSS. For a health data app, these are proportional and standard. The Caddyfile already does `encode zstd gzip` — adding a few `header` directives is trivial.

### 2.5 — API key compared in plaintext (timing-safe but not hashed) 🟢 Low

**Current state:** The API key is stored in plaintext in an environment variable and compared with `hash_equals()` (which is timing-safe). The key is never hashed (e.g. with `password_hash`).

**Suggestion:** For a single-user personal app, this is acceptable. If the app ever expands to multiple keys or users, consider storing a hash of the key instead.

**Why:** `hash_equals` prevents timing attacks, which is the primary concern. Hashing the key at rest would protect against the env var being leaked (e.g. in a `phpinfo()` dump or a misconfigured debug page), but for a personal app with one key in a Docker env var, the practical risk is low. Noted as a future consideration, not an immediate action item.

---

## 3. Validation & Error Handling

### 3.1 — Validation groups never activated — range constraints are dead code 🔴 High

**Current state:** The `HealthLog` entity defines `@Assert\Range` constraints on systolic, diastolic, heart rate, and weight — but they're assigned to the `health_check` group:

```php
#[Assert\Range(min: 60, max: 250, notInRangeMessage: '...', groups: ['health_check'])]
private ?int $systolic = null;
```

The controller validates with `Validation::createValidator()->validate($log)` — no group specified, so only the default group runs. The `health_check` group is never activated, meaning **range validation never executes**. An API consumer can submit `systolic: 9999` or `weight: -50` and it will be persisted.

The `@Assert\PositiveOrZero` constraints (on systolic, diastolic, heart rate) DO run because they're in the default group. But `weight` uses `@Assert\Positive` (also default group), so it catches zero and negative. However, the upper-bound ranges are entirely unenforced.

**Suggestion:** Either:
- Remove the `groups: ['health_check']` from the Range constraints so they run in the default group, OR
- Validate with the group explicitly: `$this->validator->validate($log, null, ['Default', 'health_check'])`

The ROADMAP also raises the point that the ranges might be too strict for edge cases. If so, widen the ranges but still enforce them — don't leave them as dead code.

**Why:** This is a data integrity issue. The validation constraints exist to prevent garbage data, but they're not running. A caller can persist `systolic: 0` (caught by PositiveOrZero but 0 is arguably invalid), `systolic: 99999` (not caught at all), or `weight: 0.001` (caught by Positive but the range check is skipped). The intent was clearly to validate these ranges; the implementation just doesn't activate them.

### 3.2 — Input type coercion is unsafe 🟡 Medium

**Current state:** The controller casts user input with `(int)` and `(float)`:

```php
$log->setSystolic((int)$data['systolic']);
$log->setWeight((float)$data['weight']);
```

PHP's `(int)` cast converts `"abc"` to `0` and `"12abc"` to `12` silently. There's no validation that the input is actually a number.

**Suggestion:** Use `filter_var($data['systolic'], FILTER_VALIDATE_INT)` or Symfony's `Assert\Type` constraint on the entity (or a DTO). Reject non-numeric input with a 400 response.

**Why:** A caller sending `{"systolic": "high"}` will get a 201 response with `systolic: 0` persisted to the database. That's silent data corruption. For a health metrics app, storing `0` for systolic is worse than rejecting the input outright. Adding `@Assert\Type('integer')` to the entity properties would catch this at the validation step.

### 3.3 — No validation on the `emoji` field 🟡 Medium

**Current state:** The `setEmoji()` method accepts any string up to 10 characters (the DB column limit). There's no validation that the value is actually an emoji. A caller could send `emoji: "alert(1)"` or any arbitrary 10-char string.

**Suggestion:** Either validate against a known set of allowed emojis (the frontend uses a fixed set of 10), or at minimum add a regex/length constraint. If custom emojis should be allowed, document that.

**Why:** The emoji field is rendered in the frontend dashboard (in chart tooltips, filter buttons). If it contains arbitrary HTML, it could be an XSS vector (though Chart.js renders text content, not HTML, so the practical risk is low). More importantly, arbitrary strings in the emoji column could break the filter UI or chart rendering. A simple whitelist or regex check would prevent this.

### 3.4 — No validation that timestamp is not in the future 🟢 Low

**Current state:** The API accepts any timestamp string, including future dates. A caller can log a reading "tomorrow."

**Suggestion:** Add a check that the provided timestamp is not in the future (with a small tolerance, e.g. 5 minutes for clock skew).

**Why:** For a health tracking app, future-dated entries don't make sense and could skew trend calculations. This is a minor data quality concern, not a security issue.

### 3.5 — Inconsistent error response format 🟢 Low

**Current state:** All error responses use `{'error': 'message string'}`, which is consistent. However, when validation violations occur, multiple errors are joined into a single comma-separated string:

```php
$errors[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
return new JsonResponse(['error' => implode(', ', $errors)], 400);
```

**Suggestion:** Return structured error details: `{'error': 'Validation failed', 'details': {'systolic': ['...'], 'weight': ['...']}}`.

**Why:** A structured error response lets API consumers (including the frontend) display field-specific errors next to the relevant input. The current format requires parsing a comma-separated string. For a personal app this is minor, but it's a low-effort improvement.

---

## 4. Testing

### 4.1 — No tests for `SystemController` 🟡 Medium

**Current state:** `SystemController` has two endpoints (`/api/about` and `/api/health`) with no test coverage. The `/api/health` endpoint is used as the Docker `HEALTHCHECK` target.

**Suggestion:** Add a `SystemControllerTest` that verifies both endpoints return 200 with the expected JSON structure. The `/api/health` endpoint especially should be tested since it's the health check.

**Why:** The health check endpoint is relied upon by Docker's `HEALTHCHECK` directive. If it breaks, Docker will consider the container unhealthy and may restart it. A simple test ensures this endpoint stays functional. The `/api/about` endpoint's version reading logic (including the fallback) also deserves a test.

### 4.2 — No test for `getStatsForDateRange()` via the API 🟡 Medium

**Current state:** `HealthLogRepository::getStatsForDateRange()` is tested directly in `HealthLogRepositoryTest`, but there's no API endpoint that calls it and no integration test for it. The method is currently dead code from the API perspective.

**Suggestion:** This is really an API completeness gap (no `/api/v1/logs/stats` endpoint) rather than a test gap. When the endpoint is added, it should be fully tested.

**Why:** The stats method exists and is tested in isolation, but it's never wired to the application. This means the tests pass but the feature is invisible to users. Noting it here because the test suite gives a false sense of completeness for this feature.

### 4.3 — No edge-case tests for invalid input types 🟡 Medium

**Current state:** The test suite covers the happy path and some error cases (missing API key, empty body, systolic-only), but doesn't test:
- Non-numeric values for numeric fields (e.g. `{"systolic": "abc"}`)
- Values outside the `health_check` range (e.g. `systolic: 9999`)
- Very long emoji strings
- Null values explicitly passed (e.g. `{"systolic": null}`)
- Non-object JSON (e.g. `"just a string"` or `[1,2,3]`)

**Suggestion:** Add edge-case tests for these scenarios. Several would likely reveal bugs (e.g. `"abc"` cast to `0` would be persisted successfully, which is wrong).

**Why:** Edge-case testing is where the validation gaps (3.1, 3.2, 3.3) would be discovered. The current tests verify that valid inputs work and that obviously-missing inputs are rejected, but they don't probe the boundary between "valid" and "invalid" — which is where the real bugs live.

### 4.4 — Test schema setup is duplicated between controller and repository tests 🟢 Low

**Current state:** Both `HealthApiControllerTest` and `HealthLogRepositoryTest` have their own `setUp()` methods that use `SchemaTool` to create the schema from metadata. The logic is nearly identical.

**Suggestion:** Extract a trait (e.g. `SchemaSetupTrait`) or a base test class that both can use.

**Why:** The duplication means if the schema setup needs to change (e.g. to add a fixture), it must be changed in two places. A shared trait is a small DRY improvement. For two test classes, this is minor, but the pattern will matter as the test suite grows.

### 4.5 — `HealthApiControllerTest` persists a HealthLog with no measurements 🟢 Low

**Current state:** In `testGetLogsReturnsCreatedEntries()`, the test persists `new HealthLog()` (with no measurements set) directly via the EntityManager to set up test data. This bypasses the API's validation rule that requires at least one measurement. The test then asserts that 3 entries are returned, including this one.

**Suggestion:** Either set a measurement on the test entity, or add a comment explaining that this is intentionally testing that the GET endpoint returns all rows regardless of their state (since the API can't prevent direct DB inserts).

**Why:** The test is technically correct (the GET endpoint should return all rows), but it silently documents that the "at least one measurement" rule is enforced at the API layer, not the database layer. This is fine, but it should be explicit so future readers don't think it's a bug.

---

## 5. Frontend

### 5.1 — API key handling in frontend is fragile 🟡 Medium

**Current state:** `app.js` prompts for the API key on page load if it's not in localStorage, and on 401 responses it removes the stored key and prompts again. The `fetchLogs()` function recurses on 401:

```javascript
if (resp.status === 401) {
    localStorage.removeItem(API_KEY_STORAGE);
    apiKey = prompt('API key invalid. Enter correct API key:');
    if (!apiKey) return null;
    localStorage.setItem(API_KEY_STORAGE, apiKey);
    return fetchLogs(); // retry
}
```

**Suggestion:** Add a retry limit (e.g. max 1 retry) to prevent infinite recursion if the key is consistently wrong. Consider a proper login/settings UI instead of `prompt()`.

**Why:** If the stored key is invalid and the user keeps entering an invalid key (or hits Enter on an empty prompt), `fetchLogs()` will recurse indefinitely. The `if (!apiKey) return null` check prevents the empty-prompt case, but entering a wrong key repeatedly will keep recursing. A retry counter is a simple safeguard. The `prompt()` UX is also jarring — a small settings panel would be more polished.

### 5.2 — Vendored JavaScript libraries are large and unversioned in the repo 🟡 Medium

**Current state:** `chartjs-v4.js` (208KB), `luxon-v3.js` (82KB), and `chartjs-adapter-luxon-v1.js` (2KB) are committed directly to `public/`. The filenames include version hints (`v4`, `v3`, `v1`) but there's no lockfile or exact version pinning.

**Suggestion:** This is a conscious tradeoff to avoid a build step, which is reasonable for a personal project. As a minimum improvement, add the exact versions (e.g. Chart.js 4.4.7, Luxon 3.5.0) to a comment in each file or to the README. If a build step is ever added, these should move to npm-managed dependencies.

**Why:** Without exact version tracking, it's impossible to know if a bug is caused by a specific library version. Security patches also can't be tracked. The current approach (vendored, no build step) is fine for the project's scope, but the version information should be documented somewhere.

### 5.3 — `commonOptions` is shared by reference across all charts 🟢 Low

**Current state:** A single `commonOptions` object is passed to all three Chart.js instances. Chart.js may mutate options internally. If one chart modifies the shared object, it could affect the others.

**Suggestion:** Use a function that returns a fresh copy: `function getCommonOptions() { return { ... }; }` or `structuredClone(commonOptions)`.

**Why:** This is a subtle JavaScript bug that might not manifest with Chart.js v4's current implementation but could appear in future updates. Deep-cloning options per chart is a safe practice. For three charts, the performance cost is negligible.

### 5.4 — No error boundary for chart rendering 🟢 Low

**Current state:** If the API returns malformed data or `renderCharts()` throws, the error is caught in `fetchLogs()` but chart rendering (`renderBpChart`, etc.) has no try/catch. A single malformed data point could break the entire dashboard.

**Suggestion:** Wrap chart rendering in try/catch with a user-facing error message.

**Why:** The frontend fetches data and passes it directly to Chart.js. If the data shape is unexpected (e.g. a `null` timestamp), Chart.js may throw, leaving the dashboard in a broken state with no feedback. A simple error boundary would show "Could not render charts" instead of a blank page.

### 5.5 — `favicon.svg` is 293KB 🟢 Low

**Current state:** The SVG favicon is 293KB, which is unusually large for a favicon. It's likely an SVG with embedded raster data or excessive paths.

**Suggestion:** Optimize the SVG (strip metadata, simplify paths, or regenerate at a smaller size). Tools like SVGO can reduce it significantly.

**Why:** 293KB for a favicon is excessive and will slow down the initial page load, especially on mobile. The PNG favicons (8-31KB) are more reasonably sized. The SVG should be optimized to a few KB at most.

---

## 6. Docker & Deployment

### 6.1 — Healthcheck endpoint doesn't match the app's health endpoint 🔴 High

**Current state:** The Dockerfile `HEALTHCHECK` curls `http://localhost:80/` (the root URL), while the app has a dedicated `/api/health` endpoint. The root URL serves `index.html` via `PublicAssetController`, so it returns 200 even if the database connection is broken.

**Suggestion:** Change the healthcheck to `curl -sf http://localhost:80/api/health` (which is an unauthenticated endpoint that at least proves the Symfony kernel booted). For a deeper check, add a database connectivity test to the health endpoint.

**Why:** The current healthcheck only verifies that the web server is responding, not that the application is functional. If the SQLite database is corrupted or unreadable, the healthcheck will still pass. The `/api/health` endpoint at least proves the Symfony router and controller pipeline are working. This is a one-line Dockerfile change.

### 6.2 — Entrypoint runs `schema:create` with `schema:update` fallback 🟡 Medium

**Current state:** The entrypoint script does:

```sh
php /app/bin/console doctrine:schema:create --no-interaction --env=prod || \
    php /app/bin/console doctrine:schema:update --force --no-interaction --env=prod
```

This creates the schema from scratch if the DB is empty, or force-updates it if it exists. `schema:update --force` is explicitly discouraged by Doctrine for production use.

**Suggestion:** Use `doctrine:schema:create` for fresh databases and implement proper Doctrine migrations for updates. Even for a personal project, a migration set is the right pattern.

**Why:** `schema:update --force` can silently alter the database in ways that lose data (e.g. dropping a column). For a single-entity app this is unlikely to cause issues today, but it establishes a pattern that will be dangerous as the schema evolves. The ROADMAP already identifies "no Doctrine migrations" as a gap — this entrypoint is where that gap has the most practical impact.

### 6.3 — `deploy.sh` and `run.sh` are deprecated but still present 🟡 Medium

**Current state:** `deploy.sh` is explicitly marked deprecated in its own comments. `run.sh` starts a PHP dev server in a `screen` session, which is not how the app is deployed in Docker. Both files are excluded from the Docker image via `.dockerignore`.

**Suggestion:** Remove both files (or move to a `legacy/` directory or a git tag) now that Docker is the primary deployment method.

**Why:** Dead files in the project root create confusion for new contributors. The README already says "do not use them for new deployments." Keeping them serves no purpose if the Docker setup works. If they need to be preserved for reference, a `docs/legacy/` directory is cleaner than the project root.

### 6.4 — `compose.yaml` references an external network with no documentation 🟢 Low

**Current state:** `compose.yaml` declares `networks: public: external: true`. If the `public` network doesn't exist, `docker compose up` will fail with an opaque error.

**Suggestion:** Add a comment explaining that the external network must be created first (`docker network create public`), or make the network internal with a defined driver.

**Why:** A new user cloning the repo and running `docker compose up` will get an error about a missing network. A one-line comment or a documented prerequisite would save them debugging time.

### 6.5 — Docker image runs as root 🟢 Low

**Current state:** The Dockerfile doesn't specify a `USER` directive, so the FrankenPHP process runs as root inside the container. The `php.ini` even sets `opcache.preload_user=root`.

**Suggestion:** Create a non-root user and switch to it. FrankenPHP images may support running as a non-root user — check the base image documentation.

**Why:** Running as root inside a container is a security anti-pattern. If the application has a vulnerability (e.g. the `PublicAssetController`'s file serving), an attacker could potentially write files as root. For a personal project on a LAN, this is low risk, but it's a best practice that costs nothing to adopt.

---

## 7. Dependencies & Configuration

### 7.1 — `host.env` committed with real secrets 🟡 Medium

**Current state:** `host.env` is in `.gitignore`, but it's present in the working directory with what appear to be real API key and APP_SECRET values. If someone accidentally removes it from `.gitignore` or uses `git add -f`, these secrets would be committed.

**Suggestion:** Verify that `host.env` is truly git-ignored (it is, per `.gitignore`). Consider using `git check-ignore host.env` to verify. The values in `host.env` look like real secrets (64-char hex strings), not placeholders.

**Why:** The `.gitignore` entry exists, so this is more of a vigilance note. The file's presence with real values is a risk if the gitignore is ever modified. The `.env` file (committed) correctly uses `change_me_` placeholders, which is the right pattern.

### 7.2 — Unused Symfony components in `composer.json` 🟡 Medium

**Current state:** `composer.json` requires `symfony/serializer`, `symfony/property-access`, `symfony/property-info`, `symfony/var-exporter`, and `phpdocumentor/reflection-docblock`. None of these are used in the application code — the controller does manual serialization, and there's no Serializer usage anywhere in `src/`.

**Suggestion:** Remove the unused dependencies, or actually use the Serializer (see finding 1.1). If they were installed for future use, they should be documented as such.

**Why:** Unused dependencies increase the project's attack surface, slow down `composer install`, and make the dependency graph harder to reason about. The Serializer, PropertyAccess, and PropertyInfo components are substantial libraries. If the intent is to use them (which would be a good architectural choice), then use them; otherwise, remove them.

### 7.3 — Symfony version requirements are inconsistent 🟢 Low

**Current state:** `composer.json` requires Symfony components at `^8.1`, but `symfony.lock` shows some recipes were installed at version 7.4 (e.g. `symfony/console` recipe at version 7.4, `symfony/framework-bundle` at 7.4). The CHANGELOG mentions "Symfony 7.x / Doctrine ORM / SQLite backend" in the v1.0.0 entry. The ROADMAP notes "mixed component versions."

**Suggestion:** Ensure all Symfony components are on the same major version. Run `composer update` to align everything, then verify the lock file is consistent.

**Why:** Mixing Symfony major versions (7.x and 8.x) is unsupported and can lead to subtle incompatibilities. Since `composer.json` already specifies `^8.1` for all Symfony components, the lock file should reflect 8.x versions throughout. If it doesn't, a `composer update` is needed.

### 7.4 — `composer.phar` committed to the repository 🟢 Low

**Current state:** A 3.6MB `composer.phar` file is in the project root. It's listed in `.gitignore` but appears to be present in the working tree.

**Suggestion:** Verify it's not tracked by git (`git ls-files composer.phar`). If it is tracked, remove it. Composer is available in CI and Docker builds.

**Why:** Committing a 3.6MB binary to a git repository bloats the repo history. The `.gitignore` entry exists, but the file's presence suggests it may have been committed before the ignore rule was added.

---

## 8. API Design

### 8.1 — No GET-by-ID, PUT/PATCH, or DELETE endpoints 🟡 Medium

**Current state:** The API only supports `POST /api/v1/logs` (create) and `GET /api/v1/logs` (list). There's no way to retrieve, update, or delete a single entry.

**Suggestion:** Add `GET /api/v1/logs/{id}`, `PUT /api/v1/logs/{id}`, and `DELETE /api/v1/logs/{id}`. The ROADMAP already plans these.

**Why:** Without a DELETE endpoint, mistyped entries (which will happen — e.g. entering 1200 instead of 120 for systolic) can't be removed without direct database access. Without PUT, they can't be corrected. These are basic CRUD operations that a personal data app should support. The ROADMAP correctly identifies this as Phase 2.

### 8.2 — No pagination on GET endpoint 🟡 Medium

**Current state:** `GET /api/v1/logs` returns all matching entries with no limit. Over months/years of daily logging, this could return hundreds or thousands of records in a single response.

**Suggestion:** Add `page` and `limit` query parameters (with sensible defaults, e.g. 50 per page, max 200). Return pagination metadata.

**Why:** For a personal health app used daily, you'd accumulate 365+ entries per year. Loading all of them on every dashboard render is wasteful — the charts only show the filtered date range anyway. Pagination would also make the API more suitable for the planned MCP integration (fetching recent entries without downloading the entire history).

### 8.3 — API versioning is in the URL but there's no versioning strategy 🟢 Low

**Current state:** Routes are prefixed with `/api/v1/`. This is good practice, but there's no documentation of what would trigger a v2 or how backward compatibility would be maintained.

**Suggestion:** Document the versioning policy: "v1 endpoints are stable; breaking changes will go to v2." This is a documentation task, not a code change.

**Why:** The `/v1/` prefix signals intent to version, but without a stated policy, consumers don't know what to expect. A one-paragraph note in the README would clarify expectations.

### 8.4 — `PublicAssetController` route catches all non-API paths 🟢 Low

**Current state:** The `serve_asset` route has `requirements: ['path' => '.+']`, meaning it matches any path that doesn't match a more specific route. This is the catch-all that serves static files. It's placed after the API routes in the router, so API routes take priority.

**Suggestion:** This works correctly as implemented. The directory traversal protection is thorough (checks for `..`, leading `/`, `.php` extension, and verifies `realpath` is inside `public/`). No change needed, but consider adding a test for symlink-based traversal attempts.

**Why:** This is actually well done. The multiple layers of defense (string check, extension block, realpath verification) are the right approach. Noting it as a positive finding — the security-conscious approach to file serving is one of the project's strengths.

---

## 9. Project Hygiene

### 9.1 — `config/reference.php` is a 63KB auto-generated file in version control 🟡 Medium

**Current state:** `config/reference.php` is 63KB of auto-generated Symfony configurator reference code. It's listed in `.gitignore` but appears to be present in the working tree.

**Suggestion:** Verify it's not tracked by git. If it is, `git rm --cached config/reference.php` and let `.gitignore` handle it.

**Why:** This file is regenerated by Symfony and has no business being in version control. It adds noise to diffs and code review. The `.gitignore` entry exists; just need to ensure the file isn't tracked.

### 9.2 — CONTRIBUTING.md references php-cs-fixer which isn't installed ✅ Resolved

**Resolution:** `friendsofphp/php-cs-fixer` has been added to `require-dev` and a `.php-cs-fixer.dist.php` config has been created with the PSR-12 ruleset. CONTRIBUTING.md has been updated with proper usage instructions.

**Previous state:** CONTRIBUTING.md referenced `php-cs-fixer` but it was not in `composer.json` dependencies. Running the documented command would fail.

**Why:** Documentation that references non-existent tools creates a poor contributor experience. Someone following the guide will hit an error and may give up.

### 9.3 — README references `AUTH_SECRET` which doesn't exist 🟡 Medium

**Current state:** The README's configuration table lists:

> `AUTH_SECRET` — `vital-pulse-master` — Defined in env but currently unused — slated for removal

But `AUTH_SECRET` does not appear in `.env`, `.env.example`, `.env.test`, or `host.env`. It was already removed from the env files but the README still references it.

**Suggestion:** Remove the `AUTH_SECRET` row from the README configuration table.

**Why:** Stale documentation is worse than no documentation. A user looking at the config table will wonder where `AUTH_SECRET` is and whether they need to set it. The ROADMAP also references it, but that's a historical document so it's fine there.

### 9.4 — README's Docker port (9000) doesn't match Dockerfile (80) 🟡 Medium

**Current state:** The README says "The API and dashboard are both available at `http://localhost:9000`" and shows `php -S 0.0.0.0:9000 -t public/` for manual setup. But the Dockerfile `EXPOSE`s port 80, and `compose.yaml` exposes port 80 (not mapped to host). The manual dev server instructions use port 9000, which is correct for that context, but the Docker quick start doesn't mention port mapping.

**Suggestion:** Clarify which port applies to which setup. For Docker, either add `ports: ["8080:80"]` to `compose.yaml` (the `.env` defines `VITALPULSE_PORT=8080` but it's not used) or document that the container listens on 80 and should be accessed via the reverse proxy.

**Why:** A new user following the Docker quick start will `docker compose up -d` and then try to access `http://localhost:9000` (per the README), which won't work because the container is on port 80 and isn't mapped to the host. The `VITALPULSE_PORT` env var is defined but never consumed by `compose.yaml`.

### 9.5 — `.phpunit.result.cache` in the project root 🟢 Low

**Current state:** `.phpunit.result.cache` is present in the project root and is listed in `.gitignore`. This is expected behavior — PHPUnit's result cache.

**Suggestion:** No action needed. The `.gitignore` entry is correct.

**Why:** Noting for completeness — this is working as intended.

### 9.6 — No `.gitkeep` or placeholder in `var/data/` 🟢 Low

**Current state:** The `var/` directory is git-ignored entirely. The Docker entrypoint creates `var/data/` at runtime, but for local development, a new clone won't have the directory.

**Suggestion:** The README's manual setup section already includes `mkdir -p var/data`, which is the right approach. Alternatively, add a `var/data/.gitkeep` with an exception in `.gitignore` to ensure the directory exists after clone.

**Why:** A new contributor who skips the README step and runs `composer install && php -S ... -t public/` will get a database error because `var/data/` doesn't exist. The README documents this, but a `.gitkeep` would prevent the error entirely.

---

## Summary of Priorities

### 🔴 High-impact findings (fix first)
1. **Validation groups never activated** — range constraints are dead code (3.1)
2. **Internal exception messages leaked in 500 responses** (2.1)
3. **Manual serialization duplicated** between controller methods (1.1)
4. **Validator instantiated ad-hoc** instead of via DI (1.2)
5. **Docker healthcheck** doesn't test the application, just the web server (6.1)

### 🟡 Medium-impact findings (address when touching related code)
6. Input type coercion is unsafe — `(int)"abc"` silently becomes `0` (3.2)
7. No security headers (2.4)
8. API key in localStorage with no CSP (2.2)
9. API key via query parameter logged by proxies (2.3)
10. No `declare(strict_types=1)` despite CONTRIBUTING.md requiring it (1.4)
11. Unused Symfony dependencies in composer.json (7.2)
12. `host.env` with real secrets in working tree (7.1)
13. No tests for `SystemController` (4.1)
14. No edge-case tests for invalid input types (4.3)
15. No emoji validation (3.3)
16. Entrypoint uses `schema:update --force` (6.2)
17. Deprecated `deploy.sh` and `run.sh` still in root (6.3)
18. ~~CONTRIBUTING.md references uninstalled php-cs-fixer (9.2)~~ ✅ Resolved
19. README references non-existent `AUTH_SECRET` (9.3)
20. README Docker port doesn't match Dockerfile (9.4)
21. No pagination on GET endpoint (8.2)
22. No CRUD endpoints beyond create/list (8.1)
23. `config/reference.php` should not be tracked (9.1)
24. Frontend API key retry can recurse infinitely (5.1)
25. Vendored JS libraries lack exact version tracking (5.2)
26. EntityManager injected directly into controller (1.3)

### 🟢 Low-impact findings (nice-to-have improvements)
27. `SystemController::getVersion()` shells out to git, always fails in Docker (1.5)
28. API key not hashed at rest (2.5)
29. No future-timestamp validation (3.4)
30. Inconsistent error response format for multiple validation errors (3.5)
31. Test schema setup duplicated (3.4)
32. Test persists HealthLog without measurements (4.5)
33. `commonOptions` shared by reference across charts (5.3)
34. No error boundary for chart rendering (5.4)
35. `favicon.svg` is 293KB (5.5)
36. External Docker network undocumented (6.4)
37. Docker image runs as root (6.5)
38. Symfony version inconsistency in lock file (7.3)
39. `composer.phar` in working tree (7.4)
40. No API versioning strategy documented (8.3)
41. No `.gitkeep` in `var/data/` (9.6)

---

## What's Done Well

- **Directory traversal protection** in `PublicAssetController` is thorough and multi-layered (4.4)
- **Timing-safe API key comparison** via `hash_equals()` (2.5)
- **Test suite** covers the core functionality well — entity, repository, controller, and security tests exist
- **CI pipeline** runs tests on every push and PR with the correct PHP version
- **`.dockerignore`** is comprehensive and correctly excludes test files, secrets, and build artifacts
- **Environment configuration** follows Symfony best practices — committed `.env` with safe defaults, git-ignored overrides
- **Docker multi-stage build** separates composer install from runtime, with good layer caching
- **Documentation** (README, CHANGELOG, ROADMAP, CONTRIBUTING) is thorough and honest about current limitations
- **OPcache configuration** in `php.ini` is well-tuned for FrankenPHP worker mode

# VitalPulse — Project Roadmap

## Project Overview

VitalPulse is a personal health vitals tracker built with **Symfony**
(PHP ≥ 8.4), **Doctrine ORM** (SQLite by default), and a vanilla
HTML/JS dashboard with Chart.js. It tracks blood pressure, heart rate,
body weight, and mood (emoji) in a single unified log entry.

- **Location:** `projects/vital-pulse/`
- **Framework:** Symfony 7.x/8.x (mixed component versions)
- **Language:** PHP ≥ 8.4
- **Database:** SQLite (`var/data/health_tracker.db`), MySQL-capable
- **Frontend:** Plain HTML + Chart.js v4 + Luxon (no build step)
- **Auth:** Single API key (timing-safe comparison via `hash_equals`)

---

## Current State — Audit Summary

### What's Working

- ✅ Health log creation (`POST /api/v1/logs`) with validation
- ✅ Health log querying (`GET /api/v1/logs`) with date range + emoji filter
- ✅ API key authentication (header or query param, timing-safe comparison)
- ✅ Dashboard UI with three Chart.js graphs (BP, HR, weight) + emoji selector
- ✅ Basic test suite (controller, entity, repository, security tests)
- ✅ Dockerfile (multi-stage) and docker-compose.yml exist
- ✅ Caddyfile for reverse proxy deployment
- ✅ Repository has `getStatsForDateRange()` — aggregate stats (avg/min/max)
- ✅ **README** — comprehensive documentation with quick start, API
      reference, data model, and deployment guide
- ✅ **Open-source project files** — LICENSE (MIT), CONTRIBUTING.md,
      CODE_OF_CONDUCT.md, CHANGELOG.md
- ✅ **Legacy CLI tools removed** — `vitalpulse-cli.php` and
      `bin/health-commander` were incomplete and have been deleted

### What's Missing or Needs Work

- ❌ **No GET-by-ID, no PUT/PATCH, no DELETE endpoints** — can't update or
      remove a log entry via API
- ❌ **Stats endpoint not exposed** — `getStatsForDateRange()` exists in the
      repository but no controller route calls it
- ❌ **Validation groups bug** — `@Assert\Range` constraints (systolic
      60–250, diastolic 40–150, HR 30–250, weight 30–400) are in the
      `health_check` group, but the controller validates without specifying
      any group, so **range validation never runs**
- ❌ **No Doctrine migrations** — schema created ad-hoc via `SchemaTool`
- ❌ **Symfony version mixing** — composer.json requires a mix of `^7.2`
      and `^8.0`/`^8.1` Symfony components, which is unusual and risky
- ❌ **Docker image isn't published** — Dockerfile exists but uses a
      custom `symfony/runtime` binary download that may be fragile
- ❌ **docker-compose uses MySQL** but default config is SQLite —
      confusing for new users
- ❌ **No pagination on GET** — returns all matching logs; could be slow
      with years of data
- ❌ **API key stored in plaintext** in env var (compared with
      `hash_equals`, but not bcrypt-hashed like penny-track)
- ❌ **No rate limiting**
- ❌ **No CORS headers** (fine for same-origin, blocks external consumers)
- ❌ **No security headers** (CSP, X-Content-Type-Options, etc.)
- ❌ **Frontend has a bug** — `initFilterEmoji()` queries
      `getElementById('filter-emoji')` and then appends children to it,
      but also creates a `span` variable that's never used separately

---

## Existing API Endpoints

### Health Logs

| Method | Path             | Auth | Parameters                                              |
|--------|------------------|------|---------------------------------------------------------|
| POST   | `/api/v1/logs`   | Yes  | `systolic?`, `diastolic?`, `heart_rate?`, `weight?`, `emoji?`, `timestamp?` |
| GET    | `/api/v1/logs`   | Yes  | `from?`, `to?`, `emoji?` (or `emoji[]`)                |

### HealthLog Data Model

| Field        | Type                 | Nullable | Default  | Constraints (defined)                          |
|--------------|----------------------|----------|----------|------------------------------------------------|
| `id`         | int (auto-increment) | auto     | —        | Primary key                                    |
| `timestamp`  | datetime_immutable   | no       | now(UTC) | `@Assert\NotBlank`                             |
| `systolic`   | int                  | yes      | null     | `@Assert\PositiveOrZero`, `Range(60–250)` *    |
| `diastolic`  | int                  | yes      | null     | `@Assert\PositiveOrZero`, `Range(40–150)` *    |
| `heartRate`  | int                  | yes      | null     | `@Assert\PositiveOrZero`, `Range(30–250)` *    |
| `weight`     | float                | yes      | null     | `@Assert\Positive`, `Range(30–400)` *          |
| `emoji`      | string (len 10)      | no       | `😐`     | Falls back to `😐` if empty                    |

*\* Range constraints are in the `health_check` validation group, which
is never activated — so these ranges are NOT enforced at runtime.*

### Business Rules

- At least one measurement field must be present (systolic, diastolic,
  heart_rate, or weight) — emoji-only entries are rejected.
- If systolic is provided, diastolic must also be provided (and vice
  versa).
- Timestamp defaults to now (UTC) if not provided.

### Configuration

| Variable        | Default                                              | Purpose                     |
|-----------------|------------------------------------------------------|-----------------------------|
| `DATABASE_URL`  | `sqlite:///%kernel.project_dir%/var/data/health_tracker.db` | Database connection |
| `API_KEY`       | `change_me_to_a_strong_secret_key_...`               | API key for authentication  |
| `APP_ENV`       | `dev`                                                | Symfony environment         |
| `APP_SECRET`    | *(generated)*                                        | Symfony secret              |
| `AUTH_SECRET`   | `vital-pulse-master`                                 | Purpose unclear (unused?)   |

---

## Roadmap

### Phase 1 — Hardening & Bug Fixes

**Goal:** Fix known bugs, tighten validation, and make the codebase
robust before adding features or publishing.

- [ ] **Fix validation groups bug** — either activate the `health_check`
      group in the controller, or remove the group so range constraints
      run by default. This is a data integrity issue.
- [ ] **Normalise Symfony versions** — pin all Symfony components to
      either `^7.2` or `^8.0` consistently. Mixing major versions is
      unsupported and could break unexpectedly.
- [ ] **Add Doctrine migrations** — generate the initial migration from
      the existing entity, replace ad-hoc `SchemaTool` usage in tests
      with proper migration-based schema management.
- [x] **Remove or fix broken CLI tools** — `vitalpulse-cli.php` and
      `bin/health-commander` were incomplete and have been removed.
      A proper CLI can be built later using `bin/console` if needed.
- [ ] **Clarify `AUTH_SECRET`** — this env var is defined but its
      purpose is unclear. Either document it or remove it.
- [ ] **Fix frontend `initFilterEmoji()` bug** — the function queries
      `getElementById('filter-emoji')` and appends children to it, but
      also creates an unused `span` variable. Review and fix.
- [ ] **Input validation philosophy — warn, don't block:**
  - The current `@Range` constraints (systolic 60–250, etc.) are too
    strict for a health app. Abnormal values might be real: a child's
    blood pressure could be 80/50, an athlete's resting HR could be 35,
    etc.
  - **Approach:** Accept a very wide range of values (e.g. systolic
    20–400, weight 5–1000) and use the frontend to show a gentle
    warning ("That seems high — double-check?") instead of hard
    rejection. The server should only reject physically impossible
    values (negative, zero, non-numeric).
  - [ ] Widen server-side `@Range` constraints to accept edge cases
  - [ ] Add `Type` constraints (reject strings for numeric fields)
  - [ ] Validate `emoji` length and character set
  - [ ] Reject future timestamps
  - [ ] Add max payload size check
- [ ] **Security hardening:**
  - [ ] Add rate limiting on API endpoints
  - [ ] Add security headers (CSP, X-Content-Type-Options, etc.)
  - [ ] Ensure `APP_ENV=prod` disables debug output
  - [ ] Consider bcrypt-hashing the API key (like penny-track does)
- [ ] **Error response consistency:**
  - [ ] Standardise as `{"error": "message", "details": {...}}`
  - [ ] Don't leak internal exception messages in 500 responses
- [ ] **Comprehensive test coverage:**
  - [ ] Edge cases: negative values, zero values, out-of-range values
  - [ ] Invalid JSON body types (string instead of object)
  - [ ] Concurrent POST requests
  - [ ] Date range edge cases (same day, crossing year boundary)
  - [ ] Emoji filter with multiple values, no values, invalid emojis
  - [ ] Pagination (once implemented)

### Phase 2 — API Completeness

**Goal:** Round out the API to support full CRUD and expose the stats
endpoint.

- [ ] `GET /api/v1/logs/{id}` — fetch a single log entry by ID
- [ ] `PUT /api/v1/logs/{id}` — update an existing log entry (partial
      updates, same validation as POST)
- [ ] `DELETE /api/v1/logs/{id}` — delete a log entry
- [ ] `GET /api/v1/logs/stats` — expose `getStatsForDateRange()`:
  - Query params: `from`, `to` (same date range filtering as list)
  - Returns: `avg`, `min`, `max` for systolic, diastolic, heart_rate,
    weight, plus `count`
- [ ] `GET /api/v1/logs/latest` — convenience endpoint to get the most
    recent log entry (or most recent with a specific measurement, e.g.
    `?field=weight`). This is what the MCP integration needs for
    "when did I last log my weight?"
  - Returns the single most recent entry, or the most recent entry
    where the requested field is non-null
  - Query params: `field` (optional) — `weight`, `systolic`,
    `heart_rate` — returns latest entry where that field is set
- [ ] Add pagination to `GET /api/v1/logs`:
  - `page` (default 1), `limit` (default 50, max 200)
  - Response wrapped in `{"data": [...], "meta": {"page", "limit", "total", "pages"}}`
- [ ] Tests for all new endpoints
- [ ] Update frontend to handle paginated response

### Phase 3 — Docker & Publishing

**Goal:** Get VitalPulse containerised and published publicly.

- [ ] **Production Dockerfile rewrite:**
  - Use PHP-FPM + Caddy (or FrankenPHP as originally planned) instead
    of the custom `symfony-runtime` binary download
  - Multi-stage build: composer install in build stage, copy to runtime
  - Default to SQLite (simpler for personal use), document MySQL option
  - Volume for `var/` (database + logs)
  - Health check endpoint (`/health` or reuse `/api/v1/logs` with auth)
  - Run migrations on container startup
  - `APP_ENV=prod` by default in the image
- [ ] **docker-compose.yml cleanup:**
  - Default to SQLite (single service, no database container needed)
  - Optional MySQL profile for larger deployments
  - Remove hardcoded credentials from compose file
  - Use `.env` file for all secrets
- [ ] **Docker Hub publishing:**
  - Build and tag as `docker.io/<user>/vital-pulse:latest`
  - Version tags (`:v1.0.0`, `:1.0`, `:1`)
  - GitHub Actions workflow for automated builds on tag push
  - Multi-arch builds (amd64 + arm64 for Raspberry Pi deployment)
- [ ] **GitHub repository:**
  - [x] Git repo initialised
  - [x] Write comprehensive README:
    - What it is, screenshots
    - Quick start (Docker)
    - API reference
    - Configuration
    - Deployment guide
  - [x] Add LICENSE (MIT, matching composer.json)
  - [x] Add CONTRIBUTING.md, CODE_OF_CONDUCT.md, CHANGELOG.md
  - Set up branch protection on `main`
  - GitHub Actions CI (run tests on push/PR)

### Phase 4 — Polish & v1.0 Release

**Goal:** Production-ready v1.0 with a polished frontend.

- [ ] **Improved data entry UX — logic-based auto-advance:**
  - Modelled after how browser date/time inputs work: the field
    auto-advances based on logical value boundaries, not timers or
    fixed digit counts.
  - **Systolic / Diastolic / Heart Rate** (valid range 30–299):
    - If the first digit typed is **3–9**, the value is a 2-digit
      number (30–99) → auto-advance after the 2nd digit.
    - If the first digit typed is **0–2**, the value is a 3-digit
      number (100–299) → auto-advance after the 3rd digit.
    - This means typing `8` `5` → advances (85). Typing `1` `2` `0` →
      advances (120). No ambiguity, no timeout.
  - **Weight** (valid range ~10–600+):
    - **No auto-advance.** The range is too wide for digit-count logic
      to work reliably (10, 100, 185, 185.4 — all valid). Let the user
      tab/enter to move on manually.
  - Enter/Tab always advances immediately regardless of field.
  - Field order: systolic → diastolic → heart rate → weight → emoji
    (emoji is click-based, so focus the submit button after weight).
  - Add a brief highlight animation on the newly-focused field so the
    user sees the advance happened.
  - Backspace on an empty field moves focus back to the previous field
    (standard UX expectation).
  - [ ] Implement logic-based auto-advance in `app.js`
  - [ ] Add gentle client-side validation warnings (not blocks) for
    abnormal-but-possible values (e.g. "BP 180/110 — that's quite high,
    is that correct?")
  - [ ] Test on mobile (auto-advance + mobile keyboards can be tricky)
- [ ] **Other frontend improvements:**
  - Responsive/mobile audit (ensure charts and form work on phone)
  - PWA manifest is already present — verify it works
  - Add a "latest reading" summary card on the dashboard
  - Add trend indicators (↑/↓ arrows with delta vs last reading)
  - Date range presets (7 days, 30 days, 90 days, 1 year, all time)
  - Export data as CSV
- [ ] **Dashboard stats integration:**
  - Call the new `GET /api/v1/logs/stats` endpoint
  - Show averages, min/max in a summary panel
  - Show trend: "Down 3.2 lbs in the last 30 days 🎉"
- [ ] **Documentation:**
  - API reference (OpenAPI/Swagger or hand-written in README)
  - Deployment guide (Docker, bare metal, Caddy reverse proxy)
  - Configuration reference
  - Changelog
- [ ] **Release process:**
  - Tag `v1.0.0`
  - GitHub release with release notes and screenshots
  - Docker image published to Docker Hub
  - Announcement

---

## MCP Server Integration

The MCP integration for VitalPulse is different from penny-track —
health vitals require the user's active participation (stepping on a
scale, taking blood pressure), so the focus is on **reminders and trend
analysis** rather than automated logging.

### What's Useful

| Use Case                  | Description                                              |
|---------------------------|----------------------------------------------------------|
| "Last logged" reminder    | "Boss, you haven't logged your weight in 4 days"         |
| Trend analysis            | "Down 10 pounds in the last month — that's awesome!"     |
| Streak tracking           | "You've logged your BP every day for 12 days straight"   |
| Anomaly detection         | "Your resting HR is 15 bpm above your 30-day average"    |
| Morning summary inclusion | Include last-logged vitals + trends in the daily summary |

### What's NOT Useful

| Use Case              | Why                                                  |
|-----------------------|------------------------------------------------------|
| Automated logging     | User must physically take the measurement            |
| Auto-creating entries | Would create garbage data                            |

### Required API Additions

For the MCP integration to work, VitalPulse needs:

1. **`GET /api/v1/logs/latest`** (Phase 2) — returns the most recent log
   entry, optionally filtered by field. This lets Lyra check "when was
   the last weight entry?" efficiently without fetching all logs.

2. **`GET /api/v1/logs/stats`** (Phase 2) — exposes the existing
   `getStatsForDateRange()` repository method. This lets Lyra compute
   trends: "average weight this month vs last month."

### MCP Server Endpoints

The MCP server would proxy VitalPulse's API (same pattern as penny-track,
API key managed server-side via `VITAL_PULSE_API_KEY`):

| Method | MCP Endpoint                       | Maps to VitalPulse                  |
|--------|------------------------------------|-------------------------------------|
| GET    | `/vital-pulse/logs`                | `GET /api/v1/logs` (list)           |
| GET    | `/vital-pulse/logs/latest`         | `GET /api/v1/logs/latest` (new)     |
| GET    | `/vital-pulse/logs/{id}`           | `GET /api/v1/logs/{id}` (new)       |
| GET    | `/vital-pulse/logs/stats`          | `GET /api/v1/logs/stats` (new)      |
| POST   | `/vital-pulse/logs`                | `POST /api/v1/logs` (create)        |

> **Note:** POST is proxied for convenience (user could say "log my
> weight at 185 lbs" and Lyra can do it), but the primary use case is
> read-only reminders and trend analysis. The VitalPulse dashboard UI
> remains the primary logging interface — it's quick and easy.

### Workflow: Stale Data Reminder

**Trigger:** Scheduled daily (e.g., included in the morning summary
automation).

**Logic:**

1. Call `GET /vital-pulse/logs/latest?field=weight` — get the most recent
   weight entry.
2. If the entry is > 3 days old, include in morning summary:
   *"Boss, you haven't logged your weight since Tuesday — hop on the
   scale when you get a chance."*
3. Repeat for blood pressure (`?field=systolic`) and heart rate.

### Workflow: Trend Analysis

**Trigger:** On-demand ("how's my weight doing?") or in the morning
summary.

**Logic:**

1. Call `GET /vital-pulse/logs/stats?from=2025-01-01&to=2025-01-31`
   (this month).
2. Call `GET /vital-pulse/logs/stats?from=2024-12-01&to=2024-12-31`
   (last month).
3. Compare averages:
   - Weight: "Down 3.2 lbs from last month — keep it up! 🎉"
   - BP: "Systolic averaging 125, down from 130 last month."
   - HR: "Resting HR stable at 72 bpm."

---

## Architecture Notes

### Current Tech Stack

```
┌─────────────────────────────────────────────┐
│  Browser (HTML + Chart.js v4 + Luxon)       │
│  ├── Dashboard (3 line charts + stats)      │
│  └── Log entry form (BP, HR, weight, emoji) │
├─────────────────────────────────────────────┤
│  Symfony (PHP 8.4+)                         │
│  ├── HealthApiController (POST, GET)        │
│  ├── ApiKeySubscriber (auth middleware)     │
│  ├── Doctrine ORM 3.x                       │
│  └── HealthLogRepository                    │
│      ├── findByDateRange()                  │
│      └── getStatsForDateRange() (unused)    │
├─────────────────────────────────────────────┤
│  SQLite (var/data/health_tracker.db)        │
└─────────────────────────────────────────────┘
```

### Deployment

Currently deployed via two methods:

1. **Caddy reverse proxy** (`Caddyfile`) — serves static frontend files
   from `~/host/caddy/data/data/vitals/`, proxies `/api/*` to the PHP
   server on port 9000. Domain: `vitals.devgnome.com`.
2. **Docker Compose** — runs app container + MySQL 8.0, exposes port
   9000. The Dockerfile uses a custom `symfony/runtime` binary to serve
   PHP.

The `deploy.sh` script copies frontend files to the Caddy data
directory. The `run.sh` script starts the PHP built-in server in a
`screen` session.

### Relationship to Other Projects

| Project     | Integration                                          |
|-------------|------------------------------------------------------|
| MCP server  | Proxy endpoints for reminders and trend analysis    |
| Email integration | Vitals data included in morning summary        |
| Discord/ntfy | Stale data reminders delivered via Discord          |

See `projects/mcp_server/plans/email-integration-roadmap.md` for the
morning summary workflow that includes VitalPulse data.

---

*Built by Lyra. ✨*

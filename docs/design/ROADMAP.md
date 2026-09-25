# VitalPulse — Project Roadmap

> Last updated: 2026-08-25 (updated to reflect compose.yaml and Docker fixes)

## Project Overview

VitalPulse is a personal health vitals tracker built with **Symfony**
(PHP ≥ 8.4), **Doctrine ORM** (SQLite by default), and a vanilla
HTML/JS dashboard with Chart.js. It tracks blood pressure, heart rate,
body weight, and mood (emoji) in a single unified log entry.

- **Location:** `projects/vital-pulse/`
- **Framework:** Symfony 8.1 (consistent component versions)
- **Language:** PHP ≥ 8.4 (all files declare `strict_types=1`)
- **Database:** SQLite (`var/data/health_tracker.db`) via Doctrine ORM,
  schema managed by migrations
- **Frontend:** Plain HTML + Chart.js v4 + Luxon (no build step)
- **Auth:** Single API key (timing-safe comparison via `hash_equals`)
- **Docker:** Multi-stage FrankenPHP image, `docker compose` deployable

---

## Current State — Audit Summary

### What's Working

- ✅ Health log creation (`POST /api/v1/logs`) with full validation
- ✅ Health log querying (`GET /api/v1/logs`) with date range, emoji
      filter, and **pagination** (`page`, `limit` → `{"data", "meta"}`)
- ✅ **Full CRUD** — `GET`/`PUT`/`DELETE /api/v1/logs/{id}`
- ✅ **Stats endpoint** — `GET /api/v1/logs/stats` (avg/min/max/count
      over a date range) exposed from `getStatsForDateRange()`
- ✅ **CSV export** — `GET /api/v1/logs/export`
- ✅ **System endpoints** — `GET /api/about`, `GET /api/health`
- ✅ API key authentication (timing-safe comparison)
- ✅ **Validation groups fixed** — controller now validates against
      both `Default` and `health_check` groups, so range constraints
      actually run (widened ranges per the “warn, don't block”
      philosophy: systolic 20–400, diastolic 10–300, HR 20–350,
      weight 5–1000)
- ✅ **Input type validation** — non-numeric values for numeric fields
      rejected; future timestamps rejected (5-minute clock skew
      tolerance); payload coercion errors reported per-field
- ✅ Doctrine migrations (`Version20260811130655.php`), run on
      container startup by the entrypoint
- ✅ Symfony components consistently pinned to `^8.1`; unused
      components removed
- ✅ **Security headers** via `SecurityHeadersSubscriber`
      (CSP, X-Content-Type-Options, etc.)
- ✅ Dashboard UI: three Chart.js graphs (BP, HR, weight), emoji
      selector, filter emojis, date range presets, latest-reading
      summary card, trend indicators (↑/↓ vs previous reading),
      client-side soft validation warnings, responsive/mobile audit,
      validated PWA manifest
- ✅ **Test suite** — controller (HealthApi, System, PublicAsset),
      entity, repository, and security tests; schema bootstrapped via
      `SchemaSetupTrait`
- ✅ Dockerfile — clean multi-stage FrankenPHP build, runs as
      non-root (`nobody:nogroup`)
- ✅ Docker healthcheck — calls `/api/health` which verifies DB
      connectivity (`SELECT 1` against SQLite)
- ✅ compose.yaml — defaults to SQLite, single service, uses default
      Compose network (no external network dependency)
- ✅ Caddyfile for reverse proxy deployment
- ✅ Comprehensive README, LICENSE (MIT), CONTRIBUTING.md,
      CODE_OF_CONDUCT.md, CHANGELOG.md
- ✅ `.env.dev` untracked (was committed with a real APP_SECRET)
- ✅ Vendored JS libraries have exact version tracking

### What's Missing or Needs Work

- ❌ **`GET /api/v1/logs/latest`** — convenience endpoint for the most
      recent entry (optionally `?field=weight|systolic|heart_rate`).
      This is the main blocker for MCP “stale data” reminders.
- ❌ **Rate limiting** on API endpoints
- ❌ **README/ROADMAP cross-check** — README was fixed (stale
      `AUTH_SECRET`, wrong defaults, wrong test count) but keep docs in
      sync going forward

---

## Existing API Endpoints

### Health Logs

| Method | Path                     | Auth | Parameters                                              |
|--------|--------------------------|------|---------------------------------------------------------|
| POST   | `/api/v1/logs`           | Yes  | `systolic?`, `diastolic?`, `heart_rate?`, `weight?`, `emoji?`, `timestamp?` |
| GET    | `/api/v1/logs`           | Yes  | `from?`, `to?`, `emoji?`/`emoji[]`, `page?`, `limit?`    |
| GET    | `/api/v1/logs/stats`     | Yes  | `from?`, `to?` → avg/min/max/count per field             |
| GET    | `/api/v1/logs/export`    | Yes  | `from?`, `to?`, `emoji?` → CSV download                  |
| GET    | `/api/v1/logs/{id}`      | Yes  | Fetch single entry                                       |
| PUT    | `/api/v1/logs/{id}`      | Yes  | Partial update, same validation as POST                 |
| DELETE | `/api/v1/logs/{id}`      | Yes  | Delete an entry                                          |

### System

| Method | Path          | Auth | Purpose                        |
|--------|---------------|------|--------------------------------|
| GET    | `/api/about`  | No   | Version + build info           |
| GET    | `/api/health` | No   | Liveness/readiness probe       |

### HealthLog Data Model

| Field        | Type                 | Nullable | Default  | Constraints (enforced)                |
|--------------|----------------------|----------|----------|---------------------------------------|
| `id`         | int (auto-increment) | auto     | —        | Primary key                           |
| `timestamp`  | datetime_immutable   | no       | now(UTC) | `NotBlank`, not in the future         |
| `systolic`   | int                  | yes      | null     | `PositiveOrZero`, `Range(20–400)`     |
| `diastolic`  | int                  | yes      | null     | `PositiveOrZero`, `Range(10–300)`     |
| `heartRate`  | int                  | yes      | null     | `PositiveOrZero`, `Range(20–350)`     |
| `weight`     | float                | yes      | null     | `Positive`, `Range(5–1000)`           |
| `emoji`      | string (len 10)      | no       | `😐`     | Falls back to `😐` if empty            |

*The `health_check` validation group **is** activated by the
controller, so the widened ranges above are enforced at runtime.
Deliberately abnormal-but-possible values get soft warnings in the
frontend, not hard rejections.*

### Business Rules

- At least one measurement field must be present (systolic, diastolic,
  heart_rate, or weight) — emoji-only entries are rejected.
- If systolic is provided, diastolic must also be provided (and vice
  versa).
- Timestamp defaults to now (UTC) if not provided; future timestamps
  are rejected.

### Configuration

| Variable       | Default                                                   | Purpose                    |
|----------------|-----------------------------------------------------------|----------------------------|
| `DATABASE_URL` | `sqlite:///%kernel.project_dir%/var/data/health_tracker.db` | Database connection      |
| `API_KEY`      | `change_me_to_a_strong_secret_key_...`                    | API key for authentication |
| `APP_ENV`      | `dev` (`prod` in Docker)                                  | Symfony environment        |
| `APP_SECRET`   | *(generated)*                                             | Symfony secret             |
| `DEFAULT_URI`  | `http://localhost`                                        | FrankenPHP base URI        |

---

## Roadmap

### Phase 1 — Hardening & Bug Fixes ✅ (mostly complete)

**Goal:** Fix known bugs, tighten validation, and make the codebase
robust before adding features or publishing.

- [x] **Fix validation groups bug** — controller validates both
      `Default` and `health_check` groups; range constraints now run
- [x] **Normalise Symfony versions** — all components pinned to `^8.1`
- [x] **Add Doctrine migrations** — `Version20260811130655.php`,
      entrypoint runs migrations on startup
- [x] **Remove or fix broken CLI tools** — `vitalpulse-cli.php` and
      `bin/health-commander` removed
- [x] **Clarify `AUTH_SECRET`** — env var removed entirely; README
      references cleaned up
- [x] **Frontend filter-emoji cleanup** — `initFilterEmoji()` reviewed;
      filter state handled via a `Set`
- [x] **Input validation philosophy — warn, don't block:**
  - [x] Server constraints widened to accept edge cases
  - [x] `Type` mismatch handling (reject strings for numeric fields)
  - [x] Reject future timestamps
- [x] **Security hardening (partial):**
  - [x] Security headers (CSP, X-Content-Type-Options, etc.)
  - [ ] Rate limiting on API endpoints
  - [x] Ensure `APP_ENV=prod` disables debug output (Dockerfile sets
        `ENV APP_ENV=prod`, entrypoint warms prod cache)
- [x] **Error response consistency** — standardised error payloads; no
      internal exception leakage in 500s
- [x] **Comprehensive test coverage** — edge cases, invalid input
      types, SystemController tests, pagination

### Phase 2 — API Completeness ✅ (mostly complete)

**Goal:** Round out the API to support full CRUD and expose the stats
endpoint.

- [x] `GET /api/v1/logs/{id}` — fetch a single log entry by ID
- [x] `PUT /api/v1/logs/{id}` — update an existing log entry
- [x] `DELETE /api/v1/logs/{id}` — delete a log entry
- [x] `GET /api/v1/logs/stats` — avg/min/max/count over a date range
- [ ] `GET /api/v1/logs/latest` — most recent entry, optionally
      filtered by `field` (`weight`, `systolic`, `heart_rate`). Needed
      for MCP stale-data reminders.
- [x] Pagination on `GET /api/v1/logs` (`page`, `limit`, wrapped
      response with `meta`)
- [x] Tests for all new endpoints
- [x] Frontend handles paginated response

### Phase 3 — Docker & Publishing (in progress)

**Goal:** Get VitalPulse containerised and published publicly.

- [x] **Production Dockerfile rewrite** — clean multi-stage FrankenPHP
      build (no more custom symfony-runtime binary download)
  - [x] Composer install in build stage
  - [x] SQLite by default
  - [x] Migrations run on container startup
  - [x] `APP_ENV=prod` in the image
  - [x] Non-root `USER` in the final image (`USER nobody:nogroup`)
  - [x] Healthcheck that verifies DB connectivity (`/api/health`
        endpoint runs a `SELECT 1` against SQLite)
- [x] **compose.yaml cleanup** — defaults to SQLite (single service),
      secrets via `.env`/environment; external `public` network removed
      (belongs in local production override only)
- [ ] **Docker Hub publishing:**
  - [ ] Build and tag `docker.io/<user>/vital-pulse:latest`
  - [ ] Version tags (`:v1.0.0`, `:1.0`, `:1`)
  - [ ] GitHub Actions workflow for automated builds on tag push
  - [ ] Multi-arch builds (amd64 + arm64 for Raspberry Pi)
- [ ] **GitHub repository:**
  - [x] Git repo initialised with comprehensive README
  - [x] LICENSE (MIT), CONTRIBUTING.md, CODE_OF_CONDUCT.md,
        CHANGELOG.md
  - [ ] Branch protection on `main`
  - [x] CI runs tests + cs-fixer on push/PR

### Phase 4 — Polish & v1.0 Release (in progress)

**Goal:** Production-ready v1.0 with a polished frontend.

- [x] **Improved data entry UX — logic-based auto-advance:**
  - [x] Systolic/diastolic/HR auto-advance by logical value boundaries
        (first digit 3–9 → 2-digit, 0–2 → 3-digit)
  - [x] Weight has no auto-advance (range too wide)
  - [x] Enter/Tab advances; Backspace on empty field moves back
  - [x] Gentle client-side validation warnings for
        abnormal-but-possible values
  - [x] Mobile/responsive audit
- [x] **Other frontend improvements:**
  - [x] PWA manifest validated
  - [x] “Latest reading” summary card
  - [x] Trend indicators (↑/↓ with delta vs last reading)
  - [x] Date range presets (7/30/90 days, 1 year, all time)
  - [x] CSV export endpoint + button
- [x] **Dashboard stats integration** — server-side stats endpoint +
      trend display
- [ ] **Documentation:**
  - [x] API reference + deployment guide in README
  - [x] Backfill CHANGELOG entries for v1.4.0–v1.5.1
  - [x] Keep-a-Changelog format applied to Unreleased section
- [ ] **Release process:**
  - [ ] Tag `v1.0.0`
  - [ ] GitHub release with release notes and screenshots
  - [ ] Docker image published to Docker Hub
  - [ ] Announcement

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

1. **`GET /api/v1/logs/latest`** (Phase 2, outstanding) — most recent
   entry, optionally filtered by field. Blocks the stale-data reminder
   workflow.
2. **`GET /api/v1/logs/stats`** ✅ done.

### MCP Server Endpoints

| Method | MCP Endpoint               | Maps to VitalPulse              |
|--------|----------------------------|---------------------------------|
| GET    | `/vital-pulse/logs`        | `GET /api/v1/logs`              |
| GET    | `/vital-pulse/logs/latest` | `GET /api/v1/logs/latest` (new) |
| GET    | `/vital-pulse/logs/{id}`   | `GET /api/v1/logs/{id}`         |
| GET    | `/vital-pulse/logs/stats`  | `GET /api/v1/logs/stats`        |
| POST   | `/vital-pulse/logs`        | `POST /api/v1/logs`             |

> **Note:** POST is proxied for convenience, but the primary use case
> is read-only reminders and trend analysis. The VitalPulse dashboard
> UI remains the primary logging interface.

### Workflow: Stale Data Reminder

1. Call `GET /vital-pulse/logs/latest?field=weight` — get the most
   recent weight entry.
2. If the entry is > 3 days old, include in morning summary.
3. Repeat for blood pressure (`?field=systolic`) and heart rate.

### Workflow: Trend Analysis

1. Call `GET /vital-pulse/logs/stats` for this month and last month.
2. Compare averages (weight, systolic, HR) for the summary.

---

## Architecture Notes

### Current Tech Stack

```
┌─────────────────────────────────────────────┐
│  Browser (HTML + Chart.js v4 + Luxon)       │
│  ├── Dashboard (3 line charts + stats)      │
│  └── Log entry form (BP, HR, weight, emoji) │
├─────────────────────────────────────────────┤
│  Symfony 8.1 (PHP 8.4+)                     │
│  ├── HealthApiController (CRUD + stats +    │
│  │   export, paginated)                     │
│  ├── SystemController (/api/about,          │
│  │   /api/health)                           │
│  ├── PublicAssetController (static files)   │
│  ├── ApiKeySubscriber (auth middleware)     │
│  ├── SecurityHeadersSubscriber              │
│  ├── Doctrine ORM + Migrations              │
│  └── HealthLogRepository                    │
│      ├── findByDateRange() (paginated)      │
│      └── getStatsForDateRange()             │
├─────────────────────────────────────────────┤
│  SQLite (var/data/health_tracker.db)        │
└─────────────────────────────────────────────┘
```

### Deployment

1. **Caddy reverse proxy** (`Caddyfile`) — serves static frontend
   files, proxies `/api/*`.
2. **Docker Compose** — FrankenPHP app container with SQLite, defaults
   to `APP_ENV=prod`, migrations at startup.

Legacy `deploy.sh` and `run.sh` are archived under `docs/legacy/`.

### Relationship to Other Projects

| Project          | Integration                                     |
|------------------|-------------------------------------------------|
| MCP server       | Proxy endpoints for reminders + trend analysis |
| Email integration| Vitals in morning summary                      |
| Discord/ntfy     | Stale-data reminders delivered via Discord     |

---

*Built by Lyra. ✨*

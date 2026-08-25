# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Final polish pass ahead of the public v1.0 release.

### Added
- Full CRUD API: `GET`/`PUT`/`DELETE /api/v1/logs/{id}` endpoints
- `GET /api/v1/logs/stats` endpoint exposing server-side aggregate
  statistics (avg/min/max/count) over a date range
- `GET /api/v1/logs/export` CSV export endpoint and dashboard button
- Pagination on `GET /api/v1/logs` (`page`/`limit` parameters,
  `{"data", "meta"}` response envelope)
- Security headers (CSP, X-Content-Type-Options, etc.) via a response
  subscriber
- Emoji validation enforced with a regex pattern
- Dashboard: “Latest Reading” summary card with reading-to-reading
  trend indicators
- Dashboard: date range preset buttons (7/30/90 days, 1 year, all
  time) for the chart filters
- Dashboard: soft client-side validation warnings for
  abnormal-but-possible vital readings
- Exact version tracking for vendored JS libraries
- Comprehensive `SystemController` tests, edge-case tests for invalid
  input types, and test coverage for the new endpoints

### Fixed
- High-priority pre-release review items: validation groups bug (range
  constraints previously never executed), internal exception messages
  leaked in 500 responses, duplicated serialization in the controller,
  ad-hoc validator instantiation
- Untracked `.env.dev` which contained a real `APP_SECRET`
- Removed API key acceptance via query parameter (was logged by
  proxies)
- HTML input min/max attributes widened to match the entity `@Range`
  constraints
- `FALLBACK_VERSION` in `SystemController`; extracted shared
  `SchemaSetupTrait` for tests
- Responsive/mobile layout audit; PWA manifest validated
- Stale/incorrect README information

### Changed
- All PHP files now declare `strict_types=1`
- Removed unused Symfony dependencies
- CI caches Composer dependencies and authenticates to GitHub

---

## [1.5.1] - 2026-08-13

### Added
- Dev server management script with self-bootstrapping dependency
  checks

---

## [1.5.0] - 2026-08-11

### Changed
- Switched from ad-hoc `schema:create` to Doctrine migrations for
  schema management (initial migration `Version20260811130655`)

---

## [1.4.2] - 2026-08-11

### Fixed
- Docker volume: mounting the cache directory caused issues — only
  `var/data` is mounted now

---

## [1.4.1] - 2026-08-11

### Fixed
- Public catch-all asset route was shadowing `/api/about` and
  `/api/health`

---

## [1.4.0] - 2026-08-11

### Added
- `GET /api/about` and `GET /api/health` system endpoints
- php-cs-fixer as a dev dependency with PSR-12 configuration, and a
  cs-fixer check in the CI pipeline

### Fixed
- Version detection in Docker and stale documentation
- Docker healthcheck now points at `/api/health` (was hitting root,
  which serves static HTML regardless of DB state)

### Changed
- PSR-12 cs-fixer fixes applied across the codebase

---

## [1.3.0] - 2026-08-10

### Added
- FrankenPHP worker mode Docker setup (multi-stage build)

### Changed
- PHP 8.4 is now required

---

## [1.2.1] - 2026-08-10

### Added
- Gitea Actions CI workflow running the PHPUnit test suite

### Changed
- CI switched to PHP 8.4

---

## [1.2.0] - 2026-08-10

### Added
- Public assets controller for serving static files
- Open-source project files: LICENSE (MIT), CONTRIBUTING.md,
  CODE_OF_CONDUCT.md, CHANGELOG.md
- Project roadmap document

### Fixed
- Boot failure ahead of Docker work; Symfony components aligned to
  8.1; asset serving unified

### Changed
- Removed legacy cruft and obsolete files
- README and ROADMAP synced with the current project state

---

## [1.1.2] - 2025-08-05

### Fixed
- Minor style fixes

---

## [1.1.1] - 2025-08-05

### Changed
- Emojis in the mood filter list are now enabled by default

---

## [1.1.0] - 2025-07-29

### Added
- Backend support for multiple emoji filtering via repeated `emoji` query parameters
- Frontend multi-select mood filter for chart results
- Screen-based process management script (`run.sh`) for long-running deployments

### Changed
- Chart is now a proper timeline with emoji and date selector in the tooltip

---

## [1.0.0] - 2025-07-28

### Added
- Initial release of VitalPulse
- REST API for logging and retrieving health vitals (`POST`/`GET /api/v1/logs`)
- API-key authentication
- Blood pressure (systolic/diastolic), heart rate, weight, and mood emoji tracking
- Symfony 7.x / Doctrine ORM / SQLite backend
- Vanilla HTML + Chart.js v4 dashboard with Luxon time axis
- Input validation with Symfony Validator
- Deployment script (`deploy.sh`) for Caddy static file serving
- Project logo and favicons
- PHPUnit test suite (37 tests)

---

[Unreleased]: https://github.com/your-user/vital-pulse/compare/v1.5.1...HEAD
[1.5.1]: https://github.com/your-user/vital-pulse/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/your-user/vital-pulse/compare/v1.4.2...v1.5.0
[1.4.2]: https://github.com/your-user/vital-pulse/compare/v1.4.1...v1.4.2
[1.4.1]: https://github.com/your-user/vital-pulse/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/your-user/vital-pulse/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/your-user/vital-pulse/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/your-user/vital-pulse/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/your-user/vital-pulse/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/your-user/vital-pulse/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/your-user/vital-pulse/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/your-user/vital-pulse/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/your-user/vital-pulse/releases/tag/v1.0.0

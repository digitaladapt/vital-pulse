# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Remove legacy cruft and obsolete files

### Added
- Tentatively working Docker setup
- Project roadmap document

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

[Unreleased]: https://github.com/your-user/vital-pulse/compare/v1.1.2...HEAD
[1.1.2]: https://github.com/your-user/vital-pulse/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/your-user/vital-pulse/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/your-user/vital-pulse/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/your-user/vital-pulse/releases/tag/v1.0.0

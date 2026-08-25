# VitalPulse

> A lightweight personal health vitals tracker for blood pressure, heart rate, weight, and mood — built with Symfony, PHP, SQLite, and a vanilla HTML + Chart.js dashboard.

---

## Features

- **Blood pressure tracking** — systolic & diastolic with paired validation
- **Heart rate logging** — resting or active BPM
- **Weight tracking** — kg or lb, your call
- **Mood emoji** — quick subjective wellness check-in from a 10-emoji set
- **Timeline charts** — three Chart.js v4 line graphs (blood pressure, heart rate, weight) with a Luxon time axis
- **Stats bar** — live averages for systolic, diastolic, heart rate, and weight
- **Filters** — date range picker plus multi-select emoji filter
- **REST API** — simple JSON endpoints with API-key authentication
- **SQLite storage** — zero-config, single-file database
- **Dashboard** — single-page vanilla HTML/JS frontend, no build step; API key stored in browser `localStorage`

<!-- screenshot placeholder -->
<!-- ![Dashboard screenshot](docs/screenshot.png) -->

---

## Quick Start

### Docker

```bash
# Clone the repo
git clone https://github.com/digitaladapt/vital-pulse.git
cd vital-pulse

# Copy the environment template and set your API key
cp .env.example host.env
# Edit host.env: set API_KEY to a strong secret

# Build and run
docker compose up -d
```

The API and dashboard are both available at `http://localhost:8080` (or whatever `VITALPULSE_PORT` is set to in `host.env`). The container listens on port 80 internally — `compose.yaml` maps it to the host port defined by `VITALPULSE_PORT` (default: 8080).

### Manual

```bash
# Prerequisites: PHP 8.4+, Composer, SQLite3

git clone https://github.com/digitaladapt/vital-pulse.git
cd vital-pulse
composer install

# Configure environment
cp .env.example .env.local
# Edit .env.local: set API_KEY and APP_SECRET to strong random values

# Create the database directory
mkdir -p var/data

# Start the development server — serves BOTH the API and the dashboard
php -S 0.0.0.0:8080 -t public/
```

---

## API Reference

All endpoints are prefixed with `/api/v1` and require an API key sent via the `X-API-Key` header.

| Method   | Endpoint              | Description                            | Query Params                                   |
|----------|-----------------------|----------------------------------------|------------------------------------------------|
| `POST`   | `/api/v1/logs`        | Create a new health log entry          | —                                              |
| `GET`    | `/api/v1/logs`        | List log entries with optional filter  | `from`, `to`, `emoji`, `page`, `limit`         |
| `GET`    | `/api/v1/logs/{id}`   | Get a single log entry by ID           | —                                              |
| `PUT`    | `/api/v1/logs/{id}`   | Update a log entry                     | —                                              |
| `DELETE` | `/api/v1/logs/{id}`   | Delete a log entry                     | —                                              |
| `GET`    | `/api/v1/logs/export` | Export logs as CSV                     | `from`, `to`, `emoji`                          |
| `GET`    | `/api/v1/logs/stats`  | Get aggregate statistics               | `from`, `to`                                   |

### POST `/api/v1/logs`

**Headers:**

```
Content-Type: application/json
X-API-Key: your_api_key
```

**Request body:**

```json
{
  "timestamp": "2025-07-28T10:00:00Z",
  "systolic": 120,
  "diastolic": 80,
  "heart_rate": 65,
  "weight": 72.5,
  "emoji": "🙂"
}
```

At least one measurement field (`systolic`, `diastolic`, `heart_rate`, or `weight`) is required. If providing blood pressure, both `systolic` and `diastolic` must be set together. The `timestamp` defaults to now (UTC) if omitted, and `emoji` defaults to 😐.

Results are always returned sorted by timestamp descending (newest first).

**Response — `201 Created`:**

```json
{
  "id": 1,
  "timestamp": "2025-07-28T10:00:00+00:00",
  "systolic": 120,
  "diastolic": 80,
  "heart_rate": 65,
  "weight": 72.5,
  "emoji": "🙂"
}
```

### GET `/api/v1/logs`

**Query parameters:**

| Param   | Type   | Format              | Description                          |
|---------|--------|---------------------|--------------------------------------|
| `from`  | string | `YYYY-MM-DD` or ISO 8601 | Filter entries on or after this date |
| `to`    | string | `YYYY-MM-DD` or ISO 8601 | Filter entries on or before this date |
| `emoji` | string | emoji character(s)  | Filter by mood emoji (can repeat for multi-select) |

**Example:**

```bash
curl -H "X-API-Key: your_api_key" \
  "http://localhost:8080/api/v1/logs?from=2025-07-01&to=2025-07-31&emoji=🙂&emoji=😐"
```

**Response — `200 OK`:**

```json
{
  "data": [
    {
      "id": 1,
      "timestamp": "2025-07-28T10:00:00+00:00",
      "systolic": 120,
      "diastolic": 80,
      "heart_rate": 65,
      "weight": 72.5,
      "emoji": "🙂"
    }
  ],
  "meta": {
    "page": 1,
    "limit": 50,
    "total": 1,
    "pages": 1
  }
}
```

---

## Configuration

Environment variables are loaded from the committed `.env` defaults plus a git-ignored override (`.env.local` locally, or `host.env` for Docker). See `.env.example` for the template. Never commit real secrets.

| Variable          | Default                                                          | Description                                     |
|-------------------|------------------------------------------------------------------|-------------------------------------------------|
| `DATABASE_URL`    | `sqlite:///%kernel.project_dir%/var/data/health_tracker.db`      | Doctrine database connection URL (SQLite default)|
| `API_KEY`         | `change_me_generate_with_openssl_rand_hex_32`              | API key required for `/api/v1/*` endpoints       |
| `APP_ENV`         | `prod`                                                            | Symfony environment (`dev`, `prod`, `test`)      |
| `APP_SECRET`      | *(generated)*                                                    | Symfony secret key for hashes/tokens             |
| `VITALPULSE_PORT` | `8080`                                                           | Host port mapped to the container's port 80 (Docker only) |
| `APP_VERSION`     | `dev`                                                            | Version baked into the Docker image at build time (pass `--build-arg APP_VERSION=v1.3.0` or set in `host.env`) |

> **Deployment model:** VitalPulse is designed for LAN/VPN deployment with a single shared API key. There is no user account system — the API key is the only authentication. For internet-facing deployments, put it behind a reverse proxy with additional access controls.

---

## Data Model

The `HealthLog` entity stores a single health check-in entry.

| Field         | Type                  | Nullable | Default | Validation                          |
|---------------|-----------------------|----------|---------|-------------------------------------|
| `id`          | `integer` (auto-increment) | No       | —       | Primary key                         |
| `timestamp`   | `datetime_immutable`  | No       | `now()` | UTC, defaults to current time       |
| `systolic`    | `integer`             | Yes      | `null`  | Range 60–250, positive              |
| `diastolic`   | `integer`             | Yes      | `null`  | Range 40–150, positive              |
| `heart_rate`  | `integer`             | Yes      | `null`  | Range 30–250, positive              |
| `weight`      | `float`               | Yes      | `null`  | Range 30–400, positive              |
| `emoji`       | `string` (length 10)  | No       | `😐`    | Any emoji; defaults to 😐. Frontend uses: 🤩 😀 🙂 😐 ☹️ 😩 🥵 😵‍💫 🤢 🥶 |

> **Note:** At least one measurement field must be provided. If systolic or diastolic is provided, the other must also be provided.

---

## Development

### Prerequisites

- PHP 8.4+
- Composer
- SQLite3

### Setup

```bash
git clone https://github.com/digitaladapt/vital-pulse.git
cd vital-pulse
composer install
```

### Running Tests

The project uses PHPUnit 10 with 129 tests. The test environment uses a file-based SQLite database (`var/data/test.db`).

```bash
# Run the full test suite
php vendor/bin/phpunit

# Run with verbose output
php vendor/bin/phpunit --testdox
```

Test configuration is in `phpunit.dist.xml`. The test environment is defined in `.env.test`.

### Starting the Dev Server

```bash
# Start PHP's built-in server
php -S 0.0.0.0:8080 -t public/
```

Or use the legacy screen script (moved to `docs/legacy/run.sh`):

```bash
./docs/legacy/run.sh
```

This starts the PHP server in a detached `screen` session named `vital-pulse` on port 9000.

---

## Deployment

### Serving model

A single PHP process serves the **entire** application — the static dashboard and the Symfony API alike. The front controller is `public/index.php`; static assets in `public/` are served directly by the web server. There is no separate build or copy step.

### Caddy / FrankenPHP

The included `Caddyfile` reverse-proxies everything to the PHP process (FrankenPHP in Docker). Static assets and API responses both come from the app container:

```caddyfile
vitals.example.com {
    reverse_proxy vital-pulse
}
```

### Docker

The app runs in Docker using FrankenPHP. Configure environment variables via a git-ignored `host.env` (copied next to `compose.yaml`):

```bash
cp .env.example host.env
# Edit host.env: set API_KEY and APP_SECRET to strong random values
docker compose up -d
```

### run.sh / deploy.sh (legacy manual helpers)

`run.sh` and `deploy.sh` are deprecated scripts from the old split deployment. They have been moved to `docs/legacy/`. Do not use them for new deployments — use Docker instead.

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## Acknowledgements

- [**Symfony**](https://symfony.com/) — PHP framework and HTTP foundation
- [**Doctrine ORM**](https://www.doctrine-project.org/) — database abstraction and persistence
- **[Chart.js v4.5.1](https://www.chartjs.org/)** — flexible JavaScript charting (vendored in `public/chartjs-v4.js`)
- **[Luxon v3.7.2](https://moment.github.io/luxon/)** — modern date/time library for the Chart.js time axis (vendored in `public/luxon-v3.js`)
- **[chartjs-adapter-luxon v1.3.1](https://github.com/chartjs/chartjs-adapter-luxon)** — adapter bridging Chart.js and Luxon (vendored in `public/chartjs-adapter-luxon-v1.js`)

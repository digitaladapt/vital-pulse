# VitalPulse

> A lightweight personal health vitals tracker for blood pressure, heart rate, weight, and mood — built with Symfony, PHP, SQLite, and a vanilla HTML + Chart.js dashboard.

---

## Features

- **Blood pressure tracking** — systolic & diastolic with paired validation
- **Heart rate logging** — resting or active BPM
- **Weight tracking** — kg or lb, your call
- **Mood emoji** — quick subjective wellness check-in
- **Timeline charts** — Chart.js v4 with a Luxon time axis
- **REST API** — simple JSON endpoints with API-key authentication
- **SQLite storage** — zero-config, single-file database
- **Dashboard** — single-page vanilla HTML/JS frontend, no build step

<!-- screenshot placeholder -->
<!-- ![Dashboard screenshot](docs/screenshot.png) -->

---

## Quick Start

### Docker

```bash
# Clone the repo
git clone https://github.com/your-user/vital-pulse.git
cd vital-pulse

# Copy the environment template and set your API key
cp host.env.example host.env
# Edit host.env: set API_KEY to a strong secret

# Build and run
docker compose up -d
```

The API will be available at `http://localhost:9000` and the dashboard at `http://localhost:9000/index.html`.

### Manual

```bash
# Prerequisites: PHP 8.3+, Composer, SQLite3

git clone https://github.com/your-user/vital-pulse.git
cd vital-pulse
composer install

# Configure environment
cp host.env.example .env
# Edit .env: set API_KEY and APP_SECRET

# Create the database directory
mkdir -p var/data

# Start the development server
php -S 0.0.0.0:9000 -t public/
```

---

## API Reference

All endpoints are prefixed with `/api/v1` and require the `X-API-Key` header.

| Method | Endpoint          | Description                          | Query Params                                   |
|--------|-------------------|--------------------------------------|------------------------------------------------|
| `POST` | `/api/v1/logs`    | Create a new health log entry        | —                                              |
| `GET`  | `/api/v1/logs`    | List log entries with optional filter| `from`, `to`, `emoji`                          |

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
  "http://localhost:9000/api/v1/logs?from=2025-07-01&to=2025-07-31&emoji=🙂&emoji=😐"
```

**Response — `200 OK`:**

```json
[
  {
    "id": 1,
    "timestamp": "2025-07-28T10:00:00+00:00",
    "systolic": 120,
    "diastolic": 80,
    "heart_rate": 65,
    "weight": 72.5,
    "emoji": "🙂"
  }
]
```

---

## Configuration

Environment variables are loaded from `.env` (or `host.env` in production). See `host.env.example` for a template.

| Variable        | Default                                                          | Description                                     |
|-----------------|------------------------------------------------------------------|-------------------------------------------------|
| `DATABASE_URL`  | `sqlite:///%kernel.project_dir%/var/data/health_tracker.db`      | Doctrine database connection URL (SQLite default)|
| `API_KEY`       | `change_me_to_a_strong_secret_key_1234567890abcdef`              | API key required for `/api/v1/*` endpoints       |
| `APP_ENV`       | `dev`                                                            | Symfony environment (`dev`, `prod`, `test`)      |
| `APP_SECRET`    | *(generated)*                                                    | Symfony secret key for hashes/tokens             |

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
| `emoji`       | `string` (length 10)  | No       | `😐`    | Any emoji, defaults to neutral      |

> **Note:** At least one measurement field must be provided. If systolic or diastolic is provided, the other must also be provided.

---

## Development

### Prerequisites

- PHP 8.3+
- Composer
- SQLite3

### Setup

```bash
git clone https://github.com/your-user/vital-pulse.git
cd vital-pulse
composer install
```

### Running Tests

The project uses PHPUnit 10 with 37 tests. The test environment uses an in-memory SQLite database.

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
php -S 0.0.0.0:9000 -t public/
```

Or use the included screen script (for long-running deployments):

```bash
./run.sh
```

This starts the PHP server in a detached `screen` session named `vital-pulse` on port 9000.

---

## Deployment

### Caddy Reverse Proxy

The included `Caddyfile` shows a production setup where Caddy serves the static dashboard files directly and reverse-proxies API requests to the PHP backend:

```caddyfile
vitals.example.com {
    root /data/vitals
    reverse_proxy /api/* dockerhost:9000
    file_server
}
```

The `deploy.sh` script copies the `public/` directory to the Caddy data directory:

```bash
./deploy.sh
```

### run.sh (Screen)

For simple deployments without a full web server, `run.sh` starts the PHP built-in server inside a `screen` session:

```bash
./run.sh
# Check status
screen -list
# Attach to the session
screen -r vital-pulse
```

### Docker

A Docker setup is available for containerized deployments. Configure environment variables via `host.env` (copied into the container as `.env`).

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## Acknowledgements

- [**Symfony**](https://symfony.com/) — PHP framework and HTTP foundation
- [**Doctrine ORM**](https://www.doctrine-project.org/) — database abstraction and persistence
- [**Chart.js v4**](https://www.chartjs.org/) — flexible JavaScript charting
- [**Luxon**](https://moment.github.io/luxon/) — modern date/time library for the Chart.js time axis

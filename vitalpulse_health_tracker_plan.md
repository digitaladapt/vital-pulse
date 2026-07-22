# VitalPulse Health Tracker — Project Plan

## 🎯 Overview
A personal health logging API + dashboard for tracking:
- Blood Pressure (systolic, diastolic)
- Heart Rate
- Body Weight
- Mood/Feeling (emoji selector)

Built with PHP/Symfony/FrankenPHP + SQLite. LAN/VPN-deployed only. No external security requirements beyond a simple browser-local API key.

---

## 📊 Data Model

### Log Entry Schema
```jsonc
{
  "id": "auto-generated UUID",
  "timestamp": "2025-01-14T20:30:00Z",      // defaults to current UTC time
  "systolic": null | int,                     // required if any measurement present
  "diastolic": null | int,                    // required if systolic provided
  "heart_rate": null | int,                   // optional
  "weight": null | float,                     // optional (may not be logged at same time)
  "emoji": "😐",                              // defaults to 😐 neutral; 10 allowed values
  "created_at": "2025-01-14T20:30:00Z"        // for debugging/sorting
}
```

### Emoji Set (default if no emoji provided)
🤩 😀 🙂 😐 ☹️ 😩 🥵 😵‍💫 🤢 🥶

---

## 🔌 API Design

### Endpoints

| Method | Endpoint                  | Purpose                                     |
|--------|---------------------------|---------------------------------------------|
| POST   | `/api/v1/logs`            | Create a new health log entry               |
| GET    | `/api/v1/logs?from=&to=...&emoji=` | Query logs with date range + emoji filter |

### Authentication
- Browser stores API key in `localStorage.getItem('vitalpulse_api_key')`
- Server validates on each request via custom Symfony listener/middleware

### Request Examples

**POST /api/v1/logs**
```jsonc
{
  "systolic": 128,
  "diastolic": 84,
  "heart_rate": 76,
  "weight": 185.4,
  "emoji": "🙂"
}
// or minimal: { "systolic": 130 }
```

**GET /api/v1/logs?from=2025-01-01&to=2025-01-31&emoji=%F0%9F%98%A8**
Returns JSON array of matching logs sorted by timestamp descending.

---

## 📈 Reporting / Dashboard

### Charts (3 separate line graphs)
- **Blood Pressure**: Systolic & Diastolic lines overlaid (color-coded: red/blue)
- **Heart Rate**: Single line, green
- **Weight**: Single line, orange/dark yellow

Each chart supports:
- Date range picker (matching API filter params)
- Optional emoji filter dropdown
- Y-axis scaling auto-adjusted per metric

---

## 🛠️ Tech Stack & Structure

### Backend
- **Framework:** PHP 8.3+ + Symfony 7.x
- **Serving Engine:** FrankenPHP (fast, secure, minimal overhead)
- **ORM/DB Layer:** Doctrine ORM with SQLite driver
- **Validation:** Symfony Forms or Attribute-based validators (`@Assert\NotNull`, etc.)

### Frontend
- Plain HTML/CSS + Chart.js (lightweight, declarative, no build step needed)
- Single `index.html` serving both API docs and dashboard UI
- JS fetches `/api/v1/logs?from=&to=...` and renders 3 canvas charts

### CLI Helper
- Optional `vitalpulse-cli.php`: parses natural language to POST logs locally if network fails. Can be extended later with queueing/sync logic.

---

## 📁 Expected File Structure
```
/vitalpulse/
├── src/
│   └── Controller/HealthController.php
├── public/
│   ├── index.html           # Dashboard + API docs
│   ├── chart.js             # Chart rendering script
│   └── vite.config.js       # (optional, for dev)
├── config/packages/
│   ├── doctrine.yaml        # SQLite DB connection
│   └── security.yaml        # Custom listener for API key auth
├── database/
│   └── health_tracker.db    # Auto-created by Doctrine
├── vitalpulse-cli.php       # CLI logger helper
└── .env                     # DB path, API_KEY secret, etc.
```

---

## 🚀 Next Steps
1. Scaffold Symfony 7 project with FrankenPHP bundle
2. Configure SQLite via Doctrine
3. Create HealthLog entity & repository
4. Build `/api/v1/logs` endpoints with validation + auth
5. Write frontend HTML/JS dashboard (Chart.js integration)
6. Add CLI logger helper
7. Test end-to-end

---

## 🤔 Open Decisions (to confirm while building)
- Should the API return logs in descending timestamp order by default? Yes for dashboards (latest first).
- Do we want automatic aggregation endpoints (e.g., `/api/v1/stats/2025-01`)? Probably not now, but good to keep in mind.
- Should emoji be case-insensitive or strictly lowercase? We'll store exactly what's sent, validate against allowed set only.

*Notes for Lyra: Keep this doc updated as we implement features. Next phase = coding.* 🩺💻

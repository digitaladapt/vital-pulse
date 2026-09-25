# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| unreleased (v1 development) | ✅ |

## Reporting a Vulnerability

Report vulnerabilities privately to **security@digitaladapt.com** (or open a private
security advisory on the repository). Please include reproduction steps and affected
versions. You will receive an acknowledgement within 48 hours and a status update at
least weekly until resolution.

**Do not open a public issue for a suspected vulnerability.** vital-pulse stores
personal health data, so the impact of a weakness here is not abstract.

## Security model summary

vital-pulse is a single-user health-logging application. It is not a multi-tenant
service and it does not implement user accounts — access is controlled entirely by
API keys.

- **Access control is two API keys.** `API_KEY` grants full read/write access to
  every `/api/v1/*` endpoint and is required in the `X-API-Key` header.
  `READ_ONLY_API_KEY` is optional and restricted to GET/HEAD. Keys are compared with
  `hash_equals()` so a wrong key cannot be recovered by timing.
- **The dashboard is public; the data is not.** `/` serves the static dashboard
  shell and `/api/about` and `/health` are unauthenticated by design — the first is
  read by the dashboard itself, the second by the container liveness probe. Every
  endpoint that touches a reading requires a key.
- **Secrets are environment variables, injected at runtime, never baked into
  images** (GUIDING-LIGHT §8.12). `.env` is never committed — the tracked files are
  `.env.example` and `.env.test` — and `.dockerignore` excludes `.env` from the
  build context so a developer's local secrets cannot reach an image layer.
- **Security headers are set by the application, not left to the proxy**
  (`src/EventSubscriber/SecurityHeadersSubscriber.php`): `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` and a
  Content-Security-Policy. The deprecated `X-XSS-Protection` header is deliberately
  not sent. Being app-side, they survive a change of reverse proxy.
- **The container runs as a non-root user** (`nobody:nogroup`) and drops privileges
  via `USER` in the Dockerfile (GUIDING-LIGHT §6.4).
- **State lives in `var/`** — a single SQLite file plus cache and logs. It is a
  mounted volume, and it is the only thing that needs backing up.

## Scope

In scope: the application code in `src/`, the shipped `docker/Caddyfile`, the
`Dockerfile`, and anything that affects an authorization decision.

Out of scope: the host-side proxy configuration (see `docs/examples/Caddyfile`) and
the security of any service that consumes this API.

## Deployment note

There is one deployment detail worth stating plainly, because it is easy to get
wrong and it is a real exposure rather than a theoretical one:

> **`APP_SECRET` must be set to a real value.** Compose reference configs commonly
> write `${APP_SECRET:-}`, which boots the app with a silently empty application
> secret. `docs/examples/compose.yaml` uses `${APP_SECRET:?}` instead, so startup
> fails loudly and names the missing variable. Generate one with
> `openssl rand -hex 32`.

The same applies to `API_KEY`: if it is left at its placeholder value, anyone who
can reach the app can read and write every health record in it.

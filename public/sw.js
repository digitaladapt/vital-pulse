// sw.js — VitalPulse service worker.
//
// WHY THIS EXISTS
//   A manifest + icons + `display: standalone` + `start_url` is not enough for a
//   browser to offer installation; a service worker is the remaining piece
//   (GUIDING-LIGHT §3.3b). Before this file the app was *almost* installable.
//
// CACHING POLICY — deliberately narrow
//
//   The hard rule here is that a **health record is never served stale**, and a
//   **failed save is never reported as a success**. So:
//
//   - `/api/*` is NEVER cached. Not by accident, not "just for GETs", not with
//     a short TTL. A cached reading list is a lie about the user's own data,
//     and `/api/about` carries the deployment's warnings default.
//   - Only the static app shell is precached: the document, the dashboard
//     script and the vendored chart libraries. Those are content-stable and
//     safe to serve from cache.
//   - Navigation falls back to the cached document when offline, so an
//     installed app opens instead of showing the browser error page.
//
// WHAT THIS DOES NOT DO YET
//   Offline *capture* — queueing one reading while out of signal — is decision
//   §3.3d (depth 1, write-blocking). It is intentionally not implemented here.
//   Note in particular that **Background Sync must not be used**: iOS Safari
//   does not implement it, so a queue that relies on it would silently never
//   flush on an iPhone. When that work lands it flushes on `online`,
//   `visibilitychange` and a foreground retry loop.

const CACHE_NAME = 'vitalpulse-shell-v1';

// The static shell. Paths are absolute because the app is served from the
// origin root by PublicAssetController.
const SHELL_ASSETS = [
    '/',
    '/app.js',
    '/chartjs-v4.js',
    '/luxon-v3.js',
    '/chartjs-adapter-luxon-v1.js',
    '/site.webmanifest',
    '/favicon.svg',
];

self.addEventListener('install', (event) => {
    // addAll is atomic: if any shell asset 404s the whole install fails rather
    // than caching a half-built shell. That is the behaviour we want — a
    // broken install is visible, a partial cache is not.
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    // Drop previous shell versions so a deploy cannot leave a stale document
    // behind forever.
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only GET is cacheable in any sense; never interpose on writes.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Only same-origin requests. Anything else goes straight to the network.
    if (url.origin !== self.location.origin) {
        return;
    }

    // ── API: network only, always ──────────────────────────────────────────
    // Never cached. This is the data the user came for.
    if (url.pathname === '/api' || url.pathname.startsWith('/api/')) {
        return;
    }

    // ── Navigations: network first, cached shell as the offline fallback ───
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/'))
        );
        return;
    }

    // ── Static shell: cache first, then network ────────────────────────────
    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) {
                return cached;
            }

            return fetch(request).then((response) => {
                // Only cache a genuine, complete, same-origin success.
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }

                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));

                return response;
            });
        })
    );
});

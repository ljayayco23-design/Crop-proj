const CACHE_NAME = 'cropsense-offline-v6';
const LOGIN_URL = '/login';

// Only precache PUBLIC static assets here.
// Do not put auth-protected Laravel routes here.
const PRECACHE_ASSETS = [
    // AI Model Assets
    '/model/model.json',
    '/model/metadata.json',
    '/model/weights.bin',
    '/model/best.onnx',

    // External CSS
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',

    // Font Awesome icon files (Schedule page icons need these offline)
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-solid-900.woff2',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-regular-400.woff2',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/webfonts/fa-brands-400.woff2',

    // External JS & AI Models
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.21.0/dist/tf.min.js',
    'https://cdn.jsdelivr.net/npm/@teachablemachine/image@0.8.4/dist/teachablemachine-image.min.js'
];

// Third-party hosts whose files are static (safe to serve cache-first).
// Everything else cross-origin (e.g. Nominatim geocoding) always hits the network.
const CDN_HOSTS = [
    'cdn.jsdelivr.net',
    'cdnjs.cloudflare.com',
    'unpkg.com',
    'cdn.tailwindcss.com',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'api.maptiler.com'
];

// If an exact page isn't cached offline, fall back to that role's dashboard.
const ROLE_HOME = [
    ['/farmer/', '/farmer/dashboard'],
    ['/technician/', '/technician/dashboard'],
    ['/admin/', '/admin/dashboard']
];

const OFFLINE_HTML = `<!DOCTYPE html><html><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Offline</title></head>
<body style="font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:24px">
<div><h2 style="color:#10b981">Walang internet</h2>
<p>Hindi pa na-save ang page na ito para sa offline. Mag-login nang isang beses habang online.</p>
<p><a href="/login" style="color:#10b981">Pumunta sa Login</a></p></div></body></html>`;

// ==================== INSTALL EVENT ====================
self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            for (const asset of PRECACHE_ASSETS) {
                try {
                    await cache.add(asset);
                } catch (err) {
                    console.warn(`[SW Warning] Failed to precache: ${asset}`, err);
                }
            }
            await cacheLoginPage(cache);
        })
    );
});

// Always keep a REAL copy of the login page. We fetch it WITHOUT cookies so
// the server treats us as logged out and returns the form (200) instead of
// redirecting to the dashboard. This is what lets the app open on the login
// screen offline even if the person never pressed Logout.
async function cacheLoginPage(cache) {
    try {
        const res = await fetch(LOGIN_URL, { credentials: 'omit', cache: 'no-store' });
        if (res.status === 200 && !res.redirected) {
            await cache.put(LOGIN_URL, res);
        }
    } catch (err) {
        console.warn('[SW Warning] Could not cache login page', err);
    }
}

// ==================== ACTIVATE EVENT ====================
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        console.log('[SW] Deleting old cache version:', cache);
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// ==================== FETCH EVENT ====================
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Ignore POST/PUT/DELETE (Cache API can't store them anyway).
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    const sameOrigin = url.origin === self.location.origin;

    if (sameOrigin && url.pathname === '/sw.js') return;

    // Schedule data endpoints must always hit the network (offline copy lives in IndexedDB).
    if (sameOrigin && url.pathname.startsWith('/farmer/schedule/')) return;

    // Page navigations
    if (request.mode === 'navigate') {
        event.respondWith(handleNavigation(request, url));
        return;
    }

    // Static third-party files + AI model: cache first
    if (CDN_HOSTS.includes(url.hostname) || (sameOrigin && url.pathname.startsWith('/model/'))) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // Own static files (css/js/img/fonts): network first, cache as offline fallback
    if (sameOrigin && /\.(css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf)$/i.test(url.pathname)) {
        event.respondWith(networkFirst(request));
        return;
    }

    // EVERYTHING ELSE (API calls like /check-status, /chat/*, /session-check,
    // /locations/*, Nominatim ...) goes straight to the network. It used to be
    // cached forever, which served stale data and stale auth results.
});

async function handleNavigation(request, url) {
    try {
        const networkResponse = await fetch(request);
        // Only cache real pages. A redirect (e.g. /login -> dashboard while
        // logged in) comes back as an opaque redirect with status 0, so it is
        // never stored under the wrong URL.
        if (networkResponse.status === 200 && !networkResponse.redirected) {
            const copy = networkResponse.clone();
            caches.open(CACHE_NAME).then((c) => c.put(request, copy)).catch(() => {});
        }
        return networkResponse;
    } catch (err) {
        return offlineNavigationFallback(request, url);
    }
}

async function offlineNavigationFallback(request, url) {
    const opts = { ignoreVary: true };
    const path = url.pathname;

    // App entry points ALWAYS open the login screen offline, so the
    // offline-login form runs. No more jumping straight to the dashboard.
    if (path === '/' || path === '/login' || path === '/admin' || path === '/admin/login') {
        const login = await caches.match(LOGIN_URL, { ...opts, ignoreSearch: true });
        if (login) return login;
    }

    // Exact page (with, then without, the query string)
    let hit = await caches.match(request, opts);
    if (!hit) hit = await caches.match(request, { ...opts, ignoreSearch: true });
    if (hit) return hit;

    // Page was never cached: use that role's dashboard if we have it
    for (const [prefix, home] of ROLE_HOME) {
        if (path.startsWith(prefix)) {
            const dash = await caches.match(home, { ...opts, ignoreSearch: true });
            if (dash) return dash;
        }
    }

    // Last resorts: the login page, then a tiny built-in page.
    // Never return undefined (that is the browser's "site can't be reached").
    const login = await caches.match(LOGIN_URL, { ...opts, ignoreSearch: true });
    if (login) return login;

    return new Response(OFFLINE_HTML, {
        status: 503,
        headers: { 'Content-Type': 'text/html; charset=utf-8' }
    });
}

async function cacheFirst(request) {
    const cached = await caches.match(request, { ignoreVary: true });
    if (cached) return cached;
    try {
        const res = await fetch(request);
        if (res.ok || res.type === 'opaque') {
            const copy = res.clone();
            caches.open(CACHE_NAME).then((c) => c.put(request, copy)).catch(() => {});
        }
        return res;
    } catch (err) {
        return Response.error();
    }
}

async function networkFirst(request) {
    try {
        const res = await fetch(request);
        if (res.ok) {
            const copy = res.clone();
            caches.open(CACHE_NAME).then((c) => c.put(request, copy)).catch(() => {});
        }
        return res;
    } catch (err) {
        const cached = await caches.match(request, { ignoreVary: true });
        return cached || Response.error();
    }
}
const CACHE_NAME = 'cropsense-offline-v4'; 

// Only precache PUBLIC static assets here. 
// Do not put auth-protected Laravel routes here.
const PRECACHE_ASSETS = [
    // AI Model Assets
    '/model/model.json',
    '/model/metadata.json',
    '/weights.bin.json',

// External CSS
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
    
    // External JS & AI Models
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.21.0/dist/tf.min.js',
    'https://cdn.jsdelivr.net/npm/@teachablemachine/image@0.8.4/dist/teachablemachine-image.min.js'
];

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
        })
    );
});

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
    if (event.request.method !== 'GET') return;

    // 1. PAGE NAVIGATION STRATEGY (HTML pages)
    if (event.request.mode === 'navigate' || event.request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(
            fetch(event.request)
                .then((networkResponse) => {
                    // Dynamically cache visited pages when online
                    if (networkResponse && networkResponse.status === 200) {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseToCache));
                    }
                    return networkResponse;
                })
                .catch(() => {
                    // Added { ignoreSearch: true } to prevent URL parameters from breaking the cache match
                    return caches.match(event.request, { ignoreSearch: true }).then((cachedResponse) => {
                        if (cachedResponse) return cachedResponse;

                        // Fallback 1: Try to serve the dashboard if exact page isn't cached
                        return caches.match('/farmer/dashboard', { ignoreSearch: true }).then((dashboardResponse) => {
                            if (dashboardResponse) return dashboardResponse;

                            // Fallback 2: Ultimate Inline Fallback (Kills the Dino Game)
                            return new Response(`
                                <!DOCTYPE html>
                                <html lang="en">
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <title>Offline | CROPSENSE AI</title>
                                    <style>
                                        body { background-color: #0e1116; color: white; font-family: system-ui, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
                                        h2 { color: #10b981; margin-bottom: 10px; }
                                        p { color: #94a3b8; margin-bottom: 25px; max-width: 80%; }
                                        button { background-color: #10b981; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: bold; margin: 5px; }
                                    </style>
                                </head>
                                <body>
                                    <h2>🌾 CROPSENSE AI is Offline</h2>
                                    <p>You are currently offline, and this specific page hasn't been synced to your device yet.</p>
                                    <div>
                                        <button onclick="window.history.back()">Go Back</button>
                                        <button onclick="location.reload()" style="background-color: #3b82f6;">Try Again</button>
                                    </div>
                                </body>
                                </html>
                            `, {
                                status: 200,
                                headers: { 'Content-Type': 'text/html' }
                            });
                        });
                    });
                })
        );
        return;
    }

    // 2. STATIC ASSETS STRATEGY (CSS, JS, Images, CDNs)
    event.respondWith(
        caches.match(event.request, { ignoreSearch: true }).then((cachedResponse) => {
            if (cachedResponse) return cachedResponse;

            return fetch(event.request)
                .then((networkResponse) => {
                    if (networkResponse && (networkResponse.status === 200 || networkResponse.type === 'opaque')) {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseToCache));
                    }
                    return networkResponse;
                })
                .catch((err) => {
                    console.warn('[SW Offline] Could not retrieve asset:', event.request.url);
                });
        })
    );
});
const CACHE_NAME = 'cropsense-offline-v4'; 

// Only precache PUBLIC static assets here. 
// Do not put auth-protected Laravel routes here.
const PRECACHE_ASSETS = [
    // AI Model Assets
    '/model/model.json',
    '/model/metadata.json',
    '/model/weights.bin',

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
// ==================== FETCH EVENT ====================
self.addEventListener('fetch', (event) => {
    
    // 🛑 FIX: Ignore all non-GET requests (POST, PUT, DELETE)
    // This prevents the error: "Failed to execute 'put' on 'Cache': Request method 'POST' is unsupported"
    if (event.request.method !== 'GET') {
        return; 
    }

    // Check if the request is for an HTML page (Navigation)
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .then((networkResponse) => {
                    // ONLINE: Save a fresh copy of every page visited to cache
                    if (networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
                    }
                    return networkResponse;
                })
                .catch(async () => {
                    // OFFLINE: Try exact URL match
                    let cachedResponse = await caches.match(event.request);
                    
                    // Fallback: Try match ignoring search parameters
                    if (!cachedResponse) {
                        cachedResponse = await caches.match(event.request, { ignoreSearch: true });
                    }
                    
                    // Fallback: Try matching by path name directly
                    if (!cachedResponse) {
                        const url = new URL(event.request.url);
                        cachedResponse = await caches.match(url.pathname);
                    }

                    // Fallback: Default to Dashboard if detection page isn't cached yet
                    if (!cachedResponse) {
                        cachedResponse = await caches.match('/farmer/dashboard');
                    }

                    return cachedResponse;
                })
        );
    } else {
        // Assets (CSS, JS, Models) - Cache First, fallback to Network
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                return cachedResponse || fetch(event.request).then((fetchResponse) => {
                    return caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, fetchResponse.clone());
                        return fetchResponse;
                    });
                });
            })
        );
    }
});
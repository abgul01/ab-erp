const CACHE = 'ab-erp-v1';
const STATIC = '/build/assets/';
const API = '/api/';

self.addEventListener('install', (e) => {
    self.skipWaiting();
    e.waitUntil(
        caches.open(CACHE).then((cache) =>
            cache.addAll([
                '/',
                '/build/assets/app.js',
                '/build/assets/app.css',
            ]).catch(() => {
                // Build assets may not exist in dev; non-critical.
            }),
        ),
    );
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))),
        ),
    );
});

self.addEventListener('fetch', (e) => {
    const { request } = e;
    const url = new URL(request.url);

    // API calls go to network only; offline queue handles retries.
    if (url.pathname.startsWith(API)) return;

    // Static assets: cache-first for speed.
    if (url.pathname.startsWith(STATIC)) {
        e.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((res) => {
                const copy = res.clone();
                caches.open(CACHE).then((cache) => cache.put(request, copy));
                return res;
            })),
        );
        return;
    }

    // Navigation / App shell: network-first, fall back to cache.
    e.respondWith(
        fetch(request).then((res) => {
            const copy = res.clone();
            caches.open(CACHE).then((cache) => cache.put(request, copy));
            return res;
        }).catch(() => caches.match(request).then((cached) => cached || new Response('Offline', { status: 503 }))),
    );
});

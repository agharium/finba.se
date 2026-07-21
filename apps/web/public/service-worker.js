/* Finba.se PWA — online-first, never cache private financial data. */
const CACHE_VERSION = 'v1';
const STATIC_CACHE = `finba-static-${CACHE_VERSION}`;

const PRECACHE_URLS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/favicon.ico',
    '/favicon.svg',
    '/favicon-16x16.png',
    '/favicon-32x32.png',
    '/apple-touch-icon.png',
    '/safari-pinned-tab.svg',
    '/pwa/icon-96x96.png',
    '/pwa/icon-144x144.png',
    '/pwa/icon-192x192.png',
    '/pwa/icon-384x384.png',
    '/pwa/icon-512x512.png',
    '/pwa/maskable-icon-192x192.png',
    '/pwa/maskable-icon-512x512.png',
];

const AUTH_PATH_HINTS = [
    '/login',
    '/register',
    '/password-reset',
    '/email-verification',
    '/logout',
    '/auth/',
    '/livewire/',
    '/sanctum/',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE_URLS)),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();

        await Promise.all(
            keys
                .filter((key) => key.startsWith('finba-') && key !== STATIC_CACHE)
                .map((key) => caches.delete(key)),
        );

        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (isSensitivePath(url.pathname) || request.headers.get('accept')?.includes('application/json')) {
        return;
    }

    if (request.mode === 'navigate' || request.destination === 'document') {
        event.respondWith(networkFirstNavigation(request));
        return;
    }

    if (isPrecachedAsset(url.pathname)) {
        event.respondWith(cacheFirstStatic(request));
    }
});

function isSensitivePath(pathname) {
    return AUTH_PATH_HINTS.some((hint) => pathname.includes(hint));
}

function isPrecachedAsset(pathname) {
    return PRECACHE_URLS.includes(pathname);
}

async function networkFirstNavigation(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cache = await caches.open(STATIC_CACHE);
        const offline = await cache.match('/offline.html');

        return offline || new Response('Você está sem conexão.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        });
    }
}

async function cacheFirstStatic(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        cache.put(request, response.clone());
    }

    return response;
}

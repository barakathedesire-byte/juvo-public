/* Juvo service worker — installability + safe offline fallback.
 *
 * Strategy:
 *  - Navigations: network first, fall back to cached page, then offline page.
 *  - Static assets (css/js/fonts/icons/images): stale-while-revalidate.
 *  - Everything else (incl. POST and /chat, /api, admin) is never cached.
 *
 * Nothing authenticated is cached: only same-origin GET responses that are
 * not under the paths listed in NEVER_CACHE are considered.
 */

const VERSION = 'juvo-v1';
const STATIC_CACHE = `${VERSION}-static`;
const PAGE_CACHE = `${VERSION}-pages`;
const OFFLINE_URL = '/offline';

const NEVER_CACHE = [
    '/admin',
    '/vendor',
    '/customer',
    '/chat',
    '/api',
    '/checkout',
    '/cart',
    '/login',
    '/livewire',
    '/broadcasting',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) =>
            cache.addAll([
                '/offline',
                '/manifest.webmanifest',
                '/icons/icon-192.png',
                '/icons/icon-512.png',
            ]).catch(() => undefined)
        )
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => !key.startsWith(VERSION))
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

function isNeverCache(url) {
    return NEVER_CACHE.some((prefix) => url.pathname === prefix || url.pathname.startsWith(prefix + '/'));
}

function isStaticAsset(url) {
    return /\.(?:css|js|mjs|woff2?|ttf|otf|eot|png|jpe?g|gif|webp|svg|ico|avif)$/i.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin || isNeverCache(url)) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(PAGE_CACHE).then((cache) => cache.put(request, copy)).catch(() => undefined);

                    return response;
                })
                .catch(() =>
                    caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL))
                )
        );

        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const network = fetch(request)
                    .then((response) => {
                        if (response && response.status === 200) {
                            const copy = response.clone();
                            caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy)).catch(() => undefined);
                        }

                        return response;
                    })
                    .catch(() => cached);

                return cached || network;
            })
        );
    }
});

/**
 * Service worker.
 *
 * Two different problems, two different strategies:
 *
 *   - The shell (built CSS and JS) is content-hashed and immutable, so it is
 *     cached on first use and served from cache forever. A new deploy produces
 *     new filenames.
 *
 *   - Everything else is network-first. A review is a thing about *today*;
 *     serving yesterday's from a cache would be worse than an error page,
 *     because it would look right (FR-086, FR-087).
 *
 * Card actions are not handled here at all. review.js keeps its own
 * localStorage queue and replays it when the connection returns, which
 * survives the service worker being evicted.
 */

const VERSION = 'v1';
const SHELL_CACHE = `byagain-shell-${VERSION}`;
const PAGE_CACHE = `byagain-pages-${VERSION}`;

const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.addAll([OFFLINE_URL])),
    );

    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    // Drop caches from previous versions rather than letting them accumulate
    // on somebody's phone.
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== SHELL_CACHE && key !== PAGE_CACHE)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only GETs. A POST is an action, and replaying one from a cache would
    // mean acting on a card twice.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request, SHELL_CACHE));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirst(request, PAGE_CACHE));
    }
});

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);

    if (cached !== undefined) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(cacheName);
        cache.put(request, response.clone());
    }

    return response;
}

async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }

        return response;
    } catch {
        const cached = await caches.match(request);

        return cached ?? caches.match(OFFLINE_URL);
    }
}

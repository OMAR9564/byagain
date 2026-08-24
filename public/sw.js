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

// Bumped to v2 for the push listeners below. A service worker already
// installed on somebody's phone keeps running the script it was installed
// with, so without a version change their browser would never hear a push at
// all (R-208).
const VERSION = 'v2';
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

/**
 * A notification arrived.
 *
 * Every word shown here comes down in the payload, resolved on the server from
 * lang/en/ — this file holds no interface copy of its own, and translating it
 * twice is how the two would drift apart.
 *
 * The payload carries no passage content either: what shows on a lock screen
 * is that today's review is waiting, and nothing about what is in it (FR-148).
 */
self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data?.json() ?? {};
    } catch {
        // Unreadable payload, and still worth showing something: the reader
        // asked to be reminded, and silence would be the one wrong answer.
        payload = {};
    }

    const title = payload.title ?? 'byagain';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: payload.body,
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            // Fixed per day by the server, so a second notification replaces
            // the first on screen rather than stacking under it.
            tag: payload.tag,
            data: { url: payload.url ?? '/review' },
        }),
    );
});

/**
 * The notification was tapped.
 *
 * Focus an open byagain tab if there is one rather than opening a second: two
 * tabs of the same review is how a card gets decided twice.
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = event.notification.data?.url ?? '/review';

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                for (const client of clientList) {
                    if (new URL(client.url).origin === self.location.origin && 'focus' in client) {
                        return client.focus().then((focused) =>
                            'navigate' in focused ? focused.navigate(target) : focused,
                        );
                    }
                }

                return self.clients.openWindow(target);
            }),
    );
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

/**
 * Service worker.
 *
 * Two different problems, two different strategies:
 *
 *   - The shell (built CSS and JS) is content-hashed and immutable, so it is
 *     cached on first use and served from cache forever. A new deploy produces
 *     new filenames.
 *
 *   - Everything else is network-first with a timeout: the app tries to fetch
 *     the latest from the network, but falls back to the cache after ~4 seconds
 *     if the connection is slow or absent. A review is a thing about *today*:
 *     the cached review is checked against its X-Byagain-Expires header, and
 *     an expired review is never served offline — we show the offline page
 *     instead, which explains why and asks the reader to connect (FR-086,
 *     FR-087).
 *
 * Card actions are not handled here at all. The app keeps its own localStorage
 * queue and replays it when the connection returns, which survives the service
 * worker being evicted.
 *
 * The app can ask this worker to prefetch today's review in the background
 * with X-Byagain-Prefetch: 1, which marks the download without marking the
 * review as started, so offline use is possible without advancing the ritual.
 */

// Bumped to v3 for offline review expiry enforcement. A service worker already
// installed on somebody's phone keeps running the script it was installed
// with, so without a version change their browser would never hear the new
// logic (R-208, FR-086).
const VERSION = 'v3';
const SHELL_CACHE = `byagain-shell-${VERSION}`;
const PAGE_CACHE = `byagain-pages-${VERSION}`;

const OFFLINE_URL = '/offline.html';
const OFFLINE_REVIEW_URL = '/offline-review.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.addAll([OFFLINE_URL, OFFLINE_REVIEW_URL])),
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
        event.respondWith(networkFirst(request, PAGE_CACHE, (promise) => event.waitUntil(promise)));
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

/**
 * A client asked us to prefetch today's review.
 *
 * Download it with X-Byagain-Prefetch: 1 (which tells the server not to mark
 * it as started), and cache it along with its build assets for offline use
 * without advancing the ritual.
 *
 * Swallow errors silently: prefetch is best-effort.
 */
self.addEventListener('message', (event) => {
    if (event.data?.type === 'prefetch-review') {
        event.waitUntil(prefetchReview());
    }
});

async function prefetchReview() {
    try {
        const response = await fetch('/review', {
            credentials: 'same-origin',
            headers: { 'X-Byagain-Prefetch': '1' },
        });

        // Check if the request was redirected (e.g., to login). We only cache
        // a direct response from /review.
        if (!response.ok || response.redirected || !response.url.endsWith('/review')) {
            return;
        }

        // Cache the review page.
        const cache = await caches.open(PAGE_CACHE);
        cache.put('/review', response.clone());

        // Extract and cache build assets from the page.
        try {
            const html = await response.text();

            // Find all same-origin /build/… asset URLs in src and href attributes.
            const assetRegex = /(src|href)="(\/build\/[^"]+)"/g;
            const assets = new Set();
            let match;

            while ((match = assetRegex.exec(html)) !== null) {
                assets.add(match[2]);
            }

            // Fetch and cache each asset.
            const shellCache = await caches.open(SHELL_CACHE);

            for (const assetUrl of assets) {
                try {
                    // Skip if already cached.
                    const cached = await shellCache.match(assetUrl);
                    if (cached) {
                        continue;
                    }

                    const assetResponse = await fetch(assetUrl);
                    if (assetResponse.ok) {
                        shellCache.put(assetUrl, assetResponse);
                    }
                } catch {
                    // Swallow individual asset errors.
                }
            }
        } catch {
            // Swallow HTML parsing errors.
        }
    } catch {
        // Swallow all errors; prefetch is best-effort.
    }
}

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

/**
 * How long a cached page may be served ahead of a slow network, in ms.
 */
const NETWORK_TIMEOUT_MS = 4000;

/**
 * Network-first strategy for navigations.
 *
 * The fetch is started once. With a cached copy to fall back on, the network
 * races a timeout and the cache wins a slow connection; without one there is
 * nothing to fall back to, so the reader waits for the network — a slow but
 * working connection must never be told it is offline. Only a real network
 * failure ends at the offline page.
 *
 * A cached review past its X-Byagain-Expires is never served; the offline
 * review page explains why instead (FR-086, FR-087).
 *
 * An answer that arrives after the timeout is still stored (through
 * waitUntil, so the worker is not stopped first), so the next visit is fresh.
 *
 * @param {Request} request
 * @param {string} cacheName
 * @param {(promise: Promise<unknown>) => void} waitUntil
 * @return {Promise<Response>}
 */
async function networkFirst(request, cacheName, waitUntil) {
    const cached = await caches.match(request);

    let stored = Promise.resolve();

    const network = fetch(request).then((response) => {
        if (response.ok) {
            // Cloned here, before the response is handed to the page: after
            // that its body may already be in use. Stored whether or not the
            // page is still waiting for this answer.
            const copy = response.clone();

            stored = caches
                .open(cacheName)
                .then((cache) => cache.put(request, copy))
                .catch(() => {});
        }

        return response;
    });

    // Registered up front: waitUntil only works while the event is still
    // active, and a late answer arrives after respondWith has settled. This
    // keeps the worker alive until the fetch and its cache write are done.
    waitUntil(network.then(() => stored).catch(() => {}));

    if (cached === undefined) {
        try {
            return await network;
        } catch {
            return caches.match(OFFLINE_URL);
        }
    }

    const timedOut = Symbol('timeout');
    let timer;

    try {
        const winner = await Promise.race([
            network,
            new Promise((resolve) => {
                timer = setTimeout(() => resolve(timedOut), NETWORK_TIMEOUT_MS);
            }),
        ]);

        if (winner !== timedOut) {
            return winner;
        }
    } catch {
        // Network failed outright: fall through to the cache.
    } finally {
        clearTimeout(timer);
    }

    const expiresHeader = cached.headers.get('X-Byagain-Expires');

    if (expiresHeader && new Date(expiresHeader) <= new Date()) {
        return caches.match(OFFLINE_REVIEW_URL);
    }

    return cached;
}

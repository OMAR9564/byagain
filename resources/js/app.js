/**
 * The shell's own script. Vanilla on purpose: no framework reaches a
 * user-facing page (Constitution art. II), and the whole user bundle has a
 * 150KB budget to stay inside (SC-006).
 *
 * Everything here is progressive enhancement. With this file blocked the app
 * still works — the theme follows the OS and the offline banner never shows.
 */

import { flushQueue } from './queue.js';

const THEME_KEY = 'byagain.theme';

/**
 * Theme override. The inline script in the layout has already applied the
 * stored value before first paint; this only handles changing it.
 */
export function setTheme(theme) {
    if (theme === 'system') {
        localStorage.removeItem(THEME_KEY);
        delete document.documentElement.dataset.theme;
        return;
    }

    localStorage.setItem(THEME_KEY, theme);
    document.documentElement.dataset.theme = theme;
}

function bindThemeControls() {
    document.querySelectorAll('[data-theme-choice]').forEach((control) => {
        control.addEventListener('click', () => setTheme(control.dataset.themeChoice));
    });
}

/**
 * Connection banner. The review screen queues failed card actions locally, so
 * being offline is a notice rather than an error (FR-087, SC-016).
 */
function bindConnectionBanner() {
    const banner = document.getElementById('offline-banner');

    if (banner === null) {
        return;
    }

    const render = () => {
        banner.hidden = navigator.onLine;
    };

    window.addEventListener('online', render);
    window.addEventListener('offline', render);
    render();
}

function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // A failed registration costs offline support, nothing else.
            // Never surface it — there is nothing the reader can do about it.
        });
    });
}

bindThemeControls();
bindConnectionBanner();
registerServiceWorker();
flushQueueOnLoadAndOnline();
prefetchReviewIfOnline();

/**
 * Replay queued card actions on every page load and when the connection
 * returns (FR-042, FR-043, FR-086).
 *
 * The queue module guards against concurrent flushes, so this is safe to
 * call from both the page load and the online event.
 */
function flushQueueOnLoadAndOnline() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!csrf) {
        return;
    }

    // Flush on page load.
    flushQueue(csrf);

    // Flush when connection returns.
    window.addEventListener('online', () => {
        flushQueue(csrf);
    });
}

/**
 * Trigger a background prefetch of today's review.
 *
 * The app only lives on /review for a signed-in reader. Other pages (library,
 * mastery, settings) use the app layout, so we check if we are on /review
 * already. If not, and we are online, ask the service worker to fetch and
 * cache today's review in the background with the X-Byagain-Prefetch header,
 * so it does not mark the review as started.
 *
 * This ensures that when the reader goes offline, today's review is ready,
 * and a stale cached review is never served (FR-086, FR-087).
 */
function prefetchReviewIfOnline() {
    // Check if service worker is available.
    if (!('serviceWorker' in navigator)) {
        return;
    }

    // We only prefetch if we are not already on /review.
    if (window.location.pathname === '/review') {
        return;
    }

    // Only prefetch if we are online.
    if (!navigator.onLine) {
        return;
    }

    // Wait for the service worker to be ready, then send the prefetch message.
    window.addEventListener('load', () => {
        navigator.serviceWorker.ready.then((registration) => {
            if (registration.active) {
                registration.active.postMessage({ type: 'prefetch-review' });
            }
        });
    });
}

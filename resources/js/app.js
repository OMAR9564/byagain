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

const PREFETCH_KEY = 'byagain.review.prefetchedAt';

/**
 * How often a page load may ask for today's review to be downloaded, in ms.
 * Every page view used to cost a /review request that could build the
 * review; the copy only needs to be fresh enough to survive going offline,
 * so a few minutes is plenty.
 */
const PREFETCH_INTERVAL_MS = 10 * 60 * 1000;

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

/**
 * Ask for confirmation before submitting forms with data-confirm.
 *
 * The iOS app implements window.confirm natively, so this works on all
 * platforms.
 */
function bindConfirmationForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const message = form.dataset.confirm;

        if (!message) {
            return;
        }

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
}

/**
 * Navbar scroll tracking for iOS-style collapse behavior.
 *
 * If a large title exists, use IntersectionObserver to detect when it scrolls
 * out of view. Otherwise, toggle on any scroll.
 */
function bindNavbar() {
    const navbar = document.querySelector('[data-navbar]');

    if (!navbar) {
        return;
    }

    const largeTitle = document.querySelector('[data-large-title]');

    if (largeTitle) {
        // Use IntersectionObserver to detect when the large title leaves the viewport.
        const navbarHeight = navbar.offsetHeight;
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        delete navbar.dataset.scrolled;
                    } else {
                        navbar.dataset.scrolled = '';
                    }
                });
            },
            { rootMargin: `-${navbarHeight}px 0px 0px 0px`, threshold: 0 }
        );

        observer.observe(largeTitle);
    } else {
        // For inline pages without a large title, track scroll position.
        let ticking = false;

        const updateNavbar = () => {
            if (window.scrollY > 0) {
                navbar.dataset.scrolled = '';
            } else {
                delete navbar.dataset.scrolled;
            }

            ticking = false;
        };

        window.addEventListener(
            'scroll',
            () => {
                if (!ticking) {
                    window.requestAnimationFrame(updateNavbar);
                    ticking = true;
                }
            },
            { passive: true }
        );
    }
}

/**
 * Sheet dismissal for the native iOS wrapper.
 *
 * When a link or button has data-sheet-dismiss and the presentation is 'sheet',
 * delegate to the native side instead of following the link normally.
 */
function bindSheetDismiss() {
    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const dismissible = target.closest('[data-sheet-dismiss]');

        if (!dismissible) {
            return;
        }

        // Only intercept if we are in a sheet presentation and the webkit bridge exists.
        if (
            document.documentElement.dataset.presentation !== 'sheet' ||
            !window.webkit?.messageHandlers?.byagain
        ) {
            return;
        }

        event.preventDefault();
        window.webkit.messageHandlers.byagain.postMessage({ type: 'dismiss' });
    });
}

/**
 * Context menus built on <details> elements.
 *
 * Close all open menus when clicking outside, or when Escape is pressed.
 */
function bindMenus() {
    // Close menus on outside click.
    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const openMenus = document.querySelectorAll('[data-menu][open]');

        openMenus.forEach((menu) => {
            if (!menu.contains(target)) {
                menu.removeAttribute('open');
            }
        });
    });

    // Close all menus on Escape.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('[data-menu][open]').forEach((menu) => {
                menu.removeAttribute('open');
            });
        }
    });
}

bindThemeControls();
bindConnectionBanner();
registerServiceWorker();
bindConfirmationForms();
bindNavbar();
bindSheetDismiss();
bindMenus();
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

    // Too soon after the last one. Storage may be unavailable, in which case
    // we simply do not throttle.
    try {
        const last = Number(localStorage.getItem(PREFETCH_KEY));

        if (last > 0 && Date.now() - last < PREFETCH_INTERVAL_MS) {
            return;
        }

        localStorage.setItem(PREFETCH_KEY, String(Date.now()));
    } catch {
        // Unthrottled is better than no offline copy.
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

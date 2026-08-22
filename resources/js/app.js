/**
 * The shell's own script. Vanilla on purpose: no framework reaches a
 * user-facing page (Constitution art. II), and the whole user bundle has a
 * 150KB budget to stay inside (SC-006).
 *
 * Everything here is progressive enhancement. With this file blocked the app
 * still works — the theme follows the OS and the offline banner never shows.
 */

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

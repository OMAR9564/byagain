/**
 * The notification switch, and the permission dance behind it.
 *
 * Loaded only by the settings screen, so the review screen's budget never pays
 * for it (SC-006, R-208).
 *
 * Two rules shape everything here. Permission is asked for on a tap and never
 * on page load — browsers treat an unprompted request as spam, and a reader who
 * blocks it once can never be asked again (FR-145). And where notifications
 * cannot work, the switch says so and does nothing rather than failing on tap
 * (FR-149).
 *
 * No user-facing text lives in this file: every message is rendered into the
 * markup by Blade and read back from a data attribute.
 */

const toggle = document.querySelector('[data-push-toggle]');

if (toggle !== null) {
    setup(toggle);
}

function setup(input) {
    const note = document.querySelector('[data-push-note]');
    const strings = readStrings(input);

    if (!isSupported()) {
        // Chiefly iOS before the app is added to the home screen: `PushManager`
        // simply is not there. Offering a switch that cannot work is the
        // cheapest way to lose someone's trust (R-206).
        disable(input, note, strings.unsupported);

        return;
    }

    if (Notification.permission === 'denied') {
        disable(input, note, strings.denied);

        return;
    }

    input.addEventListener('change', () => {
        // Optimism would be wrong here: the browser may refuse, and the switch
        // has to end up showing what is actually true.
        input.disabled = true;

        const done = () => {
            input.disabled = false;
        };

        if (input.checked) {
            subscribe(input, note, strings).finally(done);
        } else {
            unsubscribe(strings).finally(done);
        }
    });
}

function isSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

async function subscribe(input, note, strings) {
    // Inside the change handler, so this is still the user's tap as far as the
    // browser is concerned.
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        // Denied is final until the reader changes it in browser settings, so
        // the switch goes back and stays back (FR-149).
        input.checked = false;
        disable(input, note, strings.denied);

        return;
    }

    try {
        const registration = await navigator.serviceWorker.ready;

        const subscription =
            (await registration.pushManager.getSubscription()) ??
            (await registration.pushManager.subscribe({
                // Required by every browser that implements this: a
                // notification the reader cannot see is not allowed.
                userVisibleOnly: true,
                applicationServerKey: decodeKey(strings.vapidKey),
            }));

        const response = await request(
            'POST',
            strings.subscribeUrl,
            subscription.toJSON(),
            strings.csrf,
        );

        if (!response.ok) {
            input.checked = false;
            show(note, response.status === 503 ? strings.unconfigured : strings.denied);
        }
    } catch {
        // A push service that will not talk to us is not something the reader
        // can act on, so the switch simply goes back rather than explaining.
        input.checked = false;
    }
}

async function unsubscribe(strings) {
    try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (subscription === null) {
            return;
        }

        const { endpoint } = subscription;

        await subscription.unsubscribe();

        // Told afterwards, on purpose: the browser is the authority on whether
        // this endpoint still exists, and the server's row should not outlive
        // it. The endpoint may already be unknown to us, which is why the
        // route answers 204 either way.
        await request('DELETE', strings.unsubscribeUrl, { endpoint }, strings.csrf);
    } catch {
        // Nothing to tell the reader: the preference itself is saved by the
        // settings form regardless, and a stale row is swept at send time.
    }
}

function request(method, url, body, csrf) {
    return fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
}

/**
 * The VAPID public key travels as base64url and the Push API wants bytes.
 */
function decodeKey(base64url) {
    const padded = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4))
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const raw = atob(padded);
    const bytes = new Uint8Array(raw.length);

    for (let i = 0; i < raw.length; i++) {
        bytes[i] = raw.charCodeAt(i);
    }

    return bytes;
}

function readStrings(input) {
    return {
        csrf: input.dataset.csrf ?? '',
        vapidKey: input.dataset.vapidKey ?? '',
        subscribeUrl: input.dataset.subscribeUrl ?? '',
        unsubscribeUrl: input.dataset.unsubscribeUrl ?? '',
        unsupported: input.dataset.unsupportedText ?? '',
        denied: input.dataset.deniedText ?? '',
        unconfigured: input.dataset.unconfiguredText ?? '',
    };
}

function disable(input, note, message) {
    input.disabled = true;
    input.checked = false;
    show(note, message);
}

function show(note, message) {
    if (note === null || message === '') {
        return;
    }

    note.textContent = message;
    note.hidden = false;
}

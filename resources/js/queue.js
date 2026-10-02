/**
 * Offline action queue — shared by all pages.
 *
 * Card actions that fail to reach the server are queued in localStorage.
 * The queue is replayed when the connection returns or any page loads,
 * whichever comes first.
 */

const QUEUE_KEY = 'byagain.review.queue';

let flushInFlight = false;

/**
 * Read the queue from localStorage.
 *
 * @return {Array<{url: string, body: object}>}
 */
export function readQueue() {
    try {
        return JSON.parse(localStorage.getItem(QUEUE_KEY) ?? '[]');
    } catch {
        return [];
    }
}

/**
 * Add an entry to the queue.
 *
 * @param {{url: string, body: object}} entry
 */
export function enqueue(entry) {
    const queue = readQueue();
    queue.push(entry);
    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
}

/**
 * Send one queued request.
 *
 * Idempotent: the server treats a repeated action as a no-op that returns
 * current state (contracts/review-actions.md).
 *
 * 4xx other than 409 means the payload is wrong and retrying will not help;
 * drop it rather than poisoning the queue forever.
 *
 * @param {string} url
 * @param {object} body
 * @param {string} csrf
 * @return {Promise<object|null>}
 */
async function send(url, body, csrf) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: jsonHeaders(csrf),
            body: JSON.stringify(body),
            // keepalive ensures that a decision committed when the reader
            // leaves the page still reaches the server, so the undo window
            // can stay and the action is never lost. The offline queue still
            // catches network failures (FR-042, FR-043).
            keepalive: true,
        });

        // 4xx other than 409 means the payload is wrong and retrying will not
        // help; drop it rather than poisoning the queue forever.
        if (!response.ok && response.status !== 409 && response.status < 500) {
            return null;
        }

        if (!response.ok) {
            enqueue({ url, body });
            return null;
        }

        return await response.json();
    } catch {
        enqueue({ url, body });
        return null;
    }
}

/**
 * Replay all queued actions.
 *
 * Safe to run at any time because the endpoint is idempotent.
 *
 * Guard against concurrent flushes: if two pages load at once, only one
 * should replay the queue.
 *
 * @param {string} csrf - CSRF token from <meta name="csrf-token">
 * @return {Promise<void>}
 */
export async function flushQueue(csrf) {
    if (flushInFlight) {
        return;
    }

    const queue = readQueue();

    if (queue.length === 0) {
        return;
    }

    flushInFlight = true;

    try {
        localStorage.removeItem(QUEUE_KEY);

        for (const entry of queue) {
            await send(entry.url, entry.body, csrf);
        }
    } finally {
        flushInFlight = false;
    }
}

/**
 * JSON request headers.
 *
 * @param {string} csrf
 * @return {{
 *   'Content-Type': string,
 *   Accept: string,
 *   'X-CSRF-TOKEN': string,
 *   'X-Requested-With': string,
 * }}
 */
function jsonHeaders(csrf) {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
    };
}

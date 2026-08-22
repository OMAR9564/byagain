/**
 * The review screen.
 *
 * Every card is already in the DOM, so moving between them is a class change,
 * not a request. The action goes out afterwards, and if it fails it waits in
 * localStorage until the connection comes back (FR-041, FR-042, R-10).
 *
 * The server treats a repeated action on the same card as a no-op that returns
 * current state, which is what makes replaying the queue safe.
 */

const QUEUE_KEY = 'byagain.review.queue';

const root = document.querySelector('[data-review]');

if (root !== null) {
    start(root);
}

function start(root) {
    const cards = Array.from(root.querySelectorAll('[data-review-card]'));
    const completion = root.querySelector('[data-review-complete]');
    const progress = root.querySelector('[data-review-progress]');
    const streakLine = root.querySelector('[data-review-streak]');
    const csrf = root.dataset.csrf;

    let index = Number(root.dataset.startIndex) || 0;

    show(index);
    flushQueue(csrf);

    cards.forEach((card) => {
        card.querySelectorAll('[data-review-action]').forEach((button) => {
            button.addEventListener('click', () => {
                act(card, button.dataset.reviewAction);
            });
        });

        const favorite = card.querySelector('[data-review-favorite]');

        if (favorite !== null) {
            favorite.addEventListener('click', () => {
                const next = favorite.getAttribute('aria-pressed') !== 'true';
                favorite.setAttribute('aria-pressed', String(next));
            });
        }

        bindExpand(card);

        if (card.dataset.itemType === 'mastery') {
            bindMastery(card, (feedback) => act(card, 'keep', feedback));
        } else {
            // Swiping is for highlights only. A mastery card is a question,
            // and answering it by accident with a stray thumb would be worse
            // than making the reader tap.
            bindSwipe(card);
        }
    });

    // Arrow keys on a desktop, where there is no thumb to swipe with.
    document.addEventListener('keydown', (event) => {
        const card = cards[index];

        if (card === undefined || completion.hidden === false) {
            return;
        }

        if (event.key === 'ArrowRight') {
            act(card, 'keep');
        } else if (event.key === 'ArrowLeft') {
            act(card, 'discard');
        }
    });

    function act(card, action, masteryFeedback = null) {
        if (card.dataset.acted === 'true') {
            return;
        }

        card.dataset.acted = 'true';

        // The interface moves first. Whether the request succeeds is the
        // network's problem, not the reader's.
        advance();

        const favorite = card.querySelector('[data-review-favorite]');
        const frequency = card.querySelector('[data-review-frequency]');

        send(
            card.dataset.actionUrl,
            {
                action,
                mastery_feedback: masteryFeedback,
                favorite: favorite !== null && favorite.getAttribute('aria-pressed') === 'true',
                // Only sent when the reader actually moved the dial, so a
                // plain keep does not rewrite the source every time.
                source_frequency:
                    frequency !== null && frequency.value !== frequency.dataset.initial
                        ? frequency.value
                        : null,
                client_acted_at: new Date().toISOString(),
            },
            csrf,
        );
    }

    function advance() {
        index += 1;
        updateProgress();

        if (index >= cards.length) {
            finish();
            return;
        }

        show(index);
    }

    function show(target) {
        cards.forEach((card, i) => {
            card.hidden = i !== target;
        });

        updateProgress();
    }

    function updateProgress() {
        if (progress === null) {
            return;
        }

        const done = Math.min(index, cards.length);
        progress.setAttribute('aria-valuenow', String(done));

        const fill = progress.firstElementChild;

        if (fill !== null) {
            fill.style.width = `${Math.round((done / Math.max(cards.length, 1)) * 100)}%`;
        }
    }

    async function finish() {
        cards.forEach((card) => {
            card.hidden = true;
        });

        completion.hidden = false;

        try {
            const response = await fetch(root.dataset.completeUrl, {
                method: 'POST',
                headers: jsonHeaders(csrf),
                body: '{}',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();

            if (payload.streak && streakLine !== null) {
                streakLine.textContent = payload.streak.current;
                streakLine.hidden = false;
            }
        } catch {
            // Offline. The queued card actions will complete the review on
            // the server as soon as they land.
        }
    }
}

/**
 * A mastery card: question, then answer on request, then four ways to say
 * when it should come back.
 *
 * The feedback buttons stay hidden until the answer has been shown, so there
 * is no way to grade recall you have not actually attempted (FR-045).
 */
function bindMastery(card, onFeedback) {
    const reveal = card.querySelector('[data-mastery-reveal]');
    const answer = card.querySelector('[data-mastery-answer]');
    const choices = card.querySelector('[data-mastery-feedback]');

    if (reveal === null || answer === null || choices === null) {
        return;
    }

    reveal.addEventListener('click', () => {
        answer.hidden = false;
        choices.hidden = false;
        reveal.setAttribute('aria-expanded', 'true');
        reveal.hidden = true;
    });

    choices.querySelectorAll('[data-mastery-choice]').forEach((button) => {
        button.addEventListener('click', () => onFeedback(button.dataset.masteryChoice));
    });
}

/**
 * Collapsed long passages (FR-082).
 */
function bindExpand(card) {
    const button = card.querySelector('[data-expand-highlight]');

    if (button === null) {
        return;
    }

    button.addEventListener('click', () => {
        const body = card.querySelector('.highlight-body');
        body.classList.remove('is-collapsed');
        button.remove();
    });
}

/**
 * Horizontal swipe: right keeps, left discards. Vertical movement is left
 * alone so the passage can still be scrolled.
 */
function bindSwipe(card) {
    let startX = null;
    let startY = null;

    card.addEventListener(
        'touchstart',
        (event) => {
            startX = event.changedTouches[0].clientX;
            startY = event.changedTouches[0].clientY;
        },
        { passive: true },
    );

    card.addEventListener(
        'touchend',
        (event) => {
            if (startX === null) {
                return;
            }

            const dx = event.changedTouches[0].clientX - startX;
            const dy = event.changedTouches[0].clientY - startY;

            startX = null;
            startY = null;

            // Needs to be a decisive, mostly-horizontal gesture. Anything
            // else is someone scrolling.
            if (Math.abs(dx) < 60 || Math.abs(dx) < Math.abs(dy) * 1.5) {
                return;
            }

            const action = dx > 0 ? 'keep' : 'discard';
            card.querySelector(`[data-review-action="${action}"]`)?.click();
        },
        { passive: true },
    );
}

function jsonHeaders(csrf) {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
    };
}

async function send(url, body, csrf) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: jsonHeaders(csrf),
            body: JSON.stringify(body),
        });

        // 4xx other than 409 means the payload is wrong and retrying will not
        // help; drop it rather than poisoning the queue forever.
        if (!response.ok && response.status !== 409 && response.status < 500) {
            return;
        }

        if (!response.ok) {
            enqueue({ url, body });
        }
    } catch {
        enqueue({ url, body });
    }
}

function readQueue() {
    try {
        return JSON.parse(localStorage.getItem(QUEUE_KEY) ?? '[]');
    } catch {
        return [];
    }
}

function enqueue(entry) {
    const queue = readQueue();
    queue.push(entry);
    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
}

/**
 * Replay whatever did not get through. Safe to run at any time because the
 * endpoint is idempotent: a card that was already dealt with keeps its first
 * action and reports current state.
 */
async function flushQueue(csrf) {
    const queue = readQueue();

    if (queue.length === 0) {
        return;
    }

    localStorage.removeItem(QUEUE_KEY);

    for (const entry of queue) {
        await send(entry.url, entry.body, csrf);
    }
}

window.addEventListener('online', () => {
    flushQueue(root?.dataset.csrf);
});

/**
 * The review screen.
 *
 * Every card is already in the DOM, so moving between them is a class change,
 * not a request. The action goes out afterwards, and if it fails it waits in
 * localStorage until the connection comes back (FR-041, FR-042, R-10).
 *
 * The server treats a repeated action on the same card as a no-op that returns
 * current state, which is what makes replaying the queue safe.
 *
 * Two things are deliberate about the way a decision is taken here:
 *
 *   - It is held, not sent. For a few seconds the action lives only in this
 *     file, and Undo makes it as if it never happened. A swipe is a gesture
 *     the thumb can make by accident, and the server keeps the first answer
 *     forever, so the moment to change your mind has to exist before the
 *     request rather than after it.
 *   - Once it is sent it is final, and the interface says so instead of
 *     offering an edit it cannot honour. Stepping back onto a decided card
 *     shows what was chosen; it does not pretend to be a form.
 */

import { send, flushQueue, jsonHeaders } from './queue.js';

/** A gesture has to be this decisive to count, in pixels. */
const SWIPE_DISTANCE = 60;

/** ...and this much more horizontal than vertical, or it is a scroll. */
const SWIPE_DOMINANCE = 1.5;

/** Where the keep/discard hint starts fading in, in pixels of travel. */
const HINT_FROM = 20;

const HINT_TO = 80;

/** Degrees of tilt at full travel. Small: the card is being pushed, not thrown. */
const TILT_AT_FULL = 6;

/** Prefix of the per-review record of cards decided on this device. */
const ACTED_KEY_PREFIX = 'byagain.review.acted.';

const root = document.querySelector('[data-review]');

if (root !== null) {
    start(root);
}

function start(root) {
    const cards = Array.from(root.querySelectorAll('[data-review-card]'));
    const completion = root.querySelector('[data-review-complete]');
    const progress = root.querySelector('[data-review-progress]');
    const streakLine = root.querySelector('[data-review-streak]');
    const back = root.querySelector('[data-review-back]');
    const undoBar = root.querySelector('[data-review-undo]');
    const undoLabel = root.querySelector('[data-review-undo-label]');
    const undoButton = root.querySelector('[data-review-undo-action]');
    const copy = readCopy(root);
    const csrf = root.dataset.csrf;
    const undoMs = (Number(root.dataset.undoSeconds) || 5) * 1000;

    // A review reopened offline comes from the cache, which holds the page as
    // it was when it was stored: cards decided since then look undecided. The
    // server would keep the first answer anyway, but the reader would be asked
    // twice, so what was decided here is remembered locally and applied again.
    const reviewId = root.dataset.reviewId || null;

    if (reviewId !== null) {
        const recorded = readActed(reviewId);

        cards.forEach((card) => {
            const verdict = recorded[card.dataset.itemId];

            if (verdict !== undefined && card.dataset.acted !== 'true') {
                card.dataset.acted = 'true';
                card.dataset.verdict = verdict;
            }
        });

        const firstOpen = cards.findIndex((card) => card.dataset.acted !== 'true');

        root.dataset.startIndex = String(firstOpen === -1 ? cards.length : firstOpen);
    }

    // `frontier` is the card being decided; `viewing` is the card on screen.
    // They are the same until the reader steps back to look at one they have
    // already dealt with. Both may equal cards.length, which means the
    // completion screen.
    let frontier = Number(root.dataset.startIndex) || 0;
    let viewing = frontier;

    // The decision waiting out its undo window. At most one: taking another
    // action commits whatever was already held.
    let pending = null;

    showCard(frontier);
    flushQueue(csrf);

    cards.forEach((card, position) => {
        card.querySelectorAll('[data-review-action]').forEach((button) => {
            button.addEventListener('click', () => act(card, button.dataset.reviewAction));
        });

        const favorite = card.querySelector('[data-review-favorite]');

        if (favorite !== null) {
            favorite.addEventListener('click', () => {
                const next = favorite.getAttribute('aria-pressed') !== 'true';
                favorite.setAttribute('aria-pressed', String(next));
            });
        }

        card.querySelector('[data-review-resume]')?.addEventListener('click', () => showCard(frontier));

        bindExpand(card);

        if (card.dataset.itemType === 'mastery') {
            bindMastery(card, (feedback) => act(card, 'keep', feedback));
        } else {
            // Swiping is for highlights only. A mastery card is a question,
            // and answering it by accident with a stray thumb would be worse
            // than making the reader tap.
            bindSwipe(card, position);
        }
    });

    back?.addEventListener('click', () => showCard(viewing - 1));
    undoButton?.addEventListener('click', undoPending);

    // Done button(s): commit any pending action before leaving, with a timeout
    // so a slow or offline network never traps the reader. The offline queue
    // will retry if needed (FR-042, FR-043).
    document.querySelectorAll('[data-review-done]').forEach((button) => {
        button.addEventListener('click', async (event) => {
            event.preventDefault();

            const href = button.getAttribute('href');
            if (!href) {
                return;
            }

            const pending = commitPending();

            if (pending === null) {
                // Nothing was pending, navigate at once.
                window.location.assign(href);

                return;
            }

            // Wait for the pending action to reach the server, with a 1500ms
            // timeout so we never trap the reader on a slow connection.
            await Promise.race([
                pending,
                new Promise((resolve) => window.setTimeout(resolve, 1500)),
            ]);

            window.location.assign(href);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && pending !== null) {
            undoPending();

            return;
        }

        if (event.key === 'ArrowUp') {
            showCard(viewing - 1);

            return;
        }

        // Deciding is only possible on the card being decided. Arrow keys on
        // a card you stepped back to look at would silently act on a
        // different one.
        if (viewing !== frontier || frontier >= cards.length) {
            return;
        }

        if (event.key === 'ArrowRight') {
            act(cards[frontier], 'keep');
        } else if (event.key === 'ArrowLeft') {
            act(cards[frontier], 'discard');
        }
    });

    // A held action must not be lost because the reader closed the tab or
    // switched app. `pagehide` is the one event iOS reliably fires.
    window.addEventListener('pagehide', () => commitPending());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            commitPending();
        }
    });

    function act(card, action, masteryFeedback = null) {
        if (card.dataset.acted === 'true' || pending?.card === card) {
            return;
        }

        // One at a time: deciding the next card ends the previous card's
        // window rather than queueing a second undo nobody could aim at.
        commitPending();

        pending = {
            card,
            action,
            masteryFeedback,
            index: cards.indexOf(card),
            favorite: card.querySelector('[data-review-favorite]')?.getAttribute('aria-pressed') === 'true',
            frequency: frequencyChange(card),
            actedAt: new Date().toISOString(),
            timer: window.setTimeout(() => commitPending(), undoMs),
        };

        card.dataset.verdict = action;

        showUndo(action);
        advance();
    }

    /**
     * Send the held decision. From here it is the server's, and the interface
     * stops offering to take it back.
     *
     * Returns the send promise so callers can wait for it, or null if there
     * was nothing pending. This enables the Done button to wait for the
     * last card's action to reach the server before navigating (FR-043).
     */
    function commitPending() {
        if (pending === null) {
            return null;
        }

        const held = pending;
        pending = null;

        window.clearTimeout(held.timer);
        hideUndo();

        held.card.dataset.acted = 'true';

        if (reviewId !== null) {
            recordActed(reviewId, held.card.dataset.itemId, held.action);
        }

        const sent = send(
            held.card.dataset.actionUrl,
            {
                action: held.action,
                mastery_feedback: held.masteryFeedback,
                favorite: held.favorite,
                // Only sent when the reader actually moved the dial, so a
                // plain keep does not rewrite the source every time.
                source_frequency: held.frequency,
                client_acted_at: held.actedAt,
            },
            csrf,
        );

        // The card that finishes the review completes it server-side by
        // itself; this call is what tells the reader, and what covers the
        // case where the action was queued offline.
        if (held.index === cards.length - 1) {
            sent.then((payload) => completeReview(payload));
        }

        return sent;
    }

    function undoPending() {
        if (pending === null) {
            return;
        }

        const held = pending;
        pending = null;

        window.clearTimeout(held.timer);
        hideUndo();

        held.card.dataset.verdict = '';
        frontier = held.index;

        showCard(frontier);
    }

    function advance() {
        frontier += 1;

        showCard(frontier);
    }

    /**
     * Put a card on screen. `target` may be cards.length, which is the
     * completion screen, and is clamped at both ends.
     */
    function showCard(target) {
        viewing = Math.max(0, Math.min(target, cards.length));

        cards.forEach((card, i) => {
            card.hidden = i !== viewing;

            if (i === viewing) {
                resetSwipe(card);
            }
        });

        completion.hidden = viewing !== cards.length;

        updateChrome();
    }

    /**
     * The controls around the card: which of them belong to the card being
     * looked at right now.
     */
    function updateChrome() {
        if (back !== null) {
            back.hidden = viewing === 0;
        }

        const card = cards[viewing];

        if (card !== undefined) {
            // Decided cards — including the one whose window is still open —
            // show their verdict rather than their buttons.
            const decided = card.dataset.acted === 'true' || card.dataset.verdict !== '';

            const actions = card.querySelector('[data-review-actions]');
            const verdict = card.querySelector('[data-review-verdict]');
            const masteryChoices = card.querySelector('[data-mastery-feedback]');

            if (actions !== null) {
                actions.hidden = decided;
            }

            if (masteryChoices !== null && decided) {
                masteryChoices.hidden = true;
            }

            if (verdict !== null) {
                verdict.hidden = ! decided;

                const label = verdict.querySelector('[data-review-verdict-label]');

                if (label !== null) {
                    label.textContent = verdictText(card);
                }
            }
        }

        updateProgress();
    }

    function verdictText(card) {
        if (card.dataset.itemType === 'mastery') {
            return copy.answered;
        }

        return card.dataset.verdict === 'discard' ? copy.discarded : copy.kept;
    }

    function updateProgress() {
        if (progress === null) {
            return;
        }

        const done = Math.min(frontier, cards.length);
        progress.setAttribute('aria-valuenow', String(done));

        const fill = progress.firstElementChild;

        if (fill !== null) {
            fill.style.width = `${Math.round((done / Math.max(cards.length, 1)) * 100)}%`;
        }
    }

    function showUndo(action) {
        if (undoBar === null) {
            return;
        }

        undoLabel.textContent = action === 'discard' ? copy.undoDiscarded : copy.undoKept;

        undoBar.hidden = false;
        undoBar.dataset.entering = '';

        // Two frames: the first paints the bar in its offset state, the
        // second removes it so the transition has somewhere to travel from.
        requestAnimationFrame(() => requestAnimationFrame(() => delete undoBar.dataset.entering));
    }

    function hideUndo() {
        if (undoBar !== null) {
            undoBar.hidden = true;
        }
    }

    async function completeReview(payload) {
        const streak = payload?.streak ?? (await postCompletion())?.streak;

        if (streak && streakLine !== null) {
            streakLine.textContent = streak.current;
            streakLine.hidden = false;
        }
    }

    async function postCompletion() {
        // Practice has no completion endpoint (data-complete-url is absent),
        // so the completion screen shows locally only (R-302). Review does have
        // one and calls it here.
        if (!root.dataset.completeUrl) {
            return null;
        }

        try {
            const response = await fetch(root.dataset.completeUrl, {
                method: 'POST',
                headers: jsonHeaders(csrf),
                body: '{}',
            });

            return response.ok ? await response.json() : null;
        } catch {
            // Offline. The queued card actions will complete the review on
            // the server as soon as they land.
            return null;
        }
    }

    /**
     * Horizontal swipe: right keeps, left discards. The card tracks the thumb
     * and leaves in the direction it was pushed; vertical movement is left
     * alone so the passage can still be scrolled.
     */
    function bindSwipe(card, position) {
        const surface = card.querySelector('[data-swipe-surface]');

        if (surface === null) {
            return;
        }

        const hints = {
            keep: surface.querySelector('[data-swipe-hint="keep"]'),
            discard: surface.querySelector('[data-swipe-hint="discard"]'),
        };

        let startX = null;
        let startY = null;
        let dragging = false;

        const decidable = () => card.dataset.acted !== 'true'
            && card.dataset.verdict === ''
            && position === frontier;

        /**
         * Whether the touch landed inside something that scrolls sideways on
         * its own — a code block, a wide table.
         *
         * Such a box belongs to the browser for the whole of that touch. CSS
         * hands it back the horizontal axis, but the gesture would still be
         * tracking the same finger and dragging the card along under the
         * scrolling code, so it is not armed at all (R-201, FR-121).
         *
         * Deliberately not conditioned on how far the box has left to scroll:
         * a reader who reaches the end of a line and keeps going is still
         * reading, and a decision landing there is exactly the accident this
         * fixes (FR-122).
         *
         * Asked of the element rather than of any particular tag, so it holds
         * for whatever a card is made of, mastery cards included (FR-125).
         */
        const insideHorizontalScroller = (target) => {
            let node = target instanceof Element ? target : target?.parentElement ?? null;

            while (node !== null && node !== surface) {
                const overflowX = window.getComputedStyle(node).overflowX;

                // The extra pixel is sub-pixel rounding: a box laid out at a
                // fractional width reports a scrollWidth a hair over its
                // client width while having nothing to scroll.
                if ((overflowX === 'auto' || overflowX === 'scroll') && node.scrollWidth > node.clientWidth + 1) {
                    return true;
                }

                node = node.parentElement;
            }

            return false;
        };

        surface.addEventListener(
            'touchstart',
            (event) => {
                if (! decidable() || insideHorizontalScroller(event.target)) {
                    return;
                }

                startX = event.changedTouches[0].clientX;
                startY = event.changedTouches[0].clientY;
                dragging = false;
                surface.classList.remove('is-settling');
            },
            { passive: true },
        );

        surface.addEventListener(
            'touchmove',
            (event) => {
                if (startX === null) {
                    return;
                }

                const dx = event.changedTouches[0].clientX - startX;
                const dy = event.changedTouches[0].clientY - startY;

                if (! dragging && Math.abs(dx) < Math.abs(dy)) {
                    // A scroll. Let go of the gesture entirely rather than
                    // fighting the page for it.
                    startX = null;

                    return;
                }

                dragging = true;

                surface.style.transform = `translateX(${dx}px) rotate(${(dx / window.innerWidth) * TILT_AT_FULL * 2}deg)`;

                const strength = Math.min(1, Math.max(0, (Math.abs(dx) - HINT_FROM) / (HINT_TO - HINT_FROM)));

                hints.keep?.style.setProperty('opacity', dx > 0 ? String(strength) : '0');
                hints.discard?.style.setProperty('opacity', dx < 0 ? String(strength) : '0');
            },
            { passive: true },
        );

        surface.addEventListener(
            'touchend',
            (event) => {
                if (startX === null) {
                    return;
                }

                const dx = event.changedTouches[0].clientX - startX;
                const dy = event.changedTouches[0].clientY - startY;

                startX = null;
                startY = null;
                dragging = false;

                surface.classList.add('is-settling');

                // Needs to be a decisive, mostly-horizontal gesture. Anything
                // else is someone scrolling, and the card goes back to rest.
                if (Math.abs(dx) < SWIPE_DISTANCE || Math.abs(dx) < Math.abs(dy) * SWIPE_DOMINANCE) {
                    resetSwipe(card);

                    return;
                }

                const direction = dx > 0 ? 1 : -1;

                surface.style.transform = `translateX(${direction * window.innerWidth}px) rotate(${direction * TILT_AT_FULL}deg)`;
                surface.style.opacity = '0';

                act(card, direction > 0 ? 'keep' : 'discard');
            },
            { passive: true },
        );
    }
}

/**
 * Put a card back at rest. Called when it is shown as well as when a gesture
 * is abandoned, so a card that left the screen and was undone comes back
 * square.
 */
function resetSwipe(card) {
    const surface = card.querySelector('[data-swipe-surface]');

    if (surface === null) {
        return;
    }

    surface.style.transform = '';
    surface.style.opacity = '';

    surface.querySelectorAll('[data-swipe-hint]').forEach((hint) => {
        hint.style.opacity = '0';
    });
}

/**
 * Copy for the strings this file puts on screen. Rendered by the translator
 * into the page rather than written here, so there is one place user-facing
 * words live.
 */
function readCopy(root) {
    const source = root.querySelector('[data-review-copy]');

    try {
        return JSON.parse(source?.textContent ?? '{}');
    } catch {
        return {};
    }
}

function frequencyChange(card) {
    const select = card.querySelector('[data-review-frequency]');

    if (select === null || select.value === select.dataset.initial) {
        return null;
    }

    return select.value;
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
 * Cards decided on this device for one review, as { itemId: action }.
 *
 * Keys of other reviews are dropped on the way, so storage does not grow by a
 * key every day. Storage can be full, blocked or corrupt; none of that may
 * stop the review, so it reads as "nothing recorded".
 */
function readActed(reviewId) {
    try {
        const own = ACTED_KEY_PREFIX + reviewId;

        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i);

            if (key !== null && key.startsWith(ACTED_KEY_PREFIX) && key !== own) {
                localStorage.removeItem(key);
            }
        }

        const parsed = JSON.parse(localStorage.getItem(own) ?? '{}');

        return parsed !== null && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
}

function recordActed(reviewId, itemId, action) {
    try {
        const acted = readActed(reviewId);
        acted[itemId] = action;
        localStorage.setItem(ACTED_KEY_PREFIX + reviewId, JSON.stringify(acted));
    } catch {
        // Not remembering costs a repeated question offline, nothing more.
    }
}

/**
 * The one-handed editor.
 *
 * Two jobs: wrap selections in markdown without moving the thumb off the
 * keyboard, and never lose what someone typed (FR-020, FR-021).
 */

const DRAFT_PREFIX = 'byagain.draft.';
const DRAFT_DEBOUNCE_MS = 500;

document.querySelectorAll('[data-editor]').forEach(setup);

function setup(form) {
    const input = form.querySelector('[data-editor-input]');
    const preview = form.querySelector('[data-editor-preview]');
    const status = form.querySelector('[data-editor-draft-status]');
    const draftKey = DRAFT_PREFIX + form.dataset.draftKey;

    restoreDraft(input, draftKey, status);
    bindDraftSaving(form, input, draftKey, status);
    bindFormatting(form, input);
    bindTabs(form, input, preview);
}

/**
 * Offer back anything left behind by a crash, a closed tab or a dead battery.
 * Only when the field is empty — a saved highlight must never be silently
 * overwritten by a stale draft.
 */
function restoreDraft(input, key, status) {
    const draft = localStorage.getItem(key);

    if (draft === null || input.value.trim() !== '') {
        return;
    }

    input.value = draft;

    if (status !== null) {
        status.textContent = status.dataset.restored ?? '';
    }
}

function bindDraftSaving(form, input, key, status) {
    let timer = null;

    input.addEventListener('input', () => {
        window.clearTimeout(timer);

        // Debounced: writing to localStorage on every keystroke is the kind
        // of thing that makes a cheap phone feel slow.
        timer = window.setTimeout(() => {
            localStorage.setItem(key, input.value);

            if (status !== null) {
                status.textContent = status.dataset.saved ?? '';
            }
        }, DRAFT_DEBOUNCE_MS);
    });

    form.addEventListener('submit', () => {
        window.clearTimeout(timer);
        localStorage.removeItem(key);
    });
}

/**
 * Wrap the selection, or insert a line prefix for block-level tokens.
 */
function bindFormatting(form, input) {
    form.querySelectorAll('[data-editor-format]').forEach((button) => {
        button.addEventListener('mousedown', (event) => {
            // Keep focus in the textarea so the keyboard does not close and
            // the selection is not lost.
            event.preventDefault();
        });

        button.addEventListener('click', () => {
            applyToken(input, button.dataset.editorFormat);
        });
    });
}

function applyToken(input, token) {
    const start = input.selectionStart;
    const end = input.selectionEnd;
    const value = input.value;
    const selected = value.slice(start, end);

    const isBlock = token.endsWith(' ');

    if (isBlock) {
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        input.value = value.slice(0, lineStart) + token + value.slice(lineStart);
        input.setSelectionRange(start + token.length, end + token.length);
    } else {
        input.value = value.slice(0, start) + token + selected + token + value.slice(end);
        input.setSelectionRange(start + token.length, end + token.length);
    }

    input.focus();
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

/**
 * Write/preview toggle.
 *
 * The preview shows the markdown source as plain text via textContent. It is
 * a shape check for the writer, not a renderer — rendering it here would
 * create a second path to the page that skips MarkdownRenderer's purifier,
 * and there is only ever meant to be one.
 */
function bindTabs(form, input, preview) {
    const tabs = form.querySelectorAll('[data-editor-tab]');

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const showPreview = tab.dataset.editorTab === 'preview';

            input.hidden = showPreview;
            preview.hidden = !showPreview;

            if (showPreview) {
                preview.textContent = input.value;
            }

            tabs.forEach((other) => {
                other.setAttribute('aria-selected', String(other === tab));
            });
        });
    });
}

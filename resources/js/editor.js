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
 * The preview is rendered by the server and arrives as HTML that has already
 * been through MarkdownRenderer and its purifier — the same value that would
 * be stored in content_html. That is the whole reason it is a request rather
 * than a library in this bundle: a markdown renderer here would be a second
 * path to the page that skips the purifier, and it would drift from the
 * server's the first time either changed. What the writer sees has to be what
 * gets saved, down to the repaired line breaks of a PDF paste.
 */
function bindTabs(form, input, preview) {
    const tabs = form.querySelectorAll('[data-editor-tab]');
    const body = preview?.querySelector('[data-editor-preview-body]');

    if (preview === null || body === null || body === undefined) {
        return;
    }

    // Only the newest request may write into the preview. Switching tabs
    // twice quickly used to be enough to see the older of two answers.
    let generation = 0;

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const showPreview = tab.dataset.editorTab === 'preview';

            input.hidden = showPreview;
            preview.hidden = !showPreview;

            tabs.forEach((other) => {
                other.setAttribute('aria-selected', String(other === tab));
            });

            if (showPreview) {
                render(form, input, body, ++generation, () => generation);
            }
        });
    });
}

async function render(form, input, body, ticket, current) {
    const markdown = input.value;

    if (markdown.trim() === '') {
        body.textContent = form.dataset.previewEmpty ?? '';

        return;
    }

    body.textContent = form.dataset.previewPending ?? '';

    try {
        const response = await fetch(form.dataset.previewUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ content_md: markdown }),
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const payload = await response.json();

        if (ticket !== current()) {
            return;
        }

        // Safe to set as HTML: this string was produced by MarkdownRenderer,
        // which escapes raw HTML at the CommonMark stage and then purifies
        // against an explicit whitelist. It is the same value the review card
        // echoes. Nothing else may be written this way.
        body.innerHTML = payload.html;
    } catch {
        if (ticket === current()) {
            body.textContent = form.dataset.previewFailed ?? '';
        }
    }
}

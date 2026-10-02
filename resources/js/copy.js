/**
 * Copy button for the export screen.
 *
 * Handles copying textarea content to clipboard with three-tier fallback:
 * 1. navigator.clipboard.writeText (modern, async)
 * 2. select() + execCommand (legacy, works on iOS Safari)
 * 3. Show selection hint (user copies manually via device menu)
 */
export default function initCopy() {
  const button = document.querySelector('[data-copy-button]');
  const source = document.querySelector('[data-copy-source]');
  const status = document.querySelector('[data-copy-status]');

  if (!button || !source || !status) {
    return;
  }

  button.addEventListener('click', async () => {
    try {
      // Modern async API (HTTPS/localhost only)
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(source.value);
        status.textContent = button.getAttribute('data-copied');
        return;
      }
    } catch (err) {
      // Clipboard API rejected (privacy or user denial)
    }

    // Fallback: select and copy synchronously
    source.select();
    const copySucceeded = document.execCommand('copy');

    if (copySucceeded) {
      status.textContent = button.getAttribute('data-copied');
    } else {
      // Last resort: show fallback hint
      status.textContent = button.getAttribute('data-fallback');
    }
  });
}

// Auto-initialize on page load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCopy);
} else {
  initCopy();
}

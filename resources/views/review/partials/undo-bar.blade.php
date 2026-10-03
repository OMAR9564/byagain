{{-- The action is held for a few seconds before it is sent, so a
     mis-swipe costs a tap rather than a day (FR-042). --}}
<div data-review-undo hidden role="status" class="fixed inset-x-0 px-4" style="z-index: var(--z-toast);">
    <div class="mx-auto flex max-w-md items-center justify-between gap-3 rounded-full py-2.5 pr-2 pl-5"
         style="background-color: var(--color-ink); color: var(--color-canvas); box-shadow: var(--shadow-raised);">
        <span class="text-sm" data-review-undo-label></span>

        <button
            type="button"
            data-review-undo-action
            class="inline-flex min-h-9 items-center rounded-full px-4 text-sm font-semibold"
            style="background-color: var(--color-canvas); color: var(--color-ink);"
        >
            {{ __('review.undo.action') }}
        </button>
    </div>
</div>

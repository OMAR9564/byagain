{{-- The action is held for a few seconds before it is sent, so a
     mis-swipe costs a tap rather than a day (FR-042). --}}
<div data-review-undo hidden role="status" class="fixed inset-x-0 px-4" style="z-index: var(--z-toast);">
    <div class="mx-auto flex max-w-md items-center justify-between gap-3 rounded-[14px] py-1.5 pr-1 pl-5"
         style="background-color: var(--color-bar); -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px); color: var(--color-ink); box-shadow: 0 0 0 0.5px var(--color-separator), var(--shadow-raised);">
        <span class="text-sm" data-review-undo-label></span>

        <button
            type="button"
            data-review-undo-action
            class="pressable inline-flex min-h-11 items-center px-4 text-sm font-semibold"
            style="color: var(--color-accent);"
        >
            {{ __('review.undo.action') }}
        </button>
    </div>
</div>

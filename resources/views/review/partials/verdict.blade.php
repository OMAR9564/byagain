{{-- Shown when the reader steps back onto a card they have
     already dealt with. It states what they chose and offers
     the way forward; it does not offer to rewrite it, because
     the server keeps the first answer and pretending otherwise
     would be a lie. --}}
<div data-review-verdict hidden class="mt-4 flex items-center justify-between gap-3">
    <span
        data-review-verdict-label
        class="inline-flex min-h-9 items-center rounded-full px-3 text-sm font-medium"
        style="background-color: var(--color-fill); color: var(--color-ink-muted);"
    ></span>

    <button
        type="button"
        data-review-resume
        class="pressable inline-flex min-h-11 items-center gap-1.5 text-sm font-medium"
        style="color: var(--color-accent);"
    >
        {{ __('review.nav.resume') }}
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
            <path d="M9.5 6.5 15 12l-5.5 5.5" />
        </svg>
    </button>
</div>

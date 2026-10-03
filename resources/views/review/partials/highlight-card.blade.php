{{-- The layer the thumb drags. Keeping the transform off the
     <article> leaves the buttons still while the passage
     moves, so the card reads as a thing being pushed rather
     than the screen sliding. --}}
<div class="relative" data-swipe-surface>
    <div class="pointer-events-none absolute inset-x-0 top-3 z-10 flex justify-between px-3">
        <span data-swipe-hint="discard" class="rounded-full px-3 py-1 text-xs font-semibold opacity-0"
              style="background-color: var(--color-critical); color: var(--color-canvas);">
            {{ __('review.action.discard') }}
        </span>

        <span data-swipe-hint="keep" class="rounded-full px-3 py-1 text-xs font-semibold opacity-0"
              style="background-color: var(--color-positive); color: var(--color-canvas);">
            {{ __('review.action.keep') }}
        </span>
    </div>

    <x-card>
        <p class="mb-3 text-sm" style="color: var(--color-ink-muted);">
            {{ $highlight->source?->title }}
            @if ($highlight->location !== null)
                · {{ $highlight->location }}
            @endif
        </p>

        <x-highlight-content :highlight="$highlight" />
    </x-card>
</div>

<div data-review-actions>
    <div class="mt-4 flex items-stretch gap-3">
        <x-button
            variant="secondary"
            class="flex-1"
            data-review-action="discard"
            style="min-height: var(--size-touch-lg);"
        >
            <span class="flex items-center gap-2">
                {{-- An archive box, not a bin: discarding hides
                     a highlight from future reviews and never
                     deletes it (FR-011). --}}
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                    <path d="M4.8 4.5h14.4a.8.8 0 0 1 .8.8v2.4a.8.8 0 0 1-.8.8H4.8a.8.8 0 0 1-.8-.8V5.3a.8.8 0 0 1 .8-.8z" />
                    <path d="M5.6 8.5v10a1.2 1.2 0 0 0 1.2 1.2h10.4a1.2 1.2 0 0 0 1.2-1.2v-10" />
                    <path d="M10 12.4h4" />
                </svg>
                {{ __('review.action.discard') }}
            </span>
        </x-button>

        <x-button
            class="flex-1"
            data-review-action="keep"
            style="min-height: var(--size-touch-lg);"
        >
            <span class="flex items-center gap-2">
                {{-- A bookmark: it stays in the book. --}}
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                    <path d="M7.2 4.5h9.6a1 1 0 0 1 1 1v14l-5.8-3.5-5.8 3.5v-14a1 1 0 0 1 1-1z" />
                </svg>
                {{ __('review.action.keep') }}
            </span>
        </x-button>
    </div>

    {{-- "Discard" on its own reads as deletion. It is not, and
         one line under the buttons is cheaper than finding out
         the hard way. --}}
    <div class="mt-1.5 flex items-start gap-3 text-center text-xs" style="color: var(--color-ink-subtle);">
        <span class="flex-1">{{ __('review.action.discard_help') }}</span>
        <span class="flex-1">{{ __('review.action.keep_help') }}</span>
    </div>

    <div class="mt-3 flex items-center gap-3">
        <button
            type="button"
            class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 text-sm"
            style="color: var(--color-ink-muted);"
            data-review-favorite
            aria-pressed="{{ $highlight->is_favorite ? 'true' : 'false' }}"
        >
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"
                 data-favorite-mark>
                <path d="M12 20.2s-6.9-4.3-6.9-9A3.9 3.9 0 0 1 12 8.6a3.9 3.9 0 0 1 6.9 2.6c0 4.7-6.9 9-6.9 9z" />
            </svg>
            {{ __('review.action.favorite') }}
        </button>

        {{-- Retuning a source from the card it interrupted you
             with, rather than making you go and find it in the
             library. Takes effect from the next review (FR-038,
             FR-028). --}}
        <label class="flex flex-1 items-center gap-2 text-sm" style="color: var(--color-ink-muted);">
            <span class="sr-only">{{ __('review.action.source_frequency') }}</span>

            <select
                data-review-frequency
                data-initial="{{ $highlight->source?->frequency }}"
                class="min-h-11 w-full rounded-lg px-2 text-sm"
                style="background-color: var(--color-surface); color: var(--color-ink-muted); border: 1px solid var(--color-border);"
            >
                @foreach (\App\Http\Requests\StoreSourceRequest::frequencies() as $frequency)
                    <option value="{{ $frequency }}" @selected($highlight->source?->frequency === $frequency)>
                        {{ __('library.frequency.' . $frequency) }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>
</div>

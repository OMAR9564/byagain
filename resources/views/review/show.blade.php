<x-layouts.app greet tabRoot :title="__('review.title')">
    {{-- Cards are rendered server-side and hidden with [hidden], not fetched.
         The whole review is already on the page, so moving between cards
         costs nothing and works with no connection at all (FR-041). --}}
    <div
        id="review"
        data-review
        data-review-id="{{ $review->id }}"
        data-start-index="{{ $startIndex }}"
        data-complete-url="{{ route('review.complete') }}"
        data-csrf="{{ csrf_token() }}"
        data-undo-seconds="{{ config('byagain.review.undo_window_seconds') }}"
    >
        {{-- Copy the script needs, rendered by the translator rather than
             written into the bundle. Constitution: nothing user-facing is
             spelled out in a .js file. --}}
        @php
            $reviewCopy = [
                'kept' => __('review.nav.kept'),
                'discarded' => __('review.nav.discarded'),
                'answered' => __('review.nav.answered'),
                'undoKept' => __('review.undo.kept'),
                'undoDiscarded' => __('review.undo.discarded'),
            ];
        @endphp

        <script type="application/json" data-review-copy>@json($reviewCopy)</script>

        <div class="mb-5 flex items-center gap-3">
            {{-- Going back was the missing half of swiping. A gesture that only
                 moves one way turns a slip of the thumb into a decision you
                 cannot look at again. --}}
            <button
                type="button"
                data-review-back
                hidden
                class="bar-button pressable"
                aria-label="{{ __('review.nav.previous') }}"
            >
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;">
                    <path d="M14.5 6.5 9 12l5.5 5.5" />
                </svg>
            </button>

            <x-progress-bar :current="$startIndex" :total="$review->items->count()" class="flex-1" data-review-progress />
        </div>

        @foreach ($review->items as $index => $item)
            @continue($item->highlight === null && $item->masteryCard === null)

            <article
                class="review-card"
                data-review-card
                data-item-type="{{ $item->item_type }}"
                data-item-id="{{ $item->id }}"
                data-action-url="{{ route('review.item.action', $item) }}"
                data-acted="{{ $item->isActed() ? 'true' : 'false' }}"
                data-verdict="{{ $item->action ?? '' }}"
                @if ($index !== $startIndex) hidden @endif
            >
            @if ($item->item_type === \App\Models\ReviewItem::TYPE_MASTERY)
                @include('review.partials.mastery-card', ['card' => $item->masteryCard, 'domId' => 'item-'.$item->id, 'scheduling' => true])
            @else
                @include('review.partials.highlight-card', ['highlight' => $item->highlight])
            @endif

                @include('review.partials.verdict')
            </article>
        @endforeach

        {{-- The end of the ritual. Rendered up front so arriving here needs no
             round trip (FR-043). --}}
        <div data-review-complete hidden class="py-10 text-center">
            <h2 class="text-2xl font-semibold" style="color: var(--color-ink);">
                {{ __('review.complete.title') }}
            </h2>

            <p class="mt-2 text-base" style="color: var(--color-ink-muted);">
                {{ __('review.complete.body') }}
            </p>

            <p class="mt-6 text-3xl font-semibold" style="color: var(--color-accent);" data-review-streak hidden></p>

            <x-button
                href="{{ route('home') }}"
                data-review-done
                class="mt-8 w-full"
                style="min-height: var(--size-touch-lg);"
            >
                {{ __('review.complete.done') }}
            </x-button>
        </div>

        @include('review.partials.undo-bar')
    </div>

    @vite('resources/js/review.js')
</x-layouts.app>

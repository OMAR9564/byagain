<x-layouts.app :title="$source->title">
    <x-slot:header>{{ $source->title }}</x-slot:header>

    {{-- Practice set: not recorded, not touching the schedule or streak.
         The interaction is identical to review/show, but there is no
         `data-complete-url` — completion is local only. --}}
    <div
        id="review"
        data-review
        data-start-index="0"
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

        {{-- Banner: this is a practice, not a day review. Not in a status role
             because the page title already states it. --}}
        <div class="mb-6 rounded-lg px-4 py-3" style="background-color: var(--color-surface); color: var(--color-ink-muted);">
            {{ __('practice.practice.banner') }}
        </div>

        <div class="mb-5 flex items-center gap-3">
            {{-- Going back was the missing half of swiping. A gesture that only
                 moves one way turns a slip of the thumb into a decision you
                 cannot look at again. --}}
            <button
                type="button"
                data-review-back
                hidden
                class="-ml-2 flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                style="color: var(--color-ink-muted);"
                aria-label="{{ __('review.nav.previous') }}"
            >
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                    <path d="M14.5 6.5 9 12l5.5 5.5" />
                </svg>
            </button>

            <x-progress-bar :current="0" :total="$items->count()" class="flex-1" data-review-progress />
        </div>

        @foreach ($items as $index => $item)
            @php
                $highlight = $item['type'] === 'highlight' ? $item['model'] : null;
                $card = $item['type'] === 'card' ? $item['model'] : null;
            @endphp

            <article
                class="review-card"
                data-review-card
                {{-- The review's own type names, so review.js wires a card as
                     a question (reveal, passage, Next) rather than a swipe. --}}
                data-item-type="{{ $item['type'] === 'card' ? \App\Models\ReviewItem::TYPE_MASTERY : \App\Models\ReviewItem::TYPE_HIGHLIGHT }}"
                data-item-id="{{ $item['model']->id }}"
                data-action-url="{{
                    $item['type'] === 'highlight'
                        ? route('practice.action', [$source, $highlight])
                        : route('mix.card', $card)
                }}"
                data-acted="false"
                data-verdict=""
                @if ($index !== 0) hidden @endif
            >
                @if ($item['type'] === 'highlight' && $highlight !== null)
                    @include('review.partials.highlight-card', ['highlight' => $highlight])
                @elseif ($item['type'] === 'card' && $card !== null)
                    {{-- Mastery cards in practice show no scheduling buttons,
                         like in Mix (FR-205). The card's schedule never changes
                         because practice leaves no trace (SC-201). --}}
                    @include('review.partials.mastery-card', ['card' => $card, 'domId' => 'item-'.$card->id, 'scheduling' => false])
                @endif

                @include('review.partials.verdict')
            </article>
        @endforeach

        {{-- The end of the practice set. Rendered up front so arriving here needs no
             round trip. --}}
        <div data-review-complete hidden class="py-10 text-center">
            <h2 class="text-2xl font-semibold" style="color: var(--color-ink);">
                {{ __('practice.practice.complete.title') }}
            </h2>

            <p class="mt-2 text-base" style="color: var(--color-ink-muted);">
                {{ __('practice.practice.complete.body') }}
            </p>

            <div class="mt-6 flex flex-col gap-3">
                <x-button
                    href="{{ route('practice.show', $source) }}"
                    data-review-done
                    style="min-height: var(--size-touch-lg);"
                >
                    {{ __('practice.practice.another_set') }}
                </x-button>

                <x-button
                    variant="secondary"
                    href="{{ route('sources.show', $source) }}"
                    data-review-done
                    style="min-height: var(--size-touch-lg);"
                >
                    {{ __('practice.practice.back_to_source') }}
                </x-button>
            </div>
        </div>

        @include('review.partials.undo-bar')
    </div>

    @vite('resources/js/review.js')
</x-layouts.app>

<x-layouts.app header="Mix" tabRoot :title="__('practice.mix.title')">
    {{-- Mix: endless, shuffled practice across all sources and cards.
         Like source practice, this is not recorded and never touches the day.
         The root has `data-endless-url` instead of `data-complete-url` so the
         completion screen triggers a reload instead of navigating away. --}}
    <div
        id="review"
        data-review
        data-start-index="0"
        data-endless-url="{{ route('mix.show') }}"
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

        {{-- Banner: this is endless practice, not a single set. --}}
        <p class="mb-5 text-sm" style="color: var(--color-ink-muted);">
            {{ __('practice.mix.banner') }}
        </p>

        @if ($items->isEmpty())
            <x-empty-state
                :title="__('practice.mix.empty_title')"
                :body="__('practice.mix.empty_body')"
                :action="__('actions.add')"
                :href="route('highlights.create')"
            />
        @else
            <div class="mb-5 flex items-center gap-3">
                {{-- Going back was the missing half of swiping. --}}
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
                            ? route('mix.highlight', $highlight)
                            : route('mix.card', $card)
                    }}"
                    data-acted="false"
                    data-verdict=""
                    @if ($index !== 0) hidden @endif
                >
                    @if ($item['type'] === 'highlight' && $highlight !== null)
                        @include('review.partials.highlight-card', ['highlight' => $highlight])
                    @elseif ($item['type'] === 'card' && $card !== null)
                        @include('review.partials.mastery-card', ['card' => $card, 'domId' => 'item-'.$card->id, 'scheduling' => false])
                    @endif

                    @include('review.partials.verdict')
                </article>
            @endforeach

            {{-- The end of a batch. The completion block says "loading" so the
                 reader knows what the page reload is doing. --}}
            <div data-review-complete hidden class="py-10 text-center">
                <p class="text-base" style="color: var(--color-ink-muted);">
                    {{ __('practice.mix.loading') }}
                </p>
            </div>

            @include('review.partials.undo-bar')
        @endif
    </div>

    @vite('resources/js/review.js')
</x-layouts.app>

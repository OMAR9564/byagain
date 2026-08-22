<x-layouts.app :title="__('review.title')">
    {{-- Cards are rendered server-side and hidden with [hidden], not fetched.
         The whole review is already on the page, so moving between cards
         costs nothing and works with no connection at all (FR-041). --}}
    <div
        id="review"
        data-review
        data-start-index="{{ $startIndex }}"
        data-complete-url="{{ route('review.complete') }}"
        data-csrf="{{ csrf_token() }}"
    >
        <x-progress-bar :current="$startIndex" :total="$review->items->count()" class="mb-5" data-review-progress />

        @foreach ($review->items as $index => $item)
            @continue($item->highlight === null && $item->masteryCard === null)

            <article
                class="review-card"
                data-review-card
                data-item-type="{{ $item->item_type }}"
                data-item-id="{{ $item->id }}"
                data-action-url="{{ route('review.item.action', $item) }}"
                data-acted="{{ $item->isActed() ? 'true' : 'false' }}"
                @if ($index !== $startIndex) hidden @endif
            >
            @if ($item->item_type === \App\Models\ReviewItem::TYPE_MASTERY)
                @include('review.partials.mastery-card', ['item' => $item])
            @else
                <x-card>
                    <p class="mb-3 text-sm" style="color: var(--color-ink-muted);">
                        {{ $item->highlight->source?->title }}
                        @if ($item->highlight->location !== null)
                            · {{ $item->highlight->location }}
                        @endif
                    </p>

                    <x-highlight-content :highlight="$item->highlight" />
                </x-card>

                <div class="mt-4 flex items-stretch gap-3">
                    <x-button
                        variant="secondary"
                        class="flex-1"
                        data-review-action="discard"
                        style="min-height: var(--size-touch-lg);"
                    >
                        {{ __('review.action.discard') }}
                    </x-button>

                    <x-button
                        class="flex-1"
                        data-review-action="keep"
                        style="min-height: var(--size-touch-lg);"
                    >
                        {{ __('review.action.keep') }}
                    </x-button>
                </div>

                <div class="mt-3 flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex min-h-11 flex-1 items-center justify-center text-sm"
                        style="color: var(--color-ink-muted);"
                        data-review-favorite
                        aria-pressed="{{ $item->highlight->is_favorite ? 'true' : 'false' }}"
                    >
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
                            data-initial="{{ $item->highlight->source?->frequency }}"
                            class="min-h-11 w-full rounded-lg px-2 text-sm"
                            style="background-color: var(--color-surface); color: var(--color-ink-muted); border: 1px solid var(--color-border);"
                        >
                            @foreach (\App\Http\Requests\StoreSourceRequest::frequencies() as $frequency)
                                <option value="{{ $frequency }}" @selected($item->highlight->source?->frequency === $frequency)>
                                    {{ __('library.frequency.' . $frequency) }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
            @endif
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
        </div>
    </div>

    @vite('resources/js/review.js')
</x-layouts.app>

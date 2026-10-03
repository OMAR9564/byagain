<x-layouts.app :title="$source->title" :back="route('library.index')" :back-label="__('library.title')">
    <x-slot:header>{{ $source->title }}</x-slot:header>

    <x-slot:headerAction>
        <a href="{{ route('sources.edit', $source) }}" class="bar-button pressable">
            {{ __('actions.edit') }}
        </a>

        @if (! $source->is_archived)
            <a href="{{ route('highlights.create', ['source' => $source->id]) }}"
               class="bar-button pressable">
                <span class="sr-only">{{ __('library.source.add_passage') }}</span>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
            </a>
        @endif
    </x-slot:headerAction>

    {{-- The title is the large title above; the author is its subtitle. --}}
    @if ($source->author !== null)
        <p class="-mt-2 mb-5 text-base" style="color: var(--color-ink-muted);">{{ $source->author }}</p>
    @endif

    {{-- Practice works on archived sources too: archiving only stops a
         source feeding the daily review (FR-201). The button needs a passage
         to practise, so it hides when none is active. Export also requires
         active passages and works on archived sources (R-309). --}}
    @if ($highlights->total() > 0)
        {{-- Stacked, not side by side: at half width "Practise this source"
             wraps onto two lines, and a two-line button reads as two buttons. --}}
        <div class="mb-5 flex flex-col gap-2">
            <x-button :href="route('practice.show', $source)" class="w-full">
                {{ __('practice.practice.start') }}
            </x-button>

            <x-button :href="route('sources.export', $source)" variant="tinted" class="w-full">
                {{ __('practice.export.start') }}
            </x-button>
        </div>
    @endif

    @forelse ($highlights as $highlight)
        <x-card class="mb-3">
            <x-highlight-content :highlight="$highlight" />

            <div class="mt-4 flex flex-wrap items-center gap-1">
                <x-button :href="route('highlights.edit', $highlight)" variant="ghost" size="small">
                    {{ __('actions.edit') }}
                </x-button>

                {{-- "Make a card" is always offered: one passage can carry
                     several cards, so the first card must not hide it
                     (FR-044). The count of live cards sits beside it as its
                     own small link; both keep the 44px touch height. --}}
                <x-button :href="route('mastery.create', $highlight)" variant="ghost" size="small">
                    {{ __('mastery.create.action') }}
                </x-button>

                @if ($highlight->active_mastery_cards_count > 0)
                    <a href="{{ route('mastery.index') }}"
                       class="pressable inline-flex min-h-11 items-center px-1 text-xs"
                       style="color: var(--color-ink-subtle);">
                        {{ trans_choice('mastery.create.card_count', $highlight->active_mastery_cards_count) }}
                    </a>
                @endif

                {{-- Discard hides it from future reviews without deleting it, and today's
                     review keeps showing it (FR-013). Delete removes it permanently. --}}
                <details class="ios-menu ml-auto" data-menu>
                    <summary class="bar-button pressable" aria-label="{{ __('actions.more') }}">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M7.5 12h.01M12 12h.01M16.5 12h.01" stroke-width="2.6"/>
                        </svg>
                    </summary>

                    <div class="ios-menu-panel">
                        <form method="POST" action="{{ route('highlights.favorite', $highlight) }}">
                            @csrf
                            <button type="submit" class="pressable">
                                <span>{{ $highlight->is_favorite ? __('actions.unfavorite') : __('actions.favorite') }}</span>
                                <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="{{ $highlight->is_favorite ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">
                                    <path d="M12 20.5s-8-4.9-8-11A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 8 2.5c0 6.1-8 11-8 11Z"/>
                                </svg>
                            </button>
                        </form>

                        <form method="POST" action="{{ route('highlights.discard', $highlight) }}">
                            @csrf
                            <button type="submit" class="pressable">
                                <span>{{ __('actions.discard') }}</span>
                                <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="5" rx="1.5"/>
                                    <path d="M5 9v9a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9M10 13h4"/>
                                </svg>
                            </button>
                        </form>

                        <form method="POST" action="{{ route('highlights.destroy', $highlight) }}"
                              data-confirm="{{ __('library.highlight.delete_confirm') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="pressable is-destructive">
                                <span>{{ __('actions.delete') }}</span>
                                <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        </x-card>
    @empty
        <x-empty-state
            :title="__('library.highlight.title_plural')"
            :body="__('editor.placeholder')"
            :action="__('actions.add')"
            :href="route('highlights.create')"
        />
    @endforelse

    {{ $highlights->links() }}
</x-layouts.app>

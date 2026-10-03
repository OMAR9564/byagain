<x-layouts.app :title="$source->title">
    <x-slot:header>{{ $source->title }}</x-slot:header>

    <x-slot:headerAction>
        <x-button :href="route('sources.edit', $source)" variant="ghost" class="!px-3">
            {{ __('actions.edit') }}
        </x-button>
    </x-slot:headerAction>

    @if ($source->author !== null)
        <p class="-mt-2 mb-4 text-sm" style="color: var(--color-ink-muted);">{{ $source->author }}</p>
    @endif

    {{-- Practice works on archived sources too: archiving only stops a
         source feeding the daily review (FR-201). The button needs a passage
         to practise, so it hides when none is active. Export also requires
         active passages and works on archived sources (R-309). --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        @if ($highlights->total() > 0)
            <x-button :href="route('practice.show', $source)">
                {{ __('practice.practice.start') }}
            </x-button>

            <x-button :href="route('sources.export', $source)" variant="secondary">
                {{ __('practice.export.start') }}
            </x-button>
        @endif

        @if (! $source->is_archived)
            <x-button :href="route('highlights.create', ['source' => $source->id])" variant="secondary">
                {{ __('library.source.add_passage') }}
            </x-button>
        @endif
    </div>

    @forelse ($highlights as $highlight)
        <x-card class="mb-3">
            <x-highlight-content :highlight="$highlight" />

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-button :href="route('highlights.edit', $highlight)" variant="ghost" class="!px-3 text-sm">
                    {{ __('actions.edit') }}
                </x-button>

                <form method="POST" action="{{ route('highlights.favorite', $highlight) }}">
                    @csrf
                    <x-button type="submit" variant="ghost" class="!px-3 text-sm">
                        {{ $highlight->is_favorite ? __('actions.unfavorite') : __('actions.favorite') }}
                    </x-button>
                </form>

                {{-- "Make a card" is always offered: one passage can carry
                     several cards, so the first card must not hide it
                     (FR-044). The count of live cards sits beside it as its
                     own small link; both keep the 44px touch height. --}}
                <x-button :href="route('mastery.create', $highlight)" variant="ghost" class="!px-3 text-sm">
                    {{ __('mastery.create.action') }}
                </x-button>

                @if ($highlight->active_mastery_cards_count > 0)
                    <a href="{{ route('mastery.index') }}"
                       class="inline-flex min-h-11 items-center px-1 text-xs"
                       style="color: var(--color-ink-subtle);">
                        {{ trans_choice('mastery.create.card_count', $highlight->active_mastery_cards_count) }}
                    </a>
                @endif

                {{-- Discard hides it from future reviews without deleting it, and today's
                     review keeps showing it (FR-013). Delete removes it permanently. --}}
                <form method="POST" action="{{ route('highlights.discard', $highlight) }}" class="ml-auto">
                    @csrf
                    <x-button type="submit" variant="ghost" class="!px-3 text-sm">
                        {{ __('actions.discard') }}
                    </x-button>
                </form>

                <form method="POST" action="{{ route('highlights.destroy', $highlight) }}"
                      data-confirm="{{ __('library.highlight.delete_confirm') }}"
                      class="ml-1">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="ghost" class="!px-3 text-sm" style="color: var(--color-critical);">
                        {{ __('actions.delete') }}
                    </x-button>
                </form>
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

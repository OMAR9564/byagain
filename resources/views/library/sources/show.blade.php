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

    @forelse ($highlights as $highlight)
        <x-card class="mb-3">
            <x-highlight-content :highlight="$highlight" />

            <div class="mt-4 flex items-center gap-2">
                <x-button :href="route('highlights.edit', $highlight)" variant="ghost" class="!px-3 text-sm">
                    {{ __('actions.edit') }}
                </x-button>

                <form method="POST" action="{{ route('highlights.favorite', $highlight) }}">
                    @csrf
                    <x-button type="submit" variant="ghost" class="!px-3 text-sm">
                        {{ $highlight->is_favorite ? __('actions.unfavorite') : __('actions.favorite') }}
                    </x-button>
                </form>

                {{-- Discard hides it from future reviews. It is never a
                     delete, and today's review keeps showing it (FR-013). --}}
                <form method="POST" action="{{ route('highlights.discard', $highlight) }}" class="ml-auto">
                    @csrf
                    <x-button type="submit" variant="ghost" class="!px-3 text-sm">
                        {{ __('actions.discard') }}
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

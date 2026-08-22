<x-layouts.app :title="__('library.title')">
    <x-slot:header>{{ __('library.title') }}</x-slot:header>

    <x-slot:headerAction>
        <x-button :href="route('sources.create')" variant="secondary" class="!px-3">
            {{ __('actions.add') }}
        </x-button>
    </x-slot:headerAction>

    @forelse ($sources as $source)
        <a href="{{ route('sources.show', $source) }}" class="mb-3 block">
            <x-card>
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-base font-semibold" style="color: var(--color-ink);">
                        {{ $source->title }}
                    </h2>

                    @if ($source->is_archived)
                        <span class="shrink-0 text-xs" style="color: var(--color-ink-subtle);">
                            {{ __('actions.archive') }}
                        </span>
                    @endif
                </div>

                @if ($source->author !== null)
                    <p class="mt-0.5 text-sm" style="color: var(--color-ink-muted);">{{ $source->author }}</p>
                @endif

                <p class="mt-2 text-sm" style="color: var(--color-ink-subtle);">
                    {{ trans_choice('library.source.highlight_count', $source->active_highlights_count, ['count' => $source->active_highlights_count]) }}
                    · {{ __('library.frequency.' . $source->frequency) }}
                </p>
            </x-card>
        </a>
    @empty
        <x-empty-state
            :title="__('library.empty.title')"
            :body="__('library.empty.body')"
            :action="__('library.empty.action')"
            :href="route('sources.create')"
        />
    @endforelse
</x-layouts.app>

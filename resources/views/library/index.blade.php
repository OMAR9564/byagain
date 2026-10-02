<x-layouts.app :title="__('library.title')">
    <x-slot:header>{{ __('library.title') }}</x-slot:header>

    <x-slot:headerAction>
        <x-button :href="route('sources.create')" variant="secondary" class="!px-3">
            {{ __('actions.add') }}
        </x-button>
    </x-slot:headerAction>

    {{-- Link to the question cards list. Moved from the bottom navigation to
         give more room to the new Mix tab. --}}
    <a href="{{ route('mastery.index') }}" class="mb-3 block">
        <x-card>
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold" style="color: var(--color-ink);">
                    {{ __('mastery.title') }}
                </h2>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"
                     style="color: var(--color-ink-muted);">
                    <path d="M9.5 6.5 15 12l-5.5 5.5" />
                </svg>
            </div>
            <p class="mt-1 text-sm" style="color: var(--color-ink-muted);">
                {{ __('mastery.index_body') }}
            </p>
        </x-card>
    </a>

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

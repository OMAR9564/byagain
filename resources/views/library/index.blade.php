<x-layouts.app :header="__('library.title')" tabRoot :title="__('library.title')">
    {{-- Cards sit above the sources as their own one-row group, the way iOS
         Settings puts a profile row above the lists. Moved here from the tab
         bar to make room for Mix. --}}
    <section class="ios-section">
        <div class="ios-group">
            <a href="{{ route('mastery.index') }}" class="ios-row ios-row-link">
                <span class="flex h-[30px] w-[30px] shrink-0 items-center justify-center rounded-[7px]"
                      style="background-color: var(--color-accent); color: var(--color-accent-ink);">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="h-[18px] w-[18px]">
                        <rect x="3.5" y="7" width="13" height="13" rx="2.2" />
                        <path d="M8 4h10.3A2.2 2.2 0 0 1 20.5 6.2V16" />
                    </svg>
                </span>

                <span class="ios-row-body">
                    <span class="ios-row-title block">{{ __('mastery.title') }}</span>
                    <span class="ios-row-subtitle block">{{ __('mastery.index_body') }}</span>
                </span>

                <svg aria-hidden="true" class="ios-chevron" viewBox="0 0 8 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 1l6 6-6 6" />
                </svg>
            </a>
        </div>
    </section>

    @if ($sources->isEmpty())
        <x-empty-state
            :title="__('library.empty.title')"
            :body="__('library.empty.body')"
            :action="__('library.empty.action')"
            :href="route('sources.create')"
        />
    @else
        <section class="ios-section">
            <h2 class="ios-section-header">{{ __('library.sources_header') }}</h2>

            <div class="ios-group">
                @foreach ($sources as $source)
                    <a href="{{ route('sources.show', $source) }}" class="ios-row ios-row-link">
                        <span class="ios-row-body">
                            <span class="ios-row-title block font-medium">{{ $source->title }}</span>

                            @if ($source->author !== null)
                                <span class="ios-row-subtitle block">{{ $source->author }}</span>
                            @endif

                            <span class="mt-0.5 block text-xs" style="color: var(--color-ink-muted);">
                                {{ trans_choice('library.source.highlight_count', $source->active_highlights_count, ['count' => $source->active_highlights_count]) }}
                                · {{ __('library.frequency.' . $source->frequency) }}
                            </span>
                        </span>

                        @if ($source->is_archived)
                            <span class="ios-row-detail text-sm">{{ __('actions.archive') }}</span>
                        @endif

                        <svg aria-hidden="true" class="ios-chevron" viewBox="0 0 8 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 1l6 6-6 6" />
                        </svg>
                    </a>
                @endforeach

                {{-- The way to grow the list lives at its end, as in iOS's own
                     "Add Account" rows: adding a passage is the bar's "+". --}}
                <a href="{{ route('sources.create') }}" class="ios-row ios-row-link ios-row--action">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="currentColor" class="h-[22px] w-[22px] shrink-0">
                        <path d="M12 1.75a10.25 10.25 0 1 0 0 20.5 10.25 10.25 0 0 0 0-20.5zm.9 5.6v3.75h3.75a.9.9 0 1 1 0 1.8H12.9v3.75a.9.9 0 1 1-1.8 0V12.9H7.35a.9.9 0 1 1 0-1.8h3.75V7.35a.9.9 0 1 1 1.8 0z" />
                    </svg>
                    <span>{{ __('library.source.new') }}</span>
                </a>
            </div>
        </section>
    @endif
</x-layouts.app>

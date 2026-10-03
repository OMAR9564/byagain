<x-layouts.app header="Library" tabRoot :title="__('library.title')">
    {{-- Cards (Mastery) link section --}}
    <div class="ios-section">
        <div class="ios-group">
            <a href="{{ route('mastery.index') }}" class="ios-row ios-row-link pressable">
                <div style="width: 29px; height: 29px; border-radius: 7px; background-color: var(--color-accent); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="white"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;">
                        <rect x="6" y="4" width="4" height="4" rx="0.5" />
                        <rect x="6" y="10" width="4" height="4" rx="0.5" />
                        <rect x="14" y="4" width="4" height="4" rx="0.5" />
                        <rect x="14" y="10" width="4" height="4" rx="0.5" />
                    </svg>
                </div>
                <div class="ios-row-body">
                    <div class="ios-row-title">{{ __('mastery.title') }}</div>
                    <div class="ios-row-subtitle">{{ __('mastery.index_body') }}</div>
                </div>
                <svg class="ios-chevron" viewBox="0 0 8 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                    <path d="M1 1l6 6-6 6"/>
                </svg>
            </a>
        </div>
    </div>

    {{-- Sources section --}}
    @if ($sources->isNotEmpty() || true)
        <div class="ios-section">
            <div class="ios-section-header">{{ __('library.sources_header') }}</div>
            <div class="ios-group">
                @forelse ($sources as $source)
                    <a href="{{ route('sources.show', $source) }}" class="ios-row ios-row-link pressable">
                        <div class="ios-row-body">
                            <div class="ios-row-title" style="font-weight: 500;">{{ $source->title }}</div>
                            @if ($source->author !== null)
                                <div class="ios-row-subtitle">{{ $source->author }}</div>
                            @endif
                            <div class="ios-row-subtitle">
                                {{ trans_choice('library.source.highlight_count', $source->active_highlights_count, ['count' => $source->active_highlights_count]) }}
                                · {{ __('library.frequency.' . $source->frequency) }}
                            </div>
                            @if ($source->is_archived)
                                <div class="ios-row-detail text-sm">{{ __('actions.archive') }}</div>
                            @endif
                        </div>
                        <svg class="ios-chevron" viewBox="0 0 8 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                            <path d="M1 1l6 6-6 6"/>
                        </svg>
                    </a>
                @empty
                @endforelse
                <a href="{{ route('sources.create') }}" class="ios-row ios-row-link ios-row--action pressable">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px; flex-shrink: 0;">
                        <circle cx="12" cy="12" r="11" />
                        <path d="M12 7v10M7 12h10" />
                    </svg>
                    <span>{{ __('library.source.new') }}</span>
                </a>
            </div>
        </div>
    @endif
</x-layouts.app>

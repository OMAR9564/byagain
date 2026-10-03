<x-layouts.app :title="__('mastery.title')" :back="route('library.index')" :back-label="__('library.title')">
    <x-slot:header>{{ __('mastery.title') }}</x-slot:header>

    @if ($cards->isNotEmpty())
        <div class="ios-group mt-2 mb-4" style="overflow: visible;">
            @foreach ($cards as $card)
                <div class="ios-row-wrap px-4 py-3">
                    <p class="mb-1 text-xs" style="color: var(--color-ink-muted);">
                        {{ $card->highlight?->source?->title }} · {{ __('mastery.status.' . $card->status) }}
                        @if ($card->due_at !== null)
                            · {{ $card->due_at->diffForHumans() }}
                        @endif
                    </p>

                    <p class="text-base" style="color: var(--color-ink);">{{ $card->question }}</p>

                    <p class="mt-1 text-sm" style="color: var(--color-ink-muted);">{{ $card->answer }}</p>

                    <div class="mt-2 -ml-3.5 flex items-center gap-1">
                        <x-button :href="route('mastery.edit', $card)" variant="ghost" size="small">
                            {{ __('actions.edit') }}
                        </x-button>

                        <details class="ios-menu ml-auto" data-menu>
                            <summary class="bar-button pressable" aria-label="{{ __('actions.more') }}">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M7.5 12h.01M12 12h.01M16.5 12h.01" stroke-width="2.6"/>
                                </svg>
                            </summary>

                            <div class="ios-menu-panel">
                                @if ($card->status !== \App\Models\MasteryCard::STATUS_RETIRED)
                                    <form method="POST" action="{{ route('mastery.retire', $card) }}">
                                        @csrf
                                        <button type="submit" class="pressable">
                                            <span>{{ __('mastery.feedback.learned') }}</span>
                                            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="9"/>
                                                <path d="m8 12.5 3 3 5-6"/>
                                            </svg>
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('mastery.destroy', $card) }}"
                                      data-confirm="{{ __('mastery.delete_confirm') }}">
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
                </div>
            @endforeach
        </div>
    @else
        <x-empty-state :title="__('mastery.empty.title')" :body="__('mastery.empty.body')" />
    @endif

    {{ $cards->links() }}
</x-layouts.app>

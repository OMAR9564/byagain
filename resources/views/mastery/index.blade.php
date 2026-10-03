<x-layouts.app :title="__('mastery.title')">
    <x-slot:header>{{ __('mastery.title') }}</x-slot:header>

    @forelse ($cards as $card)
        <x-card class="mb-3">
            <p class="mb-2 text-xs" style="color: var(--color-ink-subtle);">
                {{ $card->highlight?->source?->title }} · {{ __('mastery.status.' . $card->status) }}
                @if ($card->due_at !== null)
                    · {{ $card->due_at->diffForHumans() }}
                @endif
            </p>

            <p class="text-base" style="color: var(--color-ink);">{{ $card->question }}</p>

            <p class="mt-1 text-base" style="color: var(--color-ink-muted);">{{ $card->answer }}</p>

            <div class="mt-4 flex items-center gap-2">
                <x-button :href="route('mastery.edit', $card)" variant="ghost" class="!px-3 text-sm">
                    {{ __('actions.edit') }}
                </x-button>

                <div class="ml-auto flex items-center gap-2">
                    @if ($card->status !== \App\Models\MasteryCard::STATUS_RETIRED)
                        <form method="POST" action="{{ route('mastery.retire', $card) }}">
                            @csrf
                            <x-button type="submit" variant="ghost" class="!px-3 text-sm">
                                {{ __('mastery.feedback.learned') }}
                            </x-button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('mastery.destroy', $card) }}"
                          data-confirm="{{ __('mastery.delete_confirm') }}">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="ghost" class="!px-3 text-sm" style="color: var(--color-critical);">
                            {{ __('actions.delete') }}
                        </x-button>
                    </form>
                </div>
            </div>
        </x-card>
    @empty
        <x-empty-state :title="__('mastery.empty.title')" :body="__('mastery.empty.body')" />
    @endforelse

    {{ $cards->links() }}
</x-layouts.app>

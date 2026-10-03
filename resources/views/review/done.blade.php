<x-layouts.app greet tabRoot :title="__('review.title')">
    {{-- The day is finished, and this screen says so instead of dealing
         another hand. Everything the product promises rests on finishing
         being possible (FR-043).

         One more round is still available — behind a tap, phrased as a
         choice, and never as the thing that happens if you keep opening the
         app. --}}
    <div class="flex flex-col items-center px-2 py-12 text-center">
        {{-- Three lines with the middle one struck through: the mark, done.
             Drawn rather than a tick, because a tick is what a form says. --}}
        <svg aria-hidden="true" viewBox="0 0 48 48" fill="none" class="mb-6 h-12 w-12"
             stroke="currentColor" stroke-width="3" stroke-linecap="round"
             style="color: var(--color-accent);">
            <path d="M10 15h28" opacity="0.35" />
            <path d="M10 24h20" />
            <path d="M10 33h14" opacity="0.35" />
        </svg>

        <h2 class="text-2xl font-bold" style="color: var(--color-ink);">
            {{ __('review.done.title') }}
        </h2>

        <p class="mt-2 max-w-xs text-base" style="color: var(--color-ink-muted);">
            {{ __('review.done.body') }}
        </p>

        @if ($streak > 0)
            <p class="mt-6">
                <x-streak-badge :count="$streak" />
            </p>
        @endif

        @if ($rounds > 1)
            <p class="mt-4 text-sm" style="color: var(--color-ink-subtle);">
                {{ trans_choice('review.done.rounds', $rounds, ['count' => $rounds]) }}
            </p>
        @endif

        {{-- Three ends, and they say different things. The day may be closed
             because the reader has had every round they asked for, or open but
             out of passages to fill one with. Reading either off the other is
             what went wrong before: while `show()` still opened rounds by
             itself, landing on this screen proved the material had run out, so
             `$rounds < $limit` stood in for the question. It no longer does
             (contracts/review-completion.md). --}}
        @if (! $canRepeat)
            {{-- Closed. No button, and pointedly not the "nothing new to draw
                 on" line either: material is not why the day ended (FR-104). --}}
            <p class="mt-8 max-w-xs text-sm" style="color: var(--color-ink-subtle);">
                {{ $rounds >= $limit ? __('review.done.closed') : __('review.done.ceiling') }}
            </p>
        @elseif (! $hasMaterial)
            {{-- Room left in the day, but every eligible passage is inside its
                 cooldown. Said plainly, and with no button that would only
                 come back with this same sentence (FR-029). --}}
            <p class="mt-8 max-w-xs text-sm" style="color: var(--color-ink-subtle);">
                {{ __('review.again.exhausted') }}
            </p>
        @else
            <form method="POST" action="{{ route('review.again') }}" class="mt-10 w-full max-w-xs">
                @csrf

                {{-- Secondary, deliberately. The primary action on this screen
                     is leaving. --}}
                <x-button type="submit" variant="secondary" class="w-full">
                    {{ __('review.done.again') }}
                </x-button>

                <p class="mt-2 text-xs" style="color: var(--color-ink-subtle);">
                    {{ __('review.done.again_help') }}
                </p>
            </form>
        @endif
    </div>
</x-layouts.app>

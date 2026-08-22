<x-layouts.app :title="__('review.title')">
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

        <h1 class="text-2xl font-semibold tracking-tight" style="color: var(--color-ink);">
            {{ __('review.done.title') }}
        </h1>

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

        @if ($rounds < $limit)
            {{-- They asked for more reviews a day than there is material for
                 today. Said plainly, and with no button that would only fail
                 the same way. --}}
            <p class="mt-8 max-w-xs text-sm" style="color: var(--color-ink-subtle);">
                {{ __('review.again.exhausted') }}
            </p>
        @elseif ($canRepeat)
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

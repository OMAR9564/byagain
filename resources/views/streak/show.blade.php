<x-layouts.app header="Streak" tabRoot :title="__('streak.title')">
    <x-card class="text-center">
        <p class="text-sm" style="color: var(--color-ink-muted);">{{ __('streak.current') }}</p>

        <p class="mt-1 text-3xl font-bold" style="color: var(--color-accent);">
            {{ trans_choice('streak.days', $current, ['count' => $current]) }}
        </p>

        <p class="mt-3 text-sm" style="color: var(--color-ink-muted);">
            {{ __('streak.longest') }}:
            {{ trans_choice('streak.days', $longest, ['count' => $longest]) }}
        </p>
    </x-card>

    {{-- Stated, never scolded. A broken streak is information, not a
         judgement (FR-058). --}}
    @if ($current === 0)
        <p class="mt-4 text-center text-base" style="color: var(--color-ink-muted);">
            {{ $longest > 0 ? __('streak.broken') : __('streak.none') }}
        </p>
    @endif

    <section class="mt-8">
        <h2 class="mb-3 text-base font-semibold" style="color: var(--color-ink);">
            {{ __('streak.calendar.title', ['count' => count($calendar)]) }}
        </h2>

        {{-- Seven to a row, so a row is a week and the grid gains one as the
             streak does. No weekday letters: the window rolls, so the columns
             are not weekdays and pretending otherwise would be a lie the eye
             notices. --}}
        <ul class="grid grid-cols-7 gap-1.5" role="list">
            @foreach ($calendar as $index => $day)
                @php $isToday = $index === count($calendar) - 1; @endphp

                <li
                    class="aspect-square rounded-[8px]"
                    style="
                        background-color: {{ $day['done'] ? 'var(--color-accent)' : 'var(--color-fill)' }};
                        @if ($isToday) border: 2px solid var(--color-accent); @endif
                    "
                    title="{{ $day['date'] }} — {{ $day['done'] ? __('streak.calendar.done') : __('streak.calendar.missed') }}"
                >
                    <span class="sr-only">
                        {{ $isToday ? __('streak.calendar.today') . ', ' : '' }}{{ $day['date'] }}:
                        {{ $day['done'] ? __('streak.calendar.done') : __('streak.calendar.missed') }}
                    </span>
                </li>
            @endforeach
        </ul>

        @if ($growsAt !== null)
            <p class="mt-3 text-xs" style="color: var(--color-ink-subtle);">
                {{ __('streak.calendar.grows', ['count' => $growsAt]) }}
            </p>
        @endif
    </section>
</x-layouts.app>

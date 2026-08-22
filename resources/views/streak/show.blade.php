<x-layouts.app :title="__('streak.title')">
    <x-slot:header>{{ __('streak.title') }}</x-slot:header>

    <x-card class="text-center">
        <p class="text-sm" style="color: var(--color-ink-muted);">{{ __('streak.current') }}</p>

        <p class="mt-1 text-3xl font-semibold" style="color: var(--color-accent);">
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

        <ul class="grid grid-cols-7 gap-1.5" role="list">
            @foreach ($calendar as $day)
                <li
                    class="aspect-square rounded-md"
                    style="background-color: {{ $day['done'] ? 'var(--color-accent)' : 'var(--color-border)' }};"
                    title="{{ $day['date'] }} — {{ $day['done'] ? __('streak.calendar.done') : __('streak.calendar.missed') }}"
                >
                    <span class="sr-only">
                        {{ $day['date'] }}: {{ $day['done'] ? __('streak.calendar.done') : __('streak.calendar.missed') }}
                    </span>
                </li>
            @endforeach
        </ul>
    </section>
</x-layouts.app>

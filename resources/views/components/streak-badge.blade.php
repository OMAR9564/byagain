@props(['count' => 0])

@if ($count > 0)
    <a
        href="{{ route('streak.show') }}"
        {{ $attributes->merge(['class' => 'pressable inline-flex min-h-11 items-center gap-1.5 rounded-full px-3 text-sm font-medium']) }}
        style="background-color: var(--color-accent-wash); color: var(--color-accent);"
    >
        {{-- The Streak tab's own flame, so the badge reads as a door to it. --}}
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
            <path d="M12 3.5c3 3.4 5 5.6 5 8.4a5 5 0 0 1-10 0c0-2.8 2-5 5-8.4z" />
            <path d="M12 14.6c1.1 0 1.9-.7 1.9-1.7 0-.9-.7-1.6-1.9-2.9-1.2 1.3-1.9 2-1.9 2.9 0 1 .8 1.7 1.9 1.7z" />
        </svg>

        {{ trans_choice('streak.days', $count, ['count' => $count]) }}
    </a>
@endif

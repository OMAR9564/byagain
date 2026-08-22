@props(['count' => 0])

@if ($count > 0)
    <a
        href="{{ route('streak.show') }}"
        {{ $attributes->merge(['class' => 'inline-flex min-h-11 items-center gap-1.5 rounded-full px-3 text-sm font-medium']) }}
        style="background-color: var(--color-accent-wash); color: var(--color-accent);"
    >
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.75" stroke-linecap="round" class="h-4 w-4">
            <path d="M12 3v4M5 12H3m18 0h-2M12 21v-4" />
        </svg>

        {{ trans_choice('streak.days', $count, ['count' => $count]) }}
    </a>
@endif

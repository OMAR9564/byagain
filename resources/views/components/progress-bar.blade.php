@props([
    'current' => 0,
    'total' => 1,
])

@php
    $total = max(1, (int) $total);
    $percent = min(100, (int) round(((int) $current / $total) * 100));
@endphp

{{-- A bar, not a counter. "3 of 8" turns a two-minute ritual into a chore with
     a known cost; a filling bar just says you are getting there (FR-043).

     The exact numbers still reach assistive technology, which needs them. --}}
<div
    role="progressbar"
    aria-valuenow="{{ (int) $current }}"
    aria-valuemin="0"
    aria-valuemax="{{ $total }}"
    aria-label="{{ __('review.progress', ['current' => (int) $current, 'total' => $total]) }}"
    {{ $attributes->merge(['class' => 'h-1.5 w-full overflow-hidden rounded-full']) }}
    style="background-color: var(--color-border);"
>
    <div
        class="h-full rounded-full transition-[width]"
        style="width: {{ $percent }}%; background-color: var(--color-accent); transition-duration: var(--duration-card); transition-timing-function: var(--ease-out);"
    ></div>
</div>

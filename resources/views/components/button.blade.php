@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
])

@php
    // 44px minimum height on every variant — WCAG 2.5.5, and the difference
    // between a thumb landing and a thumb missing.
    $base = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-5 text-base '
        . 'font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50';

    $styles = match ($variant) {
        'primary' => 'background-color: var(--color-accent); color: var(--color-accent-ink);',
        'secondary' => 'background-color: var(--color-surface); color: var(--color-ink); '
            . 'border: 1px solid var(--color-border-strong);',
        'ghost' => 'background-color: transparent; color: var(--color-ink-muted);',
        'danger' => 'background-color: transparent; color: var(--color-critical); '
            . 'border: 1px solid var(--color-critical);',
        default => '',
    };
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $base, 'style' => $styles]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $base, 'style' => $styles]) }}>
        {{ $slot }}
    </button>
@endif

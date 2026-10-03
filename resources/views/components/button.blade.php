@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'size' => 'regular',
])

@php
    // iOS-style buttons with press feedback.
    $base = 'inline-flex items-center justify-center gap-2 font-semibold pressable disabled:cursor-not-allowed disabled:opacity-40';

    $sizeClasses = match ($size) {
        'small' => 'min-h-11 rounded-[10px] px-3.5 text-sm',
        'regular' => 'min-h-[50px] rounded-[12px] px-5 text-base',
        default => 'min-h-[50px] rounded-[12px] px-5 text-base',
    };

    $styles = match ($variant) {
        'primary' => 'background-color: var(--color-accent); color: var(--color-accent-ink);',
        'secondary' => 'background-color: var(--color-fill); color: var(--color-ink);',
        'ghost' => 'background-color: transparent; color: var(--color-accent); font-weight: 500;',
        'tinted' => 'background-color: var(--color-accent-wash); color: var(--color-accent);',
        'danger' => 'background-color: color-mix(in srgb, var(--color-critical) 12%, transparent); color: var(--color-critical);',
        default => '',
    };
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "$base $sizeClasses", 'style' => $styles]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "$base $sizeClasses", 'style' => $styles]) }}>
        {{ $slot }}
    </button>
@endif

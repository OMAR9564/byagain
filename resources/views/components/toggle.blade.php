@props([
    'name',
    'label',
    'help' => null,
    'checked' => false,
    'disabled' => false,
])

@php
    $id = $attributes->get('id', $name);
    $helpId = $id . '-help';
@endphp

<div class="ios-row-wrap">
    {{-- Dimmed rather than hidden when disabled: a reader who cannot use this
         yet is better served by seeing it exists and why (FR-149). --}}
    <label for="{{ $id }}" class="ios-row"
           @if ($disabled) style="opacity: 0.55;" @endif>
        <span class="flex-1">{{ $label }}</span>
        <input
            id="{{ $id }}"
            type="checkbox"
            role="switch"
            name="{{ $name }}"
            value="1"
            class="ios-switch"
            @checked(old($name, $checked))
            @disabled($disabled)
            @if ($help !== null) aria-describedby="{{ $helpId }}" @endif
            {{ $attributes->except('id') }}
        >
    </label>

    @if ($help !== null)
        <p id="{{ $helpId }}" class="px-4 pb-3 -mt-1 text-xs" style="color: var(--color-ink-muted);">{{ $help }}</p>
    @endif
</div>

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

<div class="flex flex-col gap-1">
    {{-- Dimmed rather than hidden when disabled: a reader who cannot use this
         yet is better served by seeing it exists and why (FR-149). --}}
    <label for="{{ $id }}" class="flex min-h-11 items-center gap-3 text-base"
           style="color: var(--color-ink); @if ($disabled) opacity: 0.55; @endif">
        <input
            id="{{ $id }}"
            type="checkbox"
            name="{{ $name }}"
            value="1"
            class="h-5 w-5 shrink-0 rounded"
            style="accent-color: var(--color-accent);"
            @checked(old($name, $checked))
            @disabled($disabled)
            @if ($help !== null) aria-describedby="{{ $helpId }}" @endif
            {{ $attributes->except('id') }}
        >
        <span>{{ $label }}</span>
    </label>

    @if ($help !== null)
        <p id="{{ $helpId }}" class="pl-8 text-sm" style="color: var(--color-ink-muted);">{{ $help }}</p>
    @endif
</div>

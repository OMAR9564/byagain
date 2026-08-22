@props([
    'name',
    'label',
    'help' => null,
    'checked' => false,
])

@php
    $id = $attributes->get('id', $name);
    $helpId = $id . '-help';
@endphp

<div class="flex flex-col gap-1">
    <label for="{{ $id }}" class="flex min-h-11 items-center gap-3 text-base" style="color: var(--color-ink);">
        <input
            id="{{ $id }}"
            type="checkbox"
            name="{{ $name }}"
            value="1"
            class="h-5 w-5 shrink-0 rounded"
            style="accent-color: var(--color-accent);"
            @checked(old($name, $checked))
            @if ($help !== null) aria-describedby="{{ $helpId }}" @endif
        >
        <span>{{ $label }}</span>
    </label>

    @if ($help !== null)
        <p id="{{ $helpId }}" class="pl-8 text-sm" style="color: var(--color-ink-muted);">{{ $help }}</p>
    @endif
</div>

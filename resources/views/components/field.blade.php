@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'autocomplete' => null,
    'help' => null,
])

@php
    $id = $attributes->get('id', $name);
    $errorId = $id . '-error';
    $helpId = $id . '-help';
    $hasError = $errors->has($name);

    $describedBy = collect([
        $help !== null ? $helpId : null,
        $hasError ? $errorId : null,
    ])->filter()->implode(' ');
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-sm font-medium" style="color: var(--color-ink);">
        {{ $label }}
    </label>

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @if ($required) required @endif
        @if ($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'min-h-11 w-full rounded-lg px-3.5']) }}
        style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid {{ $hasError ? 'var(--color-critical)' : 'var(--color-border-strong)' }};"
    >

    @if ($help !== null)
        <p id="{{ $helpId }}" class="text-sm" style="color: var(--color-ink-muted);">{{ $help }}</p>
    @endif

    @error($name)
        <p id="{{ $errorId }}" class="text-sm" style="color: var(--color-critical);">{{ $message }}</p>
    @enderror
</div>

@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'autocomplete' => null,
    'help' => null,
    'inline' => false,
    'helpOutside' => false,
])

@php
    $id = $attributes->get('id', $name);
    $errorId = $id . '-error';
    $helpId = $id . '-help';
    $hasError = $errors->has($name);

    $describedBy = collect([
        $help !== null && !$inline ? $helpId : null,
        $hasError ? $errorId : null,
    ])->filter()->implode(' ');
@endphp

@if ($inline)
    {{-- Inline mode: renders as an ios-row inside a group. --}}
    <label for="{{ $id }}" class="ios-row">
        <span class="flex-1">{{ $label }}</span>
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $value) }}"
            @if ($required) required @endif
            @if ($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            class="ios-input ios-input--trailing"
            style="max-width: 45%;"
            {{ $attributes->except('id') }}
        >
        @if ($hasError)
            <p id="{{ $errorId }}" class="text-xs mt-0.5" style="color: var(--color-critical); position: absolute; bottom: -20px;">{{ $errors->first($name) }}</p>
        @endif
    </label>
@else
    {{-- Stacked mode: label above, input below. --}}
    <div class="flex flex-col gap-1.5">
        <label for="{{ $id }}" class="text-sm font-medium px-1" style="color: var(--color-ink);">
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
            class="ios-field"
            {{ $attributes->except('id') }}
        >

        @if ($help !== null)
            <p id="{{ $helpId }}" class="text-sm" style="color: var(--color-ink-muted);">{{ $help }}</p>
        @endif

        @error($name)
            <p id="{{ $errorId }}" class="text-sm" style="color: var(--color-critical);">{{ $message }}</p>
        @enderror
    </div>
@endif

@props(['as' => 'div'])

<{{ $as }}
    {{ $attributes->merge(['class' => 'rounded-2xl p-5']) }}
    style="background-color: var(--color-surface); border: 1px solid var(--color-border); box-shadow: var(--shadow-card);"
>
    {{ $slot }}
</{{ $as }}>

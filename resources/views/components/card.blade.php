@props(['as' => 'div'])

<{{ $as }}
    {{ $attributes->merge(['class' => 'rounded-[18px] p-5']) }}
    style="background-color: var(--color-surface); box-shadow: var(--shadow-card);"
>
    {{ $slot }}
</{{ $as }}>

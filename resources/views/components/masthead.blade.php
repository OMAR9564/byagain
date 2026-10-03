{{-- `as` is the element the greeting is rendered in. On the home screen it is
     the page's h1, because there the greeting is the whole heading; everywhere
     else the screen has its own title and this is a paragraph. --}}
@props(['as' => 'p'])

@php
    use App\Services\Identity\Greeting;

    $user = auth()->user();
    $greeting = $user === null ? null : app(Greeting::class);
    $endearment = $greeting?->endearment($user);
@endphp

@if ($user !== null && $greeting !== null)
    {{-- The greeting block under the navbar on tab roots. The salute may be
         muted, but name and endearment are the same weight and color as a title. --}}
    <h1 class="large-title" data-large-title>
        {{ __($greeting->saluteKey($user)) }},
        {{ $greeting->name($user) }}
    </h1>

    @if ($endearment !== null)
        {{-- A note left for one person. Different every day, and nowhere near
             anything the product needs to work. --}}
        <p class="text-sm mt-1" style="color: var(--color-accent);">
            {{ $endearment }}
        </p>
    @endif
@endif

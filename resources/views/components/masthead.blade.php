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
    {{-- The strip above every signed-in screen: who is here on the left, the
         one place to change how any of this behaves on the right.

         It is chrome, not a label for the page title — which is why it is a
         row with its own business rather than a line stacked on the heading.
         Settings had no entry point in the app at all before this. --}}
    <div class="flex items-center justify-between gap-3 pt-1 pb-3">
        <{{ $as }} @class([
            'min-w-0 leading-snug',
            'text-xl tracking-tight' => $as === 'h1',
            'text-sm' => $as !== 'h1',
        ]) style="color: var(--color-ink-muted);">
            {{ __($greeting->saluteKey($user)) }},
            <span class="font-semibold" style="color: var(--color-ink);">{{ $greeting->name($user) }}</span>

            @if ($endearment !== null)
                {{-- A note left for one person. Different every day, and
                     nowhere near anything the product needs to work. --}}
                <span @class(['mt-0.5 block truncate', 'text-sm' => $as === 'h1', 'text-xs' => $as !== 'h1'])
                      style="color: var(--color-accent);">
                    {{ $endearment }}
                </span>
            @endif
        </{{ $as }}>

        <a
            href="{{ route('settings.edit') }}"
            class="-mr-2 flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
            style="color: var(--color-ink-subtle);"
            aria-label="{{ __('settings.title') }}"
            @if (request()->routeIs('settings.edit')) aria-current="page" @endif
        >
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                <circle cx="12" cy="12" r="3.1" />
                <path d="M19.4 14.2a1.6 1.6 0 0 0 .32 1.77l.06.06a1.94 1.94 0 1 1-2.74 2.74l-.06-.06a1.6 1.6 0 0 0-1.77-.32 1.6 1.6 0 0 0-.97 1.47v.17a1.94 1.94 0 0 1-3.88 0v-.09a1.6 1.6 0 0 0-1.05-1.47 1.6 1.6 0 0 0-1.77.32l-.06.06a1.94 1.94 0 1 1-2.74-2.74l.06-.06a1.6 1.6 0 0 0 .32-1.77 1.6 1.6 0 0 0-1.47-.97H3.5a1.94 1.94 0 0 1 0-3.88h.09a1.6 1.6 0 0 0 1.47-1.05 1.6 1.6 0 0 0-.32-1.77l-.06-.06a1.94 1.94 0 1 1 2.74-2.74l.06.06a1.6 1.6 0 0 0 1.77.32h.08a1.6 1.6 0 0 0 .97-1.47V3.5a1.94 1.94 0 0 1 3.88 0v.09a1.6 1.6 0 0 0 .97 1.47 1.6 1.6 0 0 0 1.77-.32l.06-.06a1.94 1.94 0 1 1 2.74 2.74l-.06.06a1.6 1.6 0 0 0-.32 1.77v.08a1.6 1.6 0 0 0 1.47.97h.17a1.94 1.94 0 0 1 0 3.88h-.09a1.6 1.6 0 0 0-1.47.97z" />
            </svg>
        </a>
    </div>
@endif

@props(['mastheadAs' => 'p', 'greet' => false, 'back' => null, 'backLabel' => null, 'inline' => false, 'tabRoot' => false, 'cancel' => null, 'header' => null, 'headerAction' => null, 'cancelLabel' => null])

{{-- The signed-in shell.

     Built like an iOS screen: a navigation bar whose large title folds into it
     on scroll, one column of content, and the sections in a tab bar within
     thumb reach (FR-077, FR-078). Inside the iPhone app the tab bar is the
     native one, so the page leaves its own out — see `data-shell` below.

     Deliberately loads only the user bundle. No Livewire, no Alpine, no
     Filament asset appears on this page (SC-018). --}}
{{-- No height class on `html` or `body`, deliberately.

     `h-full` / `min-h-full` tied both to the viewport height, which on a phone
     is a moving number: the address bar collapses, the viewport grows, and a
     shell pegged to it is relaid out mid-scroll while a `position: fixed` bar
     is trying to stay still. The page is as tall as its content; the canvas
     colour comes from `html` in app.css, so there is no white gap under a
     short one (issue #2, R-202). --}}
{{-- `data-shell` is decided here from the user agent rather than left to the
     app's injected script: at document start the script runs before <html>
     exists, and a page painted without the flag shows the web tab bar under
     the native one until it catches up. The script still sets it, for pages
     the service worker serves from cache. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if (\App\Services\Shell\NativeShell::hasTabBar(request())) data-shell="ios" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f4f1ec" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#121110" media="(prefers-color-scheme: dark)">
    <title>{{ isset($title) ? $title . ' · ' . config('app.name') : config('app.name') }}</title>

    <link rel="manifest" href="{{ url('manifest.json') }}">

    {{-- SVG first for browsers that take it — it stays sharp at any size.
         The PNGs are the fallback, and apple-touch-icon is what iOS uses when
         the app is added to the home screen. --}}
    <link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('icons/favicon-32.png') }}" sizes="32x32" type="image/png">
    <link rel="icon" href="{{ asset('icons/favicon-16.png') }}" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">

    {{-- Applied before first paint so a dark-mode user never gets a white
         flash on the way in (FR-083). --}}
    <script>
        (function () {
            var stored = localStorage.getItem('byagain.theme');
            if (stored === 'dark' || stored === 'light') {
                document.documentElement.dataset.theme = stored;
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:px-4 focus:py-2"
       style="background-color: var(--color-surface); color: var(--color-ink);">
        {{ __('actions.next') }}
    </a>

    @php
        $navTitle = $header ?? ($greet ? __('nav.review') : '');
    @endphp

    <header data-navbar @if($inline) data-inline @endif>
        <div class="navbar-row">
            <div class="navbar-leading">
                @if ($cancel)
                    <a href="{{ $cancel }}" class="bar-button pressable" data-sheet-dismiss>
                        {{ $cancelLabel ?? __('actions.cancel') }}
                    </a>
                @elseif ($back)
                    <a href="{{ $back }}" class="bar-button bar-button--back pressable">
                        <svg viewBox="0 0 13 22" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 2 2 11l9 9"/>
                        </svg>
                        <span class="truncate">{{ $backLabel ?? __('actions.back') }}</span>
                    </a>
                @endif
            </div>
            <p class="navbar-title" aria-hidden="true">{{ $navTitle }}</p>
            <div class="navbar-trailing">
                {{ $headerAction ?? '' }}
                @if ($tabRoot)
                    <a href="{{ route('highlights.create') }}" class="bar-button pressable" aria-label="{{ __('nav.add') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                    </a>
                    <a href="{{ route('settings.edit') }}" class="bar-button pressable" aria-label="{{ __('settings.title') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3.1"/>
                            <path d="M19.4 14.2a1.6 1.6 0 0 0 .32 1.77l.06.06a1.94 1.94 0 1 1-2.74 2.74l-.06-.06a1.6 1.6 0 0 0-1.77-.32 1.6 1.6 0 0 0-.97 1.47v.17a1.94 1.94 0 0 1-3.88 0v-.09a1.6 1.6 0 0 0-1.05-1.47 1.6 1.6 0 0 0-1.77.32l-.06.06a1.94 1.94 0 1 1-2.74-2.74l.06-.06a1.6 1.6 0 0 0 .32-1.77 1.6 1.6 0 0 0-1.47-.97H3.5a1.94 1.94 0 0 1 0-3.88h.09a1.6 1.6 0 0 0 1.47-1.05 1.6 1.6 0 0 0-.32-1.77l-.06-.06a1.94 1.94 0 1 1 2.74-2.74l.06.06a1.6 1.6 0 0 0 1.77.32h.08a1.6 1.6 0 0 0 .97-1.47V3.5a1.94 1.94 0 0 1 3.88 0v.09a1.6 1.6 0 0 0 .97 1.47 1.6 1.6 0 0 0 1.77-.32l.06-.06a1.94 1.94 0 1 1 2.74 2.74l-.06.06a1.6 1.6 0 0 0-.32 1.77v.08a1.6 1.6 0 0 0 1.47.97h.17a1.94 1.94 0 0 1 0 3.88h-.09a1.6 1.6 0 0 0-1.47.97z"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>
    </header>

    <main id="main" class="mx-auto w-full max-w-md px-4">
        @unless($inline)
            <div class="pt-1 pb-3">
                @if($greet)
                    <x-masthead />
                @elseif(isset($header))
                    <h1 class="large-title" data-large-title>{{ $header }}</h1>
                @endif
            </div>
        @endunless

        @if($inline && isset($header))
            <h1 class="sr-only">{{ $header }}</h1>
        @endif

        @if (session('status'))
            <p role="status" class="mb-4 rounded-[12px] px-4 py-3 text-sm"
               style="background-color: var(--color-accent-wash); color: var(--color-ink);">
                {{ session('status') }}
            </p>
        @endif

        {{ $slot }}

        <x-footer />
    </main>

    {{-- Offline banner. Hidden until app.js has something to say (SC-016). --}}
    <p id="offline-banner" hidden role="status"
       class="fixed inset-x-0 z-50 px-4 py-2 text-center text-sm"
       style="bottom: calc(var(--bottom-nav-space)); background-color: var(--color-caution); color: var(--color-canvas);">
        {{ __('review.offline') }}
    </p>

    <x-bottom-nav />
</body>
</html>

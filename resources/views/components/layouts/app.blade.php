{{-- The signed-in shell.

     Built for a 375px phone held in one hand: content scrolls in a single
     column, and everything you tap lives in the bottom bar within thumb reach
     (FR-077, FR-078).

     Deliberately loads only the user bundle. No Livewire, no Alpine, no
     Filament asset appears on this page (SC-018). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#fbfaf8" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#161513" media="(prefers-color-scheme: dark)">
    <title>{{ isset($title) ? $title . ' · ' . config('app.name') : config('app.name') }}</title>

    <link rel="manifest" href="{{ url('manifest.json') }}">

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
<body class="min-h-full">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:px-4 focus:py-2"
       style="background-color: var(--color-surface); color: var(--color-ink);">
        {{ __('actions.next') }}
    </a>

    <main id="main" class="mx-auto w-full max-w-md px-4 pt-4">
        @isset($header)
            <header class="mb-4 flex min-h-11 items-center justify-between gap-3">
                <h1 class="text-xl font-semibold tracking-tight" style="color: var(--color-ink);">
                    {{ $header }}
                </h1>

                @isset($headerAction)
                    <div class="flex items-center gap-2">{{ $headerAction }}</div>
                @endisset
            </header>
        @endisset

        @if (session('status'))
            <p role="status" class="mb-4 rounded-lg px-4 py-3 text-sm"
               style="background-color: var(--color-accent-wash); color: var(--color-ink);">
                {{ session('status') }}
            </p>
        @endif

        {{ $slot }}
    </main>

    {{-- Offline banner. Hidden until app.js has something to say (SC-016). --}}
    <p id="offline-banner" hidden role="status"
       class="fixed inset-x-0 z-50 px-4 py-2 text-center text-sm"
       style="bottom: calc(var(--size-bottom-nav) + env(safe-area-inset-bottom)); background-color: var(--color-caution); color: var(--color-canvas);">
        {{ __('review.offline') }}
    </p>

    <x-bottom-nav />
</body>
</html>

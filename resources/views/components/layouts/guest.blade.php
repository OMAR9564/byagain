{{-- Shell for the signed-out screens: one column, no navigation, nothing to
     do but the task at hand. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" @if (\App\Services\Shell\NativeShell::hasTabBar(request())) data-shell="ios" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('icons/favicon-32.png') }}" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full !pb-0" style="background-color: var(--color-canvas); color: var(--color-ink); padding-top: max(40px, env(safe-area-inset-top));">
    <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center px-4 py-10">
        <header class="mb-8">
            <a href="{{ url('/') }}" class="large-title" style="color: var(--color-ink);">
                {{ config('app.name') }}
            </a>

            @isset($subtitle)
                <p class="mt-2 text-base" style="color: var(--color-ink-muted);">{{ $subtitle }}</p>
            @endisset
        </header>

        {{ $slot }}
    </main>
</body>
</html>

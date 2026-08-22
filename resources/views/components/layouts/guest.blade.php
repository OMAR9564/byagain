{{-- Shell for the signed-out screens: one column, no navigation, nothing to
     do but the task at hand. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full !pb-0" style="background-color: var(--color-canvas); color: var(--color-ink);">
    <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center px-6 py-10">
        <header class="mb-8">
            <a href="{{ url('/') }}" class="text-2xl font-semibold tracking-tight" style="color: var(--color-ink);">
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

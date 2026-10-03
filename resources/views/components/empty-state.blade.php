@props([
    'title',
    'body' => null,
    'action' => null,
    'href' => null,
])

{{-- An empty state is a signpost, not an apology: it says what this screen
     will hold and offers the one action that fills it (US1-1). --}}
<div class="flex flex-col items-center gap-3 px-6 py-14 text-center">
    <h2 class="text-xl font-bold" style="color: var(--color-ink);">{{ $title }}</h2>

    @if ($body !== null)
        <p class="max-w-xs text-base" style="color: var(--color-ink-muted);">{{ $body }}</p>
    @endif

    @if ($action !== null && $href !== null)
        <x-button :href="$href" class="mt-2">{{ $action }}</x-button>
    @endif
</div>

<x-layouts.app :title="__('practice.export.title')">
    <x-slot:header>{{ __('practice.export.title') }}</x-slot:header>

    {{-- Study export screen: passages and cards as study text for an LLM. --}}

    {{-- Summary row: passage and card counts --}}
    <div class="mb-6 rounded-lg px-4 py-3" style="background-color: var(--color-surface); color: var(--color-ink-muted);">
        {{ trans_choice('practice.export.summary_passages', $passages, ['count' => $passages]) }}
        and
        {{ trans_choice('practice.export.summary_cards', $cards, ['count' => $cards]) }}
    </div>

    {{-- Privacy notice --}}
    <div class="mb-6 rounded-lg border px-4 py-3" style="border-color: var(--color-border); color: var(--color-ink-muted);">
        {{ __('practice.export.privacy') }}
    </div>

    {{-- Readonly textarea containing the export text --}}
    <div class="mb-4">
        <textarea
            readonly
            data-copy-source
            rows="12"
            class="w-full rounded-lg border px-3 py-2"
            style="border-color: var(--color-border); background-color: var(--color-canvas); color: var(--color-ink); font-family: var(--font-mono); font-size: 0.875rem; overflow-x: hidden; white-space: pre-wrap; word-wrap: break-word;"
        >{{ $text }}</textarea>
    </div>

    {{-- Copy and download buttons --}}
    <div class="mb-6 flex flex-col gap-3">
        <button
            type="button"
            data-copy-button
            data-copied="{{ __('practice.export.copied') }}"
            data-fallback="{{ __('practice.export.copy_fallback') }}"
            class="inline-flex min-h-11 w-full items-center justify-center rounded-lg px-5 text-base font-medium"
            style="background-color: var(--color-accent); color: var(--color-accent-ink);"
        >
            {{ __('practice.export.copy') }}
        </button>

        <a
            href="{{ route('sources.export.download', $source) }}"
            class="inline-flex min-h-11 items-center justify-center rounded-lg px-5 text-base font-medium"
            style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);"
        >
            {{ __('practice.export.download') }}
        </a>
    </div>

    {{-- Copy status message (updated by copy.js) --}}
    <div data-copy-status role="status" aria-live="polite" class="text-center text-sm" style="color: var(--color-ink-muted);">
    </div>

    @vite('resources/js/copy.js')
</x-layouts.app>

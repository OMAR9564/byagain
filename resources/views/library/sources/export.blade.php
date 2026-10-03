<x-layouts.app :title="__('practice.export.title')" inline :back="route('sources.show', $source)" :back-label="$source->title">
    <x-slot:header>{{ __('practice.export.title') }}</x-slot:header>

    {{-- Study export screen: passages and cards as study text for an LLM. --}}

    {{-- Summary row: passage and card counts --}}
    <div class="mb-4 rounded-[12px] px-4 py-3" style="background-color: var(--color-surface); color: var(--color-ink-muted);">
        {{ __('practice.export.summary', [
            'passages' => trans_choice('practice.export.summary_passages', $passages, ['count' => $passages]),
            'questions' => trans_choice('practice.export.summary_cards', $cards, ['count' => $cards]),
        ]) }}
    </div>

    {{-- Privacy notice --}}
    <div class="mb-6 px-4 text-xs" style="color: var(--color-ink-muted);">
        {{ __('practice.export.privacy') }}
    </div>

    {{-- Readonly textarea containing the export text --}}
    <div class="mb-4">
        <label for="export-textarea" class="sr-only">{{ __('practice.export.title') }}</label>
        <textarea
            id="export-textarea"
            readonly
            data-copy-source
            rows="12"
            class="ios-field"
            style="padding: 14px 16px; background-color: var(--color-surface); color: var(--color-ink); font-family: var(--font-mono); font-size: 0.875rem; overflow-x: hidden; white-space: pre-wrap; word-wrap: break-word;"
        >{{ $text }}</textarea>
    </div>

    {{-- Copy and download buttons --}}
    <div class="mb-6 flex flex-col gap-3">
        <x-button
            data-copy-button
            data-copied="{{ __('practice.export.copied') }}"
            data-fallback="{{ __('practice.export.copy_fallback') }}"
            class="w-full"
        >
            {{ __('practice.export.copy') }}
        </x-button>

        <x-button variant="secondary" :href="route('sources.export.download', $source)" class="w-full">
            {{ __('practice.export.download') }}
        </x-button>
    </div>

    {{-- Copy status message (updated by copy.js) --}}
    <div data-copy-status role="status" aria-live="polite" class="text-center text-sm" style="color: var(--color-ink-muted);">
    </div>

    @vite('resources/js/copy.js')
</x-layouts.app>

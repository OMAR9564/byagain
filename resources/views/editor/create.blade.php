@php
    // Back to wherever the reader came from, but never to this page itself:
    // after a validation error the previous URL is this form.
    $cancelTo = url()->previous() !== url()->current() ? url()->previous() : route('library.index');
@endphp

<x-layouts.app :title="__('editor.title')" inline :cancel="$cancelTo">
    <x-slot:header>{{ __('editor.title') }}</x-slot:header>

    @if ($savedSource)
        <x-slot:cancelLabel>{{ __('actions.done') }}</x-slot:cancelLabel>
    @endif

    @if ($sources->isEmpty())
        <x-empty-state
            :title="__('library.empty.title')"
            :body="__('library.empty.body')"
            :action="__('library.empty.action')"
            :href="route('sources.create')"
        />
    @else
        {{-- Show a link to the source just saved if there is one (FR-213). --}}
        @if ($savedSource)
            <div class="mb-4 rounded-[12px] p-3.5" style="background-color: var(--color-surface);">
                <p class="text-sm mb-2" style="color: var(--color-ink);">
                    {{ __('editor.saved_to', ['source' => $savedSource->title]) }}
                </p>
                <x-button :href="route('sources.show', $savedSource)" variant="ghost" size="small">
                    {{ __('editor.view_source') }}
                </x-button>
            </div>
        @endif

        <x-editor.form :sources="$sources" :action="route('highlights.store')" :selected-source-id="$selectedSourceId" />
    @endif
</x-layouts.app>

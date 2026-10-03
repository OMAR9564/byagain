<x-layouts.app :title="__('editor.title_edit')" inline :back="route('sources.show', $highlight->source_id)" :back-label="$highlight->source?->title">
    <x-slot:header>{{ __('editor.title_edit') }}</x-slot:header>

    <x-slot:headerAction>
        {{-- Link to make a card from this passage (FR-044). --}}
        <a href="{{ route('mastery.create', $highlight) }}" class="bar-button pressable">
            {{ __('mastery.create.action') }}
        </a>
    </x-slot:headerAction>

    <x-editor.form
        :sources="$sources"
        :highlight="$highlight"
        :action="route('highlights.update', $highlight)"
        method="PATCH"
    />

    <form method="POST" action="{{ route('highlights.destroy', $highlight) }}"
          data-confirm="{{ __('library.highlight.delete_confirm') }}"
          class="mt-4 flex flex-col gap-5">
        @csrf
        @method('DELETE')

        <x-button type="submit" variant="danger" class="w-full">
            {{ __('actions.delete') }}
        </x-button>
    </form>
</x-layouts.app>

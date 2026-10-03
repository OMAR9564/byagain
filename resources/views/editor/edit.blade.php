<x-layouts.app :title="__('editor.title_edit')">
    <x-slot:header>{{ __('editor.title_edit') }}</x-slot:header>

    <x-slot:headerAction>
        {{-- Link to make a card from this passage (FR-044). --}}
        <x-button :href="route('mastery.create', $highlight)" variant="ghost" class="!px-3">
            {{ __('mastery.create.action') }}
        </x-button>
    </x-slot:headerAction>

    <x-editor.form
        :sources="$sources"
        :highlight="$highlight"
        :action="route('highlights.update', $highlight)"
        method="PATCH"
    />

    <form method="POST" action="{{ route('highlights.destroy', $highlight) }}"
          data-confirm="{{ __('library.highlight.delete_confirm') }}"
          class="mt-6 flex flex-col gap-5">
        @csrf
        @method('DELETE')

        <x-button type="submit" variant="ghost" style="color: var(--color-critical);">
            {{ __('actions.delete') }}
        </x-button>
    </form>
</x-layouts.app>

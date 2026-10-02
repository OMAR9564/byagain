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
</x-layouts.app>

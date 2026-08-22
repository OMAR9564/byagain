<x-layouts.app :title="__('editor.title_edit')">
    <x-slot:header>{{ __('editor.title_edit') }}</x-slot:header>

    <x-editor.form
        :sources="$sources"
        :highlight="$highlight"
        :action="route('highlights.update', $highlight)"
        method="PATCH"
    />
</x-layouts.app>

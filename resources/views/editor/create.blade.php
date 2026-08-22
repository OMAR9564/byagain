<x-layouts.app :title="__('editor.title')">
    <x-slot:header>{{ __('editor.title') }}</x-slot:header>

    @if ($sources->isEmpty())
        <x-empty-state
            :title="__('library.empty.title')"
            :body="__('library.empty.body')"
            :action="__('library.empty.action')"
            :href="route('sources.create')"
        />
    @else
        <x-editor.form :sources="$sources" :action="route('highlights.store')" />
    @endif
</x-layouts.app>

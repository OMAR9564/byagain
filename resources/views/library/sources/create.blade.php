<x-layouts.app :title="__('library.source.title')">
    <x-slot:header>{{ __('library.source.title') }}</x-slot:header>

    <form method="POST" action="{{ route('sources.store') }}" class="flex flex-col gap-5">
        @csrf
        <x-library.source-fields />
        <x-button type="submit">{{ __('actions.save') }}</x-button>
    </form>
</x-layouts.app>

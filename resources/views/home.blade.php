<x-layouts.app :title="__('review.title')">
    <x-slot:header>{{ config('app.name') }}</x-slot:header>

    {{-- The first thing a new account sees. Not an apology for being empty —
         a signpost to the one action that fills it (US1-1, FR-023). --}}
    <x-empty-state
        :title="__('library.empty.title')"
        :body="__('library.empty.body')"
        :action="Route::has('sources.create') ? __('library.empty.action') : null"
        :href="Route::has('sources.create') ? route('sources.create') : null"
    />
</x-layouts.app>

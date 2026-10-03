<x-layouts.app greet tabRoot :title="__('review.title')">
    {{-- No eligible highlights means no review at all, rather than an empty
         one — the same rule that stops the pipeline mailing a blank page
         (FR-037). --}}
    <x-empty-state
        :title="__('review.empty.title')"
        :body="__('review.empty.body')"
        :action="__('library.empty.action')"
        :href="route('sources.create')"
    />
</x-layouts.app>

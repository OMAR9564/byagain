<x-layouts.app greet tabRoot>
    {{-- No page heading here on purpose: the masthead already says who this
         is and the screen answers one question — what now? A brand name
         repeated under a greeting would be the only thing on the page that
         is not an answer. --}}
    @if ($streak > 0)
        <div class="mb-4 flex justify-end">
            <x-streak-badge :count="$streak" />
        </div>
    @endif

    @if (! $hasSources)
        {{-- The first thing a new account sees. Not an apology for being
             empty — a signpost to the one action that fills it (US1-1). --}}
        <x-empty-state
            :title="__('library.empty.title')"
            :body="__('library.empty.body')"
            :action="__('library.empty.action')"
            :href="route('sources.create')"
        />
    @elseif ($review !== null && ! $review->isCompleted())
        <x-card>
            <p class="text-base" style="color: var(--color-ink-muted);">
                {{ trans_choice('mail.reminder.remaining', $remaining, ['count' => $remaining]) }}
            </p>

            <x-button :href="route('review.show')" class="mt-4 w-full">
                {{ $review->started_at === null ? __('review.title') : __('review.resume') }}
            </x-button>
        </x-card>
    @elseif ($review !== null)
        <x-empty-state
            :title="__('review.complete.title')"
            :body="__('review.done.body')"
        />
    @else
        <x-empty-state
            :title="__('review.title')"
            :body="__('review.empty.body')"
            :action="__('review.title')"
            :href="route('review.show')"
        />
    @endif
</x-layouts.app>

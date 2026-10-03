<x-layouts.app :title="__('mastery.create.title')">
    <x-slot:header>{{ __('mastery.create.title') }}</x-slot:header>

    <x-card class="mb-5">
        <p class="mb-2 text-xs" style="color: var(--color-ink-subtle);">
            {{ $highlight->source->title }}
        </p>

        <x-highlight-content :highlight="$highlight" :collapsible="false" />
    </x-card>

    <form method="POST" action="{{ route('mastery.store', $highlight) }}" class="flex flex-col gap-5">
        @csrf

        <x-mastery.fields />

        <x-button type="submit">{{ __('mastery.create.submit') }}</x-button>
    </form>
</x-layouts.app>

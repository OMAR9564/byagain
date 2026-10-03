<x-layouts.guest :title="__('mail.unsubscribed.title')">
    <p class="text-xl font-bold" style="color: var(--color-ink);">{{ __('mail.unsubscribed.title') }}</p>

    <p class="mt-2 text-base" style="color: var(--color-ink-muted);">
        {{ __('mail.unsubscribed.body') }}
    </p>

    <div class="mt-6">
        <x-button :href="route('settings.edit')" variant="secondary" class="w-full">
            {{ __('settings.title') }}
        </x-button>
    </div>
</x-layouts.guest>

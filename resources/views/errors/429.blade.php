<x-layouts.guest :title="__('errors.throttled.title')">
    <section>
        <h1 class="text-2xl font-bold" style="color: var(--color-ink);">
            {{ __('errors.throttled.title') }}
        </h1>

        <p class="mt-2 text-base" style="color: var(--color-ink-muted);">
            {{ __('errors.throttled.body') }}
        </p>

        <a href="{{ url('/') }}" class="pressable mt-6 inline-flex min-h-11 items-center text-sm font-medium" style="color: var(--color-accent);">
            {{ __('actions.back') }}
        </a>
    </section>
</x-layouts.guest>

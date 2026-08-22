<x-layouts.guest :title="__('auth.forgot.title')">
    <x-slot:subtitle>{{ __('auth.forgot.subtitle') }}</x-slot:subtitle>

    @if (session('status'))
        {{-- Fortify's response is identical whether or not the address is
             registered, so this screen cannot be used to discover who has an
             account (FR-004). --}}
        <p role="status" class="mb-5 rounded-lg px-4 py-3 text-sm"
           style="background-color: var(--color-accent-wash); color: var(--color-ink);">
            {{ session('status') }}
        </p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf

        <x-field
            name="email"
            type="email"
            :label="__('auth.field.email')"
            autocomplete="username"
            required
            autofocus
        />

        <x-button type="submit">{{ __('auth.forgot.submit') }}</x-button>
    </form>

    <p class="mt-6 text-sm">
        <a href="{{ route('login') }}" style="color: var(--color-accent);">{{ __('actions.back') }}</a>
    </p>
</x-layouts.guest>

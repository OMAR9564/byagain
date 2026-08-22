<x-layouts.guest :title="__('auth.register.title')">
    <x-slot:subtitle>{{ __('auth.register.subtitle') }}</x-slot:subtitle>

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-5">
        @csrf

        <x-field name="name" :label="__('auth.field.name')" autocomplete="name" required autofocus />

        <x-field
            name="email"
            type="email"
            :label="__('auth.field.email')"
            autocomplete="username"
            required
        />

        <x-field
            name="password"
            type="password"
            :label="__('auth.field.password')"
            :help="__('auth.register.password_help')"
            autocomplete="new-password"
            required
        />

        <x-field
            name="password_confirmation"
            type="password"
            :label="__('auth.field.password_confirmation')"
            autocomplete="new-password"
            required
        />

        <x-button type="submit">{{ __('auth.register.submit') }}</x-button>
    </form>

    <p class="mt-6 text-sm">
        <a href="{{ route('login') }}" style="color: var(--color-accent);">
            {{ __('auth.register.have_account') }}
        </a>
    </p>
</x-layouts.guest>

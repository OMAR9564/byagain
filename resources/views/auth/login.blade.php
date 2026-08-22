<x-layouts.guest :title="__('auth.login.title')">
    <x-slot:subtitle>{{ __('auth.login.subtitle') }}</x-slot:subtitle>

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf

        <x-field
            name="email"
            type="email"
            :label="__('auth.field.email')"
            autocomplete="username"
            required
            autofocus
        />

        <x-field
            name="password"
            type="password"
            :label="__('auth.field.password')"
            autocomplete="current-password"
            required
        />

        <label class="flex items-center gap-2.5 text-sm" style="color: var(--color-ink-muted);">
            <input type="checkbox" name="remember" class="h-5 w-5 rounded" style="accent-color: var(--color-accent);">
            {{ __('auth.login.remember') }}
        </label>

        <x-button type="submit">{{ __('auth.login.submit') }}</x-button>
    </form>

    <div class="mt-6 flex flex-col gap-2 text-sm">
        <a href="{{ route('password.request') }}" style="color: var(--color-accent);">
            {{ __('auth.login.forgot') }}
        </a>

        <a href="{{ route('register') }}" style="color: var(--color-accent);">
            {{ __('auth.login.no_account') }}
        </a>
    </div>
</x-layouts.guest>

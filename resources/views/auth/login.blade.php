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

        <div class="ios-group">
            <label class="ios-row">
                <span class="flex-1">{{ __('auth.login.remember') }}</span>
                <input type="checkbox" name="remember" class="ios-switch">
            </label>
        </div>

        <x-button type="submit" class="w-full">{{ __('auth.login.submit') }}</x-button>
    </form>

    <div class="mt-6 flex flex-col gap-2 text-sm">
        <a href="{{ route('password.request') }}" class="pressable inline-flex min-h-11 items-center" style="color: var(--color-accent);">
            {{ __('auth.login.forgot') }}
        </a>

        <a href="{{ route('register') }}" class="pressable inline-flex min-h-11 items-center" style="color: var(--color-accent);">
            {{ __('auth.login.no_account') }}
        </a>
    </div>
</x-layouts.guest>

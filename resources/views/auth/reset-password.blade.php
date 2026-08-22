<x-layouts.guest :title="__('auth.reset.title')">
    <x-slot:subtitle>{{ __('auth.reset.subtitle') }}</x-slot:subtitle>

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-field
            name="email"
            type="email"
            :label="__('auth.field.email')"
            :value="$request->email"
            autocomplete="username"
            required
        />

        <x-field
            name="password"
            type="password"
            :label="__('auth.field.new_password')"
            :help="__('auth.register.password_help')"
            autocomplete="new-password"
            required
            autofocus
        />

        <x-field
            name="password_confirmation"
            type="password"
            :label="__('auth.field.password_confirmation')"
            autocomplete="new-password"
            required
        />

        <x-button type="submit">{{ __('auth.reset.submit') }}</x-button>
    </form>
</x-layouts.guest>

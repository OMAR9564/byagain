<x-layouts.guest :title="__('auth.confirm.title')">
    <x-slot:subtitle>{{ __('auth.confirm.subtitle') }}</x-slot:subtitle>

    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-5">
        @csrf

        <x-field
            name="password"
            type="password"
            :label="__('auth.field.password')"
            autocomplete="current-password"
            required
            autofocus
        />

        <x-button type="submit" class="w-full">{{ __('actions.confirm') }}</x-button>
    </form>
</x-layouts.guest>

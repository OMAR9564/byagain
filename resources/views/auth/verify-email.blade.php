<x-layouts.guest :title="__('auth.verify.title')">
    <x-slot:subtitle>{{ __('auth.verify.subtitle') }}</x-slot:subtitle>

    @if (session('status') === 'verification-link-sent')
        <p role="status" class="mb-5 rounded-lg px-4 py-3 text-sm"
           style="background-color: var(--color-accent-wash); color: var(--color-ink);">
            {{ __('auth.verify.resent') }}
        </p>
    @endif

    <p class="mb-6 text-base" style="color: var(--color-ink-muted);">
        {{ __('auth.verify.body') }}
    </p>

    <div class="flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button type="submit" class="w-full">{{ __('auth.verify.resend') }}</x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button type="submit" variant="ghost" class="w-full">{{ __('auth.logout') }}</x-button>
        </form>
    </div>
</x-layouts.guest>

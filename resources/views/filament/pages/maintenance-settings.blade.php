<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->getSchema('form') }}

        <x-filament::button type="submit">
            {{ __('actions.save') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>

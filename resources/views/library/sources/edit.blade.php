<x-layouts.app :title="$source->title" inline :back="route('sources.show', $source)" :back-label="$source->title">
    <x-slot:header>{{ __('actions.edit') }}</x-slot:header>

    <form method="POST" action="{{ route('sources.update', $source) }}" class="flex flex-col gap-5">
        @csrf
        @method('PATCH')

        <x-library.source-fields :source="$source" />

        <div class="ios-group">
            <label class="ios-row">
                <span class="flex-1">{{ __('actions.archive') }}</span>
                <input type="checkbox" name="is_archived" value="1" class="ios-switch"
                       @checked(old('is_archived', $source->is_archived))>
            </label>
        </div>

        <x-button type="submit" class="w-full">{{ __('actions.save') }}</x-button>
    </form>

    <form method="POST" action="{{ route('sources.destroy', $source) }}"
          data-confirm="{{ __('library.source.delete_confirm') }}"
          class="mt-4 flex flex-col gap-5">
        @csrf
        @method('DELETE')

        <x-button type="submit" variant="danger" class="w-full">
            {{ __('actions.delete') }}
        </x-button>
    </form>
</x-layouts.app>

<x-layouts.app :title="$source->title">
    <x-slot:header>{{ __('actions.edit') }}</x-slot:header>

    <form method="POST" action="{{ route('sources.update', $source) }}" class="flex flex-col gap-5">
        @csrf
        @method('PATCH')

        <x-library.source-fields :source="$source" />

        <label class="flex items-center gap-2.5 text-base" style="color: var(--color-ink);">
            <input type="checkbox" name="is_archived" value="1" class="h-5 w-5 rounded"
                   style="accent-color: var(--color-accent);" @checked(old('is_archived', $source->is_archived))>
            {{ __('actions.archive') }}
        </label>

        <x-button type="submit">{{ __('actions.save') }}</x-button>
    </form>

    <form method="POST" action="{{ route('sources.destroy', $source) }}"
          data-confirm="{{ __('library.source.delete_confirm') }}"
          class="mt-6 flex flex-col gap-5">
        @csrf
        @method('DELETE')

        <x-button type="submit" style="color: var(--color-critical);">
            {{ __('actions.delete') }}
        </x-button>
    </form>
</x-layouts.app>

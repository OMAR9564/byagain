<x-layouts.app :title="__('mastery.title')" inline :back="$card->highlight ? route('sources.show', $card->highlight->source_id) : route('mastery.index')" :back-label="$card->highlight?->source?->title ?? __('mastery.title')">
    <x-slot:header>{{ __('actions.edit') }}</x-slot:header>

    <form method="POST" action="{{ route('mastery.update', $card) }}" class="flex flex-col gap-5">
        @csrf
        @method('PATCH')

        <x-mastery.fields :card="$card" />

        <div class="flex flex-col gap-1.5">
            <label for="status" class="px-1 text-sm font-medium" style="color: var(--color-ink);">
                {{ __('mastery.status.active') }}
            </label>

            <select id="status" name="status" class="ios-field ios-select">
                @foreach ([\App\Models\MasteryCard::STATUS_ACTIVE, \App\Models\MasteryCard::STATUS_PAUSED] as $status)
                    <option value="{{ $status }}" @selected(old('status', $card->status) === $status)>
                        {{ __('mastery.status.' . $status) }}
                    </option>
                @endforeach
            </select>
        </div>

        <x-button type="submit" class="w-full">{{ __('actions.save') }}</x-button>
    </form>

    <form method="POST" action="{{ route('mastery.destroy', $card) }}"
          data-confirm="{{ __('mastery.delete_confirm') }}"
          class="mt-4 flex flex-col gap-5">
        @csrf
        @method('DELETE')

        <input type="hidden" name="return" value="source">

        <x-button type="submit" variant="danger" class="w-full">
            {{ __('actions.delete') }}
        </x-button>
    </form>
</x-layouts.app>

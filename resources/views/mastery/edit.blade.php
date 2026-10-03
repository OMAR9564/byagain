<x-layouts.app :title="__('mastery.title')">
    <x-slot:header>{{ __('actions.edit') }}</x-slot:header>

    <form method="POST" action="{{ route('mastery.update', $card) }}" class="flex flex-col gap-5">
        @csrf
        @method('PATCH')

        <x-mastery.fields :card="$card" />

        <div class="flex flex-col gap-1.5">
            <label for="status" class="text-sm font-medium" style="color: var(--color-ink);">
                {{ __('mastery.status.active') }}
            </label>

            <select id="status" name="status" class="min-h-11 w-full rounded-lg px-3"
                    style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);">
                @foreach ([\App\Models\MasteryCard::STATUS_ACTIVE, \App\Models\MasteryCard::STATUS_PAUSED] as $status)
                    <option value="{{ $status }}" @selected(old('status', $card->status) === $status)>
                        {{ __('mastery.status.' . $status) }}
                    </option>
                @endforeach
            </select>
        </div>

        <x-button type="submit">{{ __('actions.save') }}</x-button>
    </form>

    <form method="POST" action="{{ route('mastery.destroy', $card) }}"
          data-confirm="{{ __('mastery.delete_confirm') }}"
          class="mt-6 flex flex-col gap-5">
        @csrf
        @method('DELETE')

        <input type="hidden" name="return" value="source">

        <x-button type="submit" style="color: var(--color-critical);">
            {{ __('actions.delete') }}
        </x-button>
    </form>
</x-layouts.app>

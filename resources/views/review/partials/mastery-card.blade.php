@props(['card' => null, 'item' => null, 'domId' => null, 'scheduling' => true])

@php
// Backward-compatible: if $item is passed, extract the card and domId from it.
if ($item !== null && $card === null) {
    $card = $item->masteryCard;
    $domId = 'item-'.$item->id;
}
@endphp

{{-- Question first, answer only when asked for. Showing both at once turns
     recall into recognition, which is the one thing a card cannot afford
     (FR-045). --}}
<x-card>
    <p class="mb-3 text-sm" style="color: var(--color-ink-muted);">
        {{ $card->highlight?->source?->title }}
    </p>

    <p class="text-lg" style="color: var(--color-ink); line-height: var(--leading-relaxed);">
        {{ $card->type === \App\Models\MasteryCard::TYPE_CLOZE
            ? preg_replace(\App\Http\Requests\StoreMasteryCardRequest::CLOZE_PATTERN, '[…]', $card->question)
            : $card->question }}
    </p>

    <button
        type="button"
        class="pressable mt-2 flex min-h-11 items-center text-base font-medium"
        style="color: var(--color-accent);"
        data-mastery-reveal
        aria-expanded="false"
    >
        {{ __('mastery.card.show_answer') }}
    </button>

    <p
        class="mt-3 text-lg"
        style="color: var(--color-ink); line-height: var(--leading-relaxed);"
        data-mastery-answer
        hidden
    >
        {{ $card->answer }}
    </p>

    @if ($card->highlight !== null)
        <button
            type="button"
            class="pressable mt-2 flex min-h-11 items-center text-base font-medium"
            style="color: var(--color-accent);"
            data-mastery-passage-toggle
            aria-expanded="false"
            aria-controls="mastery-passage-{{ $domId }}"
            data-show-label="{{ __('mastery.card.show_passage') }}"
            data-hide-label="{{ __('mastery.card.hide_passage') }}"
            @if ($card->type === \App\Models\MasteryCard::TYPE_CLOZE) hidden @endif
        >
            {{ __('mastery.card.show_passage') }}
        </button>

        <div
            id="mastery-passage-{{ $domId }}"
            data-mastery-passage
            hidden
            class="mt-4"
        >
            <x-highlight-content :highlight="$card->highlight" :collapsible="false" />
        </div>
    @endif
</x-card>

@if ($scheduling)
    {{-- Four answers, none of them "wrong". The reader is choosing when to see
         this again, not being marked (FR-046). In daily review, this moves the card's
         schedule. --}}
    <div class="mt-4 grid grid-cols-2 gap-3" data-mastery-feedback hidden>
        @foreach (['sooner', 'later', 'someday', 'learned'] as $feedback)
            <x-button
                variant="{{ $feedback === 'learned' ? 'primary' : 'secondary' }}"
                data-mastery-choice="{{ $feedback }}"
                style="min-height: var(--size-touch-lg); border-radius: 14px;"
            >
                {{ __('mastery.feedback.' . $feedback) }}
            </x-button>
        @endforeach
    </div>

    <p class="mt-3 text-sm" style="color: var(--color-caution);" data-mastery-hint hidden></p>
@else
    {{-- In Mix, only a single "Next" button; the card's schedule never changes.
         The action payload sent is ignored (FR-203). --}}
    {{-- Same hidden wrapper as the review, so "Next" shows up only after the
         answer does and bindMastery wires the card the same way. --}}
    <div class="mt-4" data-mastery-feedback hidden>
        <x-button
            class="w-full"
            data-mastery-choice="later"
            style="min-height: var(--size-touch-lg); border-radius: 14px;"
        >
            {{ __('practice.mix.next') }}
        </x-button>
    </div>
@endif

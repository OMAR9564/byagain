@props(['card' => null])

<fieldset class="flex flex-col gap-2">
    <legend class="mb-1 text-sm font-medium" style="color: var(--color-ink);">
        {{ __('mastery.card.type') }}
    </legend>

    <label class="flex min-h-11 items-center gap-2.5 text-base" style="color: var(--color-ink);">
        <input type="radio" name="type" value="{{ \App\Models\MasteryCard::TYPE_QA }}" class="h-5 w-5"
               style="accent-color: var(--color-accent);"
               @checked(old('type', $card?->type ?? \App\Models\MasteryCard::TYPE_QA) === \App\Models\MasteryCard::TYPE_QA)>
        {{ __('mastery.card.type_qa') }}
    </label>

    <div class="flex flex-col gap-1">
        <label class="flex min-h-11 items-center gap-2.5 text-base" style="color: var(--color-ink);">
            <input type="radio" name="type" value="{{ \App\Models\MasteryCard::TYPE_CLOZE }}" class="h-5 w-5"
                   style="accent-color: var(--color-accent);"
                   @checked(old('type', $card?->type ?? \App\Models\MasteryCard::TYPE_QA) === \App\Models\MasteryCard::TYPE_CLOZE)>
            {{ __('mastery.card.type_cloze') }}
        </label>
        <p class="text-sm" style="color: var(--color-ink-muted);">{{ __('mastery.card.cloze_hint') }}</p>
    </div>
</fieldset>

<div class="flex flex-col gap-1.5">
    <label for="question" class="text-sm font-medium" style="color: var(--color-ink);">
        {{ __('mastery.card.question') }}
    </label>

    <textarea id="question" name="question" rows="4" required
              class="w-full rounded-lg p-3.5"
              style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);"
    >{{ old('question', $card?->question) }}</textarea>

    @error('question')
        <p class="text-sm" style="color: var(--color-critical);">{{ $message }}</p>
    @enderror
</div>

<div class="flex flex-col gap-1.5">
    <label for="answer" class="text-sm font-medium" style="color: var(--color-ink);">
        {{ __('mastery.card.answer') }}
    </label>

    {{-- Only needed for a question-and-answer card. A cloze carries its
         answer inside the question, so the controller derives it — two fields
         that must agree are two fields that will eventually disagree. --}}
    <textarea id="answer" name="answer" rows="3"
              class="w-full rounded-lg p-3.5"
              style="background-color: var(--color-surface); color: var(--color-ink); border: 1px solid var(--color-border-strong);"
    >{{ old('answer', $card?->answer) }}</textarea>

    @error('answer')
        <p class="text-sm" style="color: var(--color-critical);">{{ $message }}</p>
    @enderror
</div>

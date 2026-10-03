@props(['card' => null])

<fieldset class="ios-section" style="margin-top: 0;">
    <legend class="ios-section-header">
        {{ __('mastery.card.type') }}
    </legend>

    @php
        $currentType = old('type', $card?->type ?? \App\Models\MasteryCard::TYPE_QA);
    @endphp

    <div class="ios-group">
        <label class="ios-row ios-check-row ios-row-link">
            <span class="flex-1">{{ __('mastery.card.type_qa') }}</span>
            <input type="radio" name="type" value="{{ \App\Models\MasteryCard::TYPE_QA }}"
                   @checked($currentType === \App\Models\MasteryCard::TYPE_QA)>
            <svg class="ios-check" aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 8.5 6 12.5 14 3.5"/>
            </svg>
        </label>

        <label class="ios-row ios-check-row ios-row-link">
            <span class="flex-1">{{ __('mastery.card.type_cloze') }}</span>
            <input type="radio" name="type" value="{{ \App\Models\MasteryCard::TYPE_CLOZE }}"
                   @checked($currentType === \App\Models\MasteryCard::TYPE_CLOZE)>
            <svg class="ios-check" aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 8.5 6 12.5 14 3.5"/>
            </svg>
        </label>
    </div>

    <p class="ios-section-footer">{{ __('mastery.card.cloze_hint') }}</p>
</fieldset>

<div class="flex flex-col gap-1.5">
    <label for="question" class="px-1 text-sm font-medium" style="color: var(--color-ink);">
        {{ __('mastery.card.question') }}
    </label>

    <textarea id="question" name="question" rows="4" required
              class="ios-field"
              style="padding: 14px 16px;"
    >{{ old('question', $card?->question) }}</textarea>

    @error('question')
        <p class="px-1 text-sm" style="color: var(--color-critical);">{{ $message }}</p>
    @enderror
</div>

<div class="flex flex-col gap-1.5">
    <label for="answer" class="px-1 text-sm font-medium" style="color: var(--color-ink);">
        {{ __('mastery.card.answer') }}
    </label>

    {{-- Only needed for a question-and-answer card. A cloze carries its
         answer inside the question, so the controller derives it — two fields
         that must agree are two fields that will eventually disagree. --}}
    <textarea id="answer" name="answer" rows="3"
              class="ios-field"
              style="padding: 14px 16px;"
    >{{ old('answer', $card?->answer) }}</textarea>

    @error('answer')
        <p class="px-1 text-sm" style="color: var(--color-critical);">{{ $message }}</p>
    @enderror
</div>

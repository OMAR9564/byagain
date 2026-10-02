<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Http\Requests\StoreMasteryCardRequest;
use App\Models\MasteryCard;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared card validation rules for inline card creation during passage
 * editing (FR-044).
 */
trait ValidatesCards
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function cardRules(): array
    {
        return [
            'cards' => ['nullable', 'array', 'max:10'],
            'cards.*' => ['array'],
            'cards.*.type' => [
                'required',
                Rule::in([MasteryCard::TYPE_QA, MasteryCard::TYPE_CLOZE]),
            ],
            'cards.*.question' => ['required', 'string', 'max:2000'],
            'cards.*.answer' => [
                'required_if:cards.*.type,'.MasteryCard::TYPE_QA,
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Drop rows whose question and answer are both blank, so an
     * added-then-ignored block does not block saving. Rows that are not
     * arrays are kept, so `cards.*` rejects them with a 422 rather than the
     * request crashing on them.
     */
    protected function prepareCardsForValidation(): void
    {
        $cards = $this->input('cards', []);

        if (! is_array($cards)) {
            return;
        }

        $cards = array_filter(
            $cards,
            fn ($card) => ! is_array($card)
                || $this->isFilled($card['question'] ?? null)
                || $this->isFilled($card['answer'] ?? null),
        );

        // Re-index the array to avoid gaps after filtering.
        $this->merge(['cards' => array_values($cards)]);
    }

    /**
     * Check that every cloze question hides something. Call from
     * withValidator.
     */
    protected function validateClozeCards(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cards = $this->input('cards', []);

            foreach (is_array($cards) ? $cards : [] as $index => $card) {
                if (! is_array($card) || ($card['type'] ?? null) !== MasteryCard::TYPE_CLOZE) {
                    continue;
                }

                // A cloze with nothing hidden is just a sentence. Catching it
                // here is kinder than letting the reader discover it mid-review.
                $question = $card['question'] ?? '';

                if (! is_string($question) || preg_match(StoreMasteryCardRequest::CLOZE_PATTERN, $question) !== 1) {
                    $validator->errors()->add(
                        "cards.{$index}.question",
                        __('mastery.card.cloze_hint'),
                    );
                }
            }
        });
    }

    /** A "0" is an answer; only an empty or whitespace-only value is blank. */
    private function isFilled(mixed $value): bool
    {
        return is_scalar($value) && trim((string) $value) !== '';
    }
}

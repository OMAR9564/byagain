<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\MasteryCard;
use Illuminate\Validation\Rule;

/**
 * Shared card validation rules for inline card creation during passage editing.
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

    /** Matches the {{hidden part}} of a cloze. */
    final protected const string CLOZE_PATTERN = '/\{\{(.+?)\}\}/s';

    /**
     * Check that cloze questions contain braces, and clean blank card rows.
     *
     * In prepareForValidation, drop rows whose question and answer are both
     * blank, so an added-then-ignored block does not block saving.
     */
    protected function prepareCardsForValidation(): void
    {
        $cards = (array) $this->input('cards', []);

        // Remove entirely blank rows (both question and answer empty).
        $cards = array_filter(
            $cards,
            fn ($card) => ! empty($card['question'] ?? '') || ! empty($card['answer'] ?? ''),
        );

        // Re-index the array to avoid gaps after filtering.
        $this->merge(['cards' => array_values($cards)]);

        // Validate cloze questions have braces in withValidator.
    }
}

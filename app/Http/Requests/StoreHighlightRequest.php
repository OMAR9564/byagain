<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCards;
use App\Models\MasteryCard;
use App\Models\Source;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreHighlightRequest extends FormRequest
{
    use ValidatesCards;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // `exists` is scoped to this user's sources, so a guessed
            // source_id cannot file a highlight into someone else's library.
            'source_id' => [
                'required',
                'integer',
                Rule::exists(Source::class, 'id')->where('user_id', $this->user()?->id),
            ],

            // `content_md` is the source of truth. `content_html` is never
            // accepted from a request — only MarkdownRenderer writes it.
            'content_md' => ['required', 'string', 'max:20000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:120'],

            // Inline cards during passage creation (FR-208).
            ...$this->cardRules(),
        ];
    }

    public function prepareForValidation(): void
    {
        $this->prepareCardsForValidation();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cards = (array) $this->input('cards', []);

            foreach ($cards as $index => $card) {
                if (($card['type'] ?? null) !== MasteryCard::TYPE_CLOZE) {
                    continue;
                }

                // A cloze with nothing hidden is just a sentence. Catching it
                // here is kinder than letting the reader discover it mid-review.
                if (preg_match(self::CLOZE_PATTERN, (string) ($card['question'] ?? '')) !== 1) {
                    $validator->errors()->add(
                        "cards.{$index}.question",
                        __('mastery.card.cloze_hint'),
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'content_md' => __('library.highlight.title'),
            'source_id' => __('library.source.title'),
        ];
    }
}

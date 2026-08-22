<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Highlight;
use App\Models\MasteryCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreMasteryCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $highlight = $this->route('highlight');

        return $highlight instanceof Highlight
            && $this->user() !== null
            && $highlight->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([MasteryCard::TYPE_QA, MasteryCard::TYPE_CLOZE])],
            'question' => ['required', 'string', 'max:2000'],

            // A cloze carries its answer inside the question, so it is filled
            // in by the controller rather than demanded from the form.
            'answer' => ['required_if:type,'.MasteryCard::TYPE_QA, 'nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('type') !== MasteryCard::TYPE_CLOZE) {
                return;
            }

            // A cloze with nothing hidden is just a sentence. Catching it here
            // is kinder than letting the reader discover it mid-review.
            if (preg_match(self::CLOZE_PATTERN, (string) $this->input('question')) !== 1) {
                $validator->errors()->add('question', __('mastery.card.cloze_hint'));
            }
        });
    }

    /** Matches the {{hidden part}} of a cloze. */
    public const string CLOZE_PATTERN = '/\{\{(.+?)\}\}/s';
}

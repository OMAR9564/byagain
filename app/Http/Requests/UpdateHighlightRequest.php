<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCards;
use App\Models\Highlight;
use App\Models\Source;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateHighlightRequest extends FormRequest
{
    use ValidatesCards;

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
            'source_id' => [
                'required',
                'integer',
                Rule::exists(Source::class, 'id')->where('user_id', $this->user()?->id),
            ],
            'content_md' => ['required', 'string', 'max:20000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:120'],

            // Inline cards during passage editing (FR-044).
            ...$this->cardRules(),
        ];
    }

    public function prepareForValidation(): void
    {
        $this->prepareCardsForValidation();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateClozeCards($validator);
    }
}

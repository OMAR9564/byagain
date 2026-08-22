<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MasteryCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateMasteryCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $card = $this->route('card');

        return $card instanceof MasteryCard
            && $this->user() !== null
            && $card->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([MasteryCard::TYPE_QA, MasteryCard::TYPE_CLOZE])],
            'question' => ['required', 'string', 'max:2000'],
            'answer' => ['required_if:type,'.MasteryCard::TYPE_QA, 'nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([MasteryCard::STATUS_ACTIVE, MasteryCard::STATUS_PAUSED])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('type') !== MasteryCard::TYPE_CLOZE) {
                return;
            }

            if (preg_match(StoreMasteryCardRequest::CLOZE_PATTERN, (string) $this->input('question')) !== 1) {
                $validator->errors()->add('question', __('mastery.card.cloze_hint'));
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MasteryCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MixCardActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $card = $this->route('card');
        $user = $this->user();

        return $card instanceof MasteryCard
            && $user !== null
            && $card->user_id === $user->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // For Mix cards, only the action is validated; we ignore the payload
            // since we do not schedule or change the card's state.
            'action' => ['required', Rule::in(['keep'])],

            'favorite' => ['nullable', 'boolean'],
            'source_frequency' => ['nullable'],

            'client_acted_at' => ['nullable', 'date'],
            'mastery_feedback' => ['nullable'],
        ];
    }
}

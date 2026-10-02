<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Highlight;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MixHighlightActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $highlight = $this->route('highlight');
        $user = $this->user();

        return $highlight instanceof Highlight
            && $user !== null
            && $highlight->user_id === $user->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['keep', 'discard'])],

            'favorite' => ['nullable', 'boolean'],
            'source_frequency' => ['nullable', Rule::in(StoreSourceRequest::frequencies())],

            // Sent for compatibility with the review action payload, but
            // ignored in Mix (contracts/routes.md).
            'client_acted_at' => ['nullable', 'date'],
            'mastery_feedback' => ['nullable'],
        ];
    }
}

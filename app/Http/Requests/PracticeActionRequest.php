<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Highlight;
use App\Models\Source;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PracticeActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('source');
        $highlight = $this->route('highlight');
        $user = $this->user();

        return $source instanceof Source
            && $highlight instanceof Highlight
            && $user !== null
            && $source->user_id === $user->id
            && $highlight->user_id === $user->id
            && $highlight->source_id === $source->id;
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
            // ignored in practice (contracts/routes.md).
            'client_acted_at' => ['nullable', 'date'],
            'mastery_feedback' => ['nullable'],
        ];
    }
}

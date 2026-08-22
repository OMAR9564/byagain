<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Source;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('source');

        // The ownership scope has already made another account's source
        // unreachable — route binding 404s before this runs. This is the
        // second lock on the same door, because authorize() returning a bare
        // `true` is how the first one silently stops mattering.
        return $source instanceof Source
            && $this->user() !== null
            && $source->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['book', 'article', 'note', 'podcast', 'course', 'other'])],

            // Changing this takes effect from the next review onwards; today's
            // cards are already written and stay as they are (FR-028).
            'frequency' => ['required', Rule::in(StoreSourceRequest::frequencies())],
            'is_archived' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_archived' => $this->boolean('is_archived'),
        ]);
    }
}

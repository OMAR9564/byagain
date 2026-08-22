<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The editor's preview.
 *
 * Nothing is written, so there is no source to check ownership against — but
 * the same length ceiling applies as on save. A preview that accepts more than
 * the editor will store would tell the writer their passage is fine and then
 * refuse it.
 */
final class PreviewHighlightRequest extends FormRequest
{
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
            // Nullable, not required: an empty field previews as nothing, and
            // Laravel's ConvertEmptyStringsToNull has already turned the empty
            // string into null by the time this runs.
            'content_md' => ['present', 'nullable', 'string', 'max:20000'],
        ];
    }
}

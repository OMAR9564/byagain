<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Highlight;
use App\Models\Source;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateHighlightRequest extends FormRequest
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
            'source_id' => [
                'required',
                'integer',
                Rule::exists(Source::class, 'id')->where('user_id', $this->user()?->id),
            ],
            'content_md' => ['required', 'string', 'max:20000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:120'],
        ];
    }
}

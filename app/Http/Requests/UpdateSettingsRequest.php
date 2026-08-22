<?php

declare(strict_types=1);

namespace App\Http\Requests;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Settings belong to the signed-in account and the route carries no
        // identifier, so there is nothing to compare against.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Bounds come from config so the form, the validator and the
            // sampler cannot disagree (Constitution art. V).
            'review_size' => [
                'required',
                'integer',
                'min:'.config('byagain.review.min_size'),
                'max:'.config('byagain.review.max_size'),
            ],

            'mastery_ratio' => ['required', 'integer', 'min:0', 'max:100'],

            'quality_filter_enabled' => ['boolean'],
            'equal_source_weighting' => ['boolean'],

            // A real IANA identifier, not a UTC offset: offsets do not know
            // about daylight saving, and the whole day boundary depends on
            // getting this right.
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],

            'daily_email_enabled' => ['boolean'],
            'daily_email_at' => ['required', 'date_format:H:i'],
            'reminder_email_enabled' => ['boolean'],
            'reminder_email_at' => ['required', 'date_format:H:i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Unchecked boxes are absent from the payload rather than false.
        $this->merge([
            'quality_filter_enabled' => $this->boolean('quality_filter_enabled'),
            'equal_source_weighting' => $this->boolean('equal_source_weighting'),
            'daily_email_enabled' => $this->boolean('daily_email_enabled'),
            'reminder_email_enabled' => $this->boolean('reminder_email_enabled'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'review_size.min' => __('settings.review.size_help', [
                'min' => config('byagain.review.min_size'),
                'max' => config('byagain.review.max_size'),
            ]),
            'review_size.max' => __('settings.review.size_help', [
                'min' => config('byagain.review.min_size'),
                'max' => config('byagain.review.max_size'),
            ]),
        ];
    }
}

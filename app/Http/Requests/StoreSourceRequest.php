<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSourceRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['book', 'article', 'note', 'podcast', 'course', 'other'])],
            'frequency' => ['required', Rule::in(self::frequencies())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'type' => 'book',
            'frequency' => config('byagain.sampling.default_source_frequency'),
        ]);
    }

    /**
     * The frequency tiers, read from config so the form and the sampler can
     * never disagree about what exists (Constitution art. V).
     *
     * @return array<int, string>
     */
    public static function frequencies(): array
    {
        /** @var array<string, float> $weights */
        $weights = config('byagain.sampling.source_weights');

        return [
            (string) config('byagain.sampling.excluded_source_frequency'),
            ...array_keys($weights),
        ];
    }
}

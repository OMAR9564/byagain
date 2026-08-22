<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ReviewItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewItemActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof ReviewItem
            && $this->user() !== null
            && $item->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in([ReviewItem::ACTION_KEEP, ReviewItem::ACTION_DISCARD])],

            // Only meaningful on a mastery card; the service ignores it on a
            // highlight rather than failing, because the offline queue may
            // replay a stale payload (FR-046).
            'mastery_feedback' => ['nullable', Rule::in(['sooner', 'later', 'someday', 'learned'])],

            'favorite' => ['nullable', 'boolean'],
            'source_frequency' => ['nullable', Rule::in(StoreSourceRequest::frequencies())],

            // Sent by the offline queue so an action taken on the train is
            // dated when it happened (R-10).
            'client_acted_at' => ['nullable', 'date'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PushSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the browser hands us when a reader allows notifications.
 *
 * The shape is the Push API's, not ours: `PushSubscription.toJSON()` produces
 * `{ endpoint, keys: { p256dh, auth } }` and the client forwards it as-is
 * rather than rearranging it on the way (contracts/push.md).
 */
final class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A subscription belongs to whoever is signed in; the request carries
        // no id to compare against, so being signed in is the whole check.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // https only, because a push service that is not is not a push
            // service. The 512 matches the column, so an over-long endpoint
            // is a 422 rather than a truncated row that silently never works.
            'endpoint' => ['required', 'string', 'max:512', 'url:https'],

            // Base64url — the alphabet the Push API encodes these in. Length
            // is deliberately not pinned: it varies by browser, and rejecting
            // a valid subscription is worse than accepting an odd one, which
            // the push service will reject for us anyway.
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'keys.auth' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],

            'content_encoding' => ['sometimes', 'nullable', Rule::in(PushSubscription::ENCODINGS)],
        ];
    }
}

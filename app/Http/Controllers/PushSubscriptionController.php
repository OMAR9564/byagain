<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePushSubscriptionRequest;
use App\Services\Push\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registering and forgetting a browser.
 *
 * Called by `push.js` either side of the browser's own permission prompt, so
 * both ends are quiet: nothing here renders, nothing here redirects.
 */
final class PushSubscriptionController extends Controller
{
    /**
     * Store a subscription, or refresh one that already exists.
     *
     * The endpoint identifies the *browser*, not the account. So the same
     * endpoint arriving under a second account transfers the row rather than
     * duplicating it: one browser yields one endpoint, and two rows for it
     * would mean sending one reader's reminder to whoever else is signed in on
     * that device (contracts/push.md).
     */
    public function store(StorePushSubscriptionRequest $request, WebPushSender $sender): JsonResponse
    {
        if (! $sender->isConfigured()) {
            // Nothing to subscribe to. The interface reads this and renders
            // the switch disabled rather than failing on tap (R-207).
            return response()->json(['message' => __('push.settings.unconfigured')], 503);
        }

        $user = $request->user();
        $data = $request->validated();
        $endpoint = $data['endpoint'];

        $attributes = [
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ];

        // Asked of the query builder rather than the model, because the answer
        // may legitimately be another account's row and the ownership scope is
        // the one thing that never gets switched off outside app/Filament/
        // (Constitution art. III). Nothing is bypassed here — the scope is a
        // model concern and no model is involved.
        $claimedBy = DB::table('push_subscriptions')->where('endpoint', $endpoint)->value('user_id');

        if ($claimedBy !== null) {
            DB::table('push_subscriptions')
                ->where('endpoint', $endpoint)
                ->update($attributes + [
                    'user_id' => $user->id,
                    'updated_at' => Carbon::now(),
                ]);

            // A refresh either way, whether or not it changed hands: this
            // browser was already known, and the reader gains nothing from
            // being told which of their accounts registered it first.
            return response()->json(['status' => 'subscribed'], 200);
        }

        $user->pushSubscriptions()->create($attributes + ['endpoint' => $endpoint]);

        return response()->json(['status' => 'subscribed'], 201);
    }

    /**
     * Forget a browser.
     *
     * Always 204, including when there was nothing to forget: the client calls
     * this after the browser's own `unsubscribe()`, and by then the endpoint
     * may already be gone. Telling it off for tidying up would be nonsense.
     */
    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->input('endpoint');

        if (is_string($endpoint) && $endpoint !== '') {
            // Through the relation, so forgetting is something a reader may
            // only do to their own device (FR-010).
            $request->user()->pushSubscriptions()->where('endpoint', $endpoint)->delete();
        }

        return response()->json([], 204);
    }
}

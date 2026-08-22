<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EmailDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The provider's open signal.
 *
 * Open tracking is approximate by nature — image blocking makes a miss look
 * like a non-open — so this only ever feeds a soft decision: after enough
 * unopened mails in a row, byagain gets quieter rather than louder (FR-064).
 * Nothing is deleted and no account is disabled on the strength of it.
 *
 * The signature is verified before anything is written. Without it this
 * endpoint would let anyone silence anyone else's reminders.
 */
final class MailWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->signatureIsValid($request)) {
            return response()->json(['message' => __('errors.signature_invalid')], 403);
        }

        $recipient = $request->input('data.to.0') ?? $request->input('data.to');
        $event = (string) $request->input('type');

        if (! is_string($recipient) || $event !== 'email.opened') {
            return response()->json(['message' => 'ignored']);
        }

        $delivery = EmailDelivery::query()
            ->where('recipient', $recipient)
            ->whereNull('opened_at')
            ->latest('id')
            ->first();

        if ($delivery === null) {
            return response()->json(['message' => 'ignored']);
        }

        $delivery->opened_at = Carbon::now();
        $delivery->save();

        // Opening anything resets the run. The counter measures consecutive
        // silence, not lifetime totals.
        $user = $delivery->user;
        $user->consecutive_unopened_emails = 0;
        $user->save();

        return response()->json(['message' => 'ok']);
    }

    /**
     * Constant-time comparison against the shared secret.
     *
     * An empty secret rejects everything rather than accepting everything —
     * an unconfigured webhook should be closed, not open.
     */
    private function signatureIsValid(Request $request): bool
    {
        $secret = (string) config('services.resend.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $signature = (string) $request->header('svix-signature', '');

        if ($signature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        return hash_equals($expected, $signature);
    }
}

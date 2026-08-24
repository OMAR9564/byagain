<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * The only place `minishlink/web-push` is touched.
 *
 * Everything else about notifications is ours — the subscription model, the
 * deduplication, the queue. The library does one thing we are not going to do
 * ourselves: sign a VAPID JWT and encrypt a payload with ECDH and AES-GCM.
 * Cryptography is not written by hand, and a mistake in it does not look like
 * a mistake — it looks like a notification that never arrives (R-203).
 *
 * Keeping the whole surface in one class means a version bump has one file to
 * read, and a test has one thing to fake.
 */
class WebPushSender
{
    /**
     * Whether this server can send at all.
     *
     * Unconfigured is a normal state, not an error: a fresh checkout has no
     * keys, and the feature simply stays out of the way until someone runs
     * `byagain:vapid-keys` (R-207).
     */
    public function isConfigured(): bool
    {
        return $this->publicKey() !== '' && $this->privateKey() !== '' && $this->subject() !== '';
    }

    /**
     * The key the browser needs to subscribe with. Public by design — it goes
     * into the page.
     */
    public function publicKey(): string
    {
        return (string) config('services.vapid.public_key');
    }

    /**
     * Deliver one payload to one subscription.
     *
     * Never throws: a push service being unreachable is an ordinary Tuesday,
     * and the caller's job is to record what happened rather than to handle an
     * exception. The distinction that matters comes back in the result —
     * `expired` means the browser has revoked this subscription and the row
     * should go (FR-150), where a failure means try again another day.
     */
    public function send(PushSubscription $subscription, string $payload): PushSendResult
    {
        if (! $this->isConfigured()) {
            return PushSendResult::failed('vapid_not_configured');
        }

        try {
            $report = $this->client()->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                $payload,
            );
        } catch (Throwable $exception) {
            return PushSendResult::failed($exception->getMessage());
        }

        if ($report->isSuccess()) {
            return PushSendResult::sent();
        }

        // 404 and 410 are the push service saying this endpoint is gone. It
        // will never work again, so retrying it is pure noise.
        if ($report->isSubscriptionExpired()) {
            return PushSendResult::expired($report->getReason());
        }

        return PushSendResult::failed($report->getReason());
    }

    /**
     * Built per call rather than injected: the client holds a queue of pending
     * notifications, and one shared across a worker's lifetime would carry a
     * failed send's leftovers into the next job.
     */
    protected function client(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => $this->subject(),
                'publicKey' => $this->publicKey(),
                'privateKey' => $this->privateKey(),
            ],
        ]);
    }

    private function privateKey(): string
    {
        return (string) config('services.vapid.private_key');
    }

    private function subject(): string
    {
        return (string) config('services.vapid.subject');
    }
}

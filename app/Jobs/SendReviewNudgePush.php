<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PushDelivery;
use App\Services\Push\PushDispatcher;
use App\Services\Push\WebPushSender;
use App\Services\Time\LocalDayResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * The browser nudge, and the checks that stop it being a nag.
 *
 * Carries an id rather than a model, like the mail jobs: the row is re-read at
 * send time because the world moves between the sweep deciding and the worker
 * running. Someone who finished their review in those minutes must not be told
 * they have not (FR-142).
 *
 * `tries = 1`, deliberately. A reminder that arrives late is not a reminder —
 * by the time a retry ran the reader would be somewhere else in their day, and
 * tomorrow's sweep makes a fresh decision anyway
 * (contracts/console-and-jobs.md).
 */
final class SendReviewNudgePush implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(
        PushDispatcher $dispatcher,
        WebPushSender $sender,
        LocalDayResolver $days,
    ): void {
        $delivery = PushDelivery::query()->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== PushDelivery::STATUS_QUEUED) {
            return;
        }

        $user = $delivery->user;

        // Re-asked here rather than trusted from queue time. Everything in the
        // list can change in the minutes a job spends waiting, and the two
        // that matter most — the reader finishing, the reader changing their
        // mind — are the ones that would make this notification wrong.
        $localDay = $this->localDayOf($delivery, $days);

        if (! $user->push_enabled) {
            $dispatcher->markSkipped($delivery, 'disabled');

            return;
        }

        $review = $dispatcher->openReviewFor($user, $localDay);

        if ($review === null) {
            $dispatcher->markSkipped($delivery, 'review_completed');

            return;
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            $dispatcher->markSkipped($delivery, 'no_subscription');

            return;
        }

        if (! $sender->isConfigured()) {
            $dispatcher->markFailed($delivery, 'vapid_not_configured');

            return;
        }

        $payload = $this->payload($localDay->toDateString());
        $delivered = 0;
        $lastError = null;

        foreach ($subscriptions as $subscription) {
            $result = $sender->send($subscription, $payload);

            if ($result->delivered) {
                $delivered++;
                $subscription->last_used_at = now();
                $subscription->save();

                continue;
            }

            // The browser has revoked this one. Deleting it is not deleting
            // user content — it is a permission the device took back, and
            // keeping it would mean retrying a dead endpoint every day
            // (FR-150, data-model.md).
            if ($result->expired) {
                $subscription->delete();

                continue;
            }

            $lastError = $result->error;
        }

        // One device is enough. A reader with a phone and a laptop has been
        // reminded even if the laptop is unreachable.
        if ($delivered > 0) {
            $dispatcher->markSent($delivery);

            return;
        }

        if ($lastError !== null) {
            $dispatcher->markFailed($delivery, $lastError);

            return;
        }

        // Every subscription turned out to be revoked: nothing failed, there
        // was simply nobody left to tell.
        $dispatcher->markSkipped($delivery, 'no_subscription');
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = PushDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        app(PushDispatcher::class)->markFailed(
            $delivery,
            $exception?->getMessage() ?? 'unknown error',
        );
    }

    /**
     * What the service worker shows.
     *
     * Resolved here, on the server, from `lang/en/` — the service worker holds
     * no user-facing text of its own (Constitution: no interface copy in JS).
     *
     * It carries no passage content. What appears on a lock screen is a
     * reminder that today's review is waiting, and nothing about what is in it
     * (FR-148).
     */
    private function payload(string $day): string
    {
        return (string) json_encode([
            'title' => __('push.nudge.title'),
            'body' => __('push.nudge.body'),
            'url' => route('review.show', absolute: false),
            // Fixed for the day, so a second notification would replace the
            // first on screen rather than stack under it (contracts/push.md).
            'tag' => 'byagain-nudge-'.$day,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * The local day this delivery was claimed for, read back off its own
     * dedupe key rather than from the clock — the worker may well be running
     * on the other side of the reader's midnight.
     */
    private function localDayOf(PushDelivery $delivery, LocalDayResolver $days): \Carbon\CarbonImmutable
    {
        $date = str_contains($delivery->dedupe_key, ':')
            ? explode(':', $delivery->dedupe_key, 2)[1]
            : null;

        if ($date === null || $date === '') {
            return $days->localDayFor($delivery->user);
        }

        return \Carbon\CarbonImmutable::parse($date)->startOfDay();
    }
}

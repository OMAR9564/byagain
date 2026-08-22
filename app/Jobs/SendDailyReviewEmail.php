<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\DailyReviewMail;
use App\Models\EmailDelivery;
use App\Models\Review;
use App\Services\Mail\MailDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the morning mail.
 *
 * The state is re-read here rather than trusted from when the job was queued.
 * Minutes can pass between the clock reaching 08:00 and a worker picking this
 * up, and in that time the reader may have unsubscribed, been suspended, or
 * deleted their account. Sending anyway because the queue said so is how
 * people receive mail they explicitly turned off.
 */
final class SendDailyReviewEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly int $deliveryId,
        public readonly int $reviewId,
    ) {}

    /**
     * Backoff in seconds: a minute, five, fifteen, an hour, three hours.
     * A provider outage is usually measured in minutes, and a reader would
     * rather have yesterday's review late than not at all (FR-067).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 10800];
    }

    public function handle(MailDispatcher $dispatcher): void
    {
        $delivery = EmailDelivery::query()->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== EmailDelivery::STATUS_QUEUED) {
            return;
        }

        $user = $delivery->user;
        $review = Review::query()->with(Review::cardRelations())->find($this->reviewId);

        if ($review === null) {
            $dispatcher->markSkipped($delivery, 'review_missing');

            return;
        }

        if (! $user->canReceiveRitualEmail()) {
            $dispatcher->markSkipped($delivery, 'not_eligible');

            return;
        }

        if (! $user->daily_email_enabled) {
            $dispatcher->markSkipped($delivery, 'unsubscribed');

            return;
        }

        Mail::to($user->email)->send(new DailyReviewMail($user, $review, $delivery));

        $dispatcher->markSent($delivery);
    }

    /**
     * Nothing disappears silently: a delivery that ran out of attempts is
     * recorded as failed with its reason, so the admin panel can show it
     * (FR-068).
     */
    public function failed(?Throwable $exception): void
    {
        $delivery = EmailDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        app(MailDispatcher::class)->markFailed(
            $delivery,
            $exception?->getMessage() ?? 'unknown error',
        );
    }
}

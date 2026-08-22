<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\EveningReminderMail;
use App\Models\EmailDelivery;
use App\Models\Review;
use App\Services\Mail\MailDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The evening nudge, and the checks that stop it being a nag.
 *
 * The review's status is re-read at send time, not at queue time. Someone who
 * finished their review at 19:55 must not be reminded at 20:00 that they have
 * not — that single mail would undo the goodwill the whole feature exists to
 * build (FR-062).
 */
final class SendEveningReminderEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly int $deliveryId,
        public readonly int $reviewId,
    ) {}

    /**
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

        // The check that matters most, and the reason it happens here.
        if ($review->isCompleted()) {
            $dispatcher->markSkipped($delivery, 'already_completed');

            return;
        }

        if (! $user->canReceiveRitualEmail()) {
            $dispatcher->markSkipped($delivery, 'not_eligible');

            return;
        }

        if (! $user->reminder_email_enabled) {
            $dispatcher->markSkipped($delivery, 'unsubscribed');

            return;
        }

        $remaining = $review->items()->whereNull('acted_at')->count();

        Mail::to($user->email)->send(new EveningReminderMail($user, $review, $delivery, $remaining));

        $dispatcher->markSent($delivery);
    }

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

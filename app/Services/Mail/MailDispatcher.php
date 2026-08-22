<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Jobs\SendDailyReviewEmail;
use App\Jobs\SendEveningReminderEmail;
use App\Models\EmailDelivery;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deciding what to send, exactly once.
 *
 * "Exactly once" is a database guarantee here, not an application one. The
 * dispatch command runs every five minutes and may overlap itself, run on two
 * machines, or be re-run by hand. Rather than checking whether a mail has
 * already been queued and then queueing it — a race with a five-minute window
 * to lose in — this attempts an insert against
 * UNIQUE (user_id, dedupe_key) and queues only if the insert won (FR-063,
 * SC-014).
 *
 * The queued job re-reads the world before sending, because a lot can change
 * between "the clock says 08:00" and the worker picking the job up.
 */
final class MailDispatcher
{
    /**
     * Queue the daily review mail, if it has not already been claimed.
     *
     * Returns the delivery when this call is the one that queued it, and null
     * when someone else got there first.
     */
    public function queueDailyReview(User $user, Review $review, CarbonImmutable $localDay): ?EmailDelivery
    {
        return $this->claim($user, EmailDelivery::TYPE_DAILY, $localDay, function (EmailDelivery $delivery) use ($review): void {
            SendDailyReviewEmail::dispatch($delivery->id, $review->id);
        });
    }

    public function queueEveningReminder(User $user, Review $review, CarbonImmutable $localDay): ?EmailDelivery
    {
        return $this->claim($user, EmailDelivery::TYPE_REMINDER, $localDay, function (EmailDelivery $delivery) use ($review): void {
            SendEveningReminderEmail::dispatch($delivery->id, $review->id);
        });
    }

    /**
     * Whether a reminder is allowed at all right now.
     *
     * Someone who has stopped opening these does not need another one. Past
     * the threshold the reminder drops to once a week, which is a quieter way
     * of asking than stopping outright (FR-064).
     */
    public function reminderIsAllowed(User $user): bool
    {
        if ($user->consecutive_unopened_emails < (int) config('byagain.mail.unopened_pause_threshold')) {
            return true;
        }

        $since = Carbon::now()->subWeek();

        return ! $user->emailDeliveries()
            ->where('type', EmailDelivery::TYPE_REMINDER)
            ->where('status', EmailDelivery::STATUS_SENT)
            ->where('sent_at', '>=', $since)
            ->exists();
    }

    /**
     * Record that a send was deliberately not made, and why.
     *
     * A skipped delivery is kept rather than deleted: "we chose not to send
     * this, because the review was already finished" is exactly what an
     * administrator needs to see when a reader asks why they got nothing
     * (FR-062, FR-068).
     */
    public function markSkipped(EmailDelivery $delivery, string $reason): void
    {
        $delivery->status = EmailDelivery::STATUS_SKIPPED;
        $delivery->error = $reason;
        $delivery->save();
    }

    public function markSent(EmailDelivery $delivery): void
    {
        $delivery->status = EmailDelivery::STATUS_SENT;
        $delivery->sent_at = Carbon::now();
        $delivery->error = null;
        $delivery->save();

        // Assume unopened until the provider says otherwise. The webhook
        // resets this to zero on any open, so the counter really does measure
        // a run of silence rather than a lifetime total (FR-064).
        $user = $delivery->user;
        $user->consecutive_unopened_emails++;
        $user->save();
    }

    public function markFailed(EmailDelivery $delivery, string $error): void
    {
        $delivery->status = EmailDelivery::STATUS_FAILED;
        $delivery->error = $error;
        $delivery->save();
    }

    /**
     * Try to claim the one delivery slot for this user, type and local day.
     *
     * @param  callable(EmailDelivery): void  $queue
     */
    private function claim(User $user, string $type, CarbonImmutable $localDay, callable $queue): ?EmailDelivery
    {
        $key = EmailDelivery::dedupeKeyFor($type, Carbon::parse($localDay->toDateString()));
        $now = Carbon::now();

        // insertOrIgnore rather than firstOrCreate: a duplicate silently loses
        // instead of throwing, which is the behaviour a five-minute scheduler
        // wants.
        $inserted = DB::table('email_deliveries')->insertOrIgnore([
            'user_id' => $user->id,
            'type' => $type,
            'recipient' => $user->email,
            'dedupe_key' => $key,
            'status' => EmailDelivery::STATUS_QUEUED,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return null;
        }

        // Read back through the relation. The ownership scope is only allowed
        // to be bypassed under app/Filament/ (Constitution art. III), and the
        // relation already constrains user_id — so there is nothing to bypass.
        $delivery = $user->emailDeliveries()->where('dedupe_key', $key)->sole();

        $queue($delivery);

        return $delivery;
    }
}

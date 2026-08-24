<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Jobs\SendReviewNudgePush;
use App\Models\EmailDelivery;
use App\Models\PushDelivery;
use App\Models\Review;
use App\Models\User;
use App\Services\Review\ReviewBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deciding whether to nudge, exactly once.
 *
 * The same shape as MailDispatcher, for the same reason: the sweep runs every
 * five minutes and may overlap itself, run twice, or be re-run by hand.
 * "At most one a day" is a UNIQUE (user_id, dedupe_key) index rather than a
 * check followed by a write, because the check and the write have five minutes
 * between them to be raced in (FR-143).
 *
 * The questions asked here are the cheap ones, asked before a job exists. The
 * expensive one — is the review finished *now* — is asked again by the worker,
 * because the reader may well finish it in the minutes between (FR-142).
 */
final class PushDispatcher
{
    public function __construct(private readonly ReviewBuilder $builder) {}

    /**
     * Queue today's nudge, if it is wanted and has not already been claimed.
     *
     * `$localDay` is the day the morning email belonged to, which where the
     * window crosses midnight is not the day the clock has reached — the caller
     * works that out, because it is the only one holding both instants
     * (contracts/console-and-jobs.md).
     *
     * Returns the delivery when this call is the one that queued it. Null
     * covers both "not wanted" and "somebody else got there first"; the reason
     * comes back through `$skipReason` for the sweep's report.
     */
    public function queueReviewNudge(User $user, CarbonImmutable $localDay, ?string &$skipReason = null): ?PushDelivery
    {
        $skipReason = $this->reasonNotToSend($user, $localDay);

        if ($skipReason !== null) {
            return null;
        }

        $delivery = $this->claim($user, $localDay);

        if ($delivery === null) {
            $skipReason = 'already_queued';

            return null;
        }

        SendReviewNudgePush::dispatch($delivery->id);

        return $delivery;
    }

    /**
     * Why this reader is not being nudged today, or null if they are.
     *
     * Ordered cheapest-first, and deliberately readable top to bottom: this is
     * the answer to "why did I not get a notification", and it should be
     * possible to answer that by reading rather than by running.
     */
    public function reasonNotToSend(User $user, CarbonImmutable $localDay): ?string
    {
        if (! $user->push_enabled) {
            return 'disabled';
        }

        if (! $user->canReceiveRitualEmail()) {
            return 'not_eligible';
        }

        // The nudge is about the morning email, so no email means nothing to
        // nudge about. A reader who has turned the daily mail off is not
        // quietly moved onto a different channel (FR-144).
        if (! $this->dailyEmailWentOut($user, $localDay)) {
            return 'no_daily_email';
        }

        $review = $this->builder->find($user, $localDay);

        if ($review === null) {
            return 'no_review';
        }

        if ($review->isCompleted()) {
            return 'review_completed';
        }

        // Nothing to send to. Not an error: the reader may have revoked
        // permission in the browser, which we only find out by looking.
        if (! $user->pushSubscriptions()->exists()) {
            return 'no_subscription';
        }

        return null;
    }

    /**
     * The day's own review, and whether it is still open — re-read at send
     * time by the worker rather than trusted from when the job was queued.
     */
    public function openReviewFor(User $user, CarbonImmutable $localDay): ?Review
    {
        $review = $this->builder->find($user, $localDay);

        return $review === null || $review->isCompleted() ? null : $review;
    }

    /**
     * Whether the morning email actually went out for this local day (FR-144).
     */
    public function dailyEmailWentOut(User $user, CarbonImmutable $localDay): bool
    {
        return $user->emailDeliveries()
            ->where('dedupe_key', EmailDelivery::dedupeKeyFor(
                EmailDelivery::TYPE_DAILY,
                Carbon::parse($localDay->toDateString()),
            ))
            ->where('status', EmailDelivery::STATUS_SENT)
            ->exists();
    }

    /**
     * A send was deliberately not made, and why.
     *
     * Kept rather than deleted, exactly as with mail: "we chose not to send
     * this, because the review was already finished" is the only thing this
     * table is ever asked.
     */
    public function markSkipped(PushDelivery $delivery, string $reason): void
    {
        $delivery->status = PushDelivery::STATUS_SKIPPED;
        $delivery->error = $reason;
        $delivery->save();
    }

    public function markSent(PushDelivery $delivery): void
    {
        $delivery->status = PushDelivery::STATUS_SENT;
        $delivery->sent_at = Carbon::now();
        $delivery->error = null;
        $delivery->save();
    }

    public function markFailed(PushDelivery $delivery, string $error): void
    {
        $delivery->status = PushDelivery::STATUS_FAILED;
        // The column is 255 and a library error can be an entire HTTP body.
        $delivery->error = mb_substr($error, 0, 255);
        $delivery->save();
    }

    /**
     * Try to claim the one nudge slot for this reader and local day.
     */
    private function claim(User $user, CarbonImmutable $localDay): ?PushDelivery
    {
        $key = PushDelivery::dedupeKeyFor(
            PushDelivery::TYPE_NUDGE,
            Carbon::parse($localDay->toDateString()),
        );

        $now = Carbon::now();

        // insertOrIgnore rather than firstOrCreate: the duplicate loses
        // silently instead of throwing, which is what a five-minute sweep
        // wants from a collision.
        $inserted = DB::table('push_deliveries')->insertOrIgnore([
            'user_id' => $user->id,
            'type' => PushDelivery::TYPE_NUDGE,
            'dedupe_key' => $key,
            'status' => PushDelivery::STATUS_QUEUED,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return null;
        }

        // Read back through the relation, which constrains `user_id` itself —
        // so there is no global scope to bypass in a console context
        // (Constitution art. III).
        return $user->pushDeliveries()->where('dedupe_key', $key)->sole();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Mastery\MasteryScheduler;
use App\Services\Streak\StreakService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What happens when the user deals with a card.
 *
 * The review screen is optimistic: the interface moves on before the request
 * lands, and failed requests are replayed from a local queue when the
 * connection returns (FR-041, R-10). That makes idempotency a requirement
 * rather than a nicety — a replayed `keep` must not increment `shown_count`
 * twice, or a highlight the user saw once would be pushed to the back of the
 * pool as if they had seen it repeatedly.
 *
 * So: the first call decides, later calls report. Side effects happen once.
 */
final class ReviewItemActions
{
    public function __construct(
        private readonly StreakService $streaks,
        private readonly MasteryScheduler $scheduler,
    ) {}

    /**
     * @param  array{action: string, favorite?: bool|null, source_frequency?: string|null, client_acted_at?: string|null}  $input
     * @return array<string, mixed>
     */
    public function apply(User $user, ReviewItem $item, array $input): array
    {
        $review = $item->review;

        $completedStreak = DB::transaction(function () use ($user, $item, $review, $input): ?CarbonImmutable {
            // Re-read inside the transaction and lock it: two tabs, or the
            // offline queue racing a live tap, must not both count as first.
            $locked = ReviewItem::query()->lockForUpdate()->findOrFail($item->id);

            if (! $locked->isActed()) {
                $this->recordAction($locked, $input);
            }

            $item->setRawAttributes($locked->getAttributes(), sync: true);

            return $this->completeIfFinished($user, $review);
        });

        return $this->response($item, $review->refresh(), $user, $completedStreak);
    }

    /**
     * The first call for this card. Everything here happens exactly once.
     *
     * @param  array{action: string, favorite?: bool|null, source_frequency?: string|null, client_acted_at?: string|null}  $input
     */
    private function recordAction(ReviewItem $item, array $input): void
    {
        $action = $input['action'];
        $actedAt = $this->actedAt($input);

        $item->action = $action;
        $item->acted_at = $actedAt;

        if ($item->item_type === ReviewItem::TYPE_MASTERY) {
            $this->recordMasteryFeedback($item, $input, $actedAt);
            $item->save();

            return;
        }

        $item->save();

        $highlight = $item->highlight;

        if ($highlight === null) {
            return;
        }

        if ($action === ReviewItem::ACTION_DISCARD) {
            // Hidden from future reviews, never deleted, and today's review
            // still shows it (FR-011, FR-013).
            $highlight->is_discarded = true;
        }

        // Counted on both actions: the user saw it either way, and the
        // cooldown is about exposure, not approval (FR-039).
        $highlight->shown_count++;
        $highlight->last_shown_at = $actedAt;

        if (($input['favorite'] ?? null) === true) {
            $highlight->is_favorite = true;
        }

        $highlight->save();

        $this->applySourceFrequency($item, $input);
    }

    /**
     * Reschedule a mastery card from the reader's answer.
     *
     * A card dealt with but given no feedback — swiped past, or replayed from
     * an older client — is treated as `later`. Leaving it unscheduled would
     * mean it never came back at all, which is worse than a slightly wrong
     * interval.
     *
     * @param  array{mastery_feedback?: string|null}  $input
     */
    private function recordMasteryFeedback(ReviewItem $item, array $input, Carbon $actedAt): void
    {
        $card = $item->masteryCard;

        if ($card === null) {
            return;
        }

        $feedback = $input['mastery_feedback'] ?? 'later';

        $item->mastery_feedback = $feedback;

        $this->scheduler->applyFeedback($card, $feedback, $actedAt);
    }

    /**
     * Adjusting how often a source appears, straight from the card (FR-038).
     * It takes effect from the next review; today's cards are already written.
     *
     * @param  array{source_frequency?: string|null}  $input
     */
    private function applySourceFrequency(ReviewItem $item, array $input): void
    {
        $frequency = $input['source_frequency'] ?? null;

        if ($frequency === null) {
            return;
        }

        $source = $item->highlight?->source;

        if ($source === null) {
            return;
        }

        $source->frequency = $frequency;
        $source->save();
    }

    /**
     * Mark the review finished once nothing is left untouched.
     *
     * Returns the streak day if this call is what completed it, so the caller
     * can tell the user — and null otherwise, including on a replay.
     */
    private function completeIfFinished(User $user, Review $review): ?CarbonImmutable
    {
        if ($review->isCompleted()) {
            return null;
        }

        $remaining = $review->items()->whereNull('acted_at')->count();

        if ($remaining > 0) {
            return null;
        }

        $review->status = Review::STATUS_COMPLETED;
        $review->completed_at = Carbon::now();
        $review->save();

        return $this->streaks->recordCompletion($user);
    }

    /**
     * Honour the timestamp the client recorded, so an action taken offline is
     * dated when it happened rather than when the connection returned.
     * Clamped to now: a device with a wrong clock must not push a highlight's
     * cooldown into the future.
     *
     * @param  array{client_acted_at?: string|null}  $input
     */
    private function actedAt(array $input): Carbon
    {
        $now = Carbon::now();
        $claimed = $input['client_acted_at'] ?? null;

        if (! is_string($claimed) || $claimed === '') {
            return $now;
        }

        $parsed = Carbon::parse($claimed);

        return $parsed->greaterThan($now) ? $now : $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function response(ReviewItem $item, Review $review, User $user, ?CarbonImmutable $streakDay): array
    {
        $remaining = $review->items()->whereNull('acted_at')->count();
        $completed = $review->isCompleted();

        return [
            'item_id' => $item->id,
            'acted_at' => $item->acted_at?->toIso8601String(),
            'review' => [
                'remaining' => $remaining,
                'completed' => $completed,
            ],
            // Only populated by the call that actually completed the review,
            // so a replay does not re-announce the streak.
            'streak' => $streakDay === null ? null : [
                'current' => $user->refresh()->current_streak,
                'longest' => $user->longest_streak,
                'day' => $streakDay->toDateString(),
            ],
            'mastery' => $this->masteryPayload($item),
        ];
    }

    /**
     * What the card's new schedule looks like, so the interface can say when
     * it will be back.
     *
     * @return array<string, mixed>|null
     */
    private function masteryPayload(ReviewItem $item): ?array
    {
        $card = $item->masteryCard?->refresh();

        if ($card === null) {
            return null;
        }

        return [
            'half_life_days' => $card->half_life_days,
            'due_at' => $card->due_at?->toIso8601String(),
            // Offered only once the reader has asked to see it sooner enough
            // times to say something. The card is never rewritten for them
            // (FR-052).
            'hint' => $card->isStruggling() ? __('mastery.struggle_hint') : null,
        ];
    }
}

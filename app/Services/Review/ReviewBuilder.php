<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\Mastery\MasteryScheduler;
use App\Services\Time\LocalDayResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds a user's review for a local day, once.
 *
 * "Once" is the hard part. The dispatch command runs every five minutes and
 * may collide with the user opening the app; two generations for one day would
 * mean the email and the screen disagree about what today is, which is the
 * one thing this product cannot get wrong (FR-025, SC-015).
 *
 * The guarantee is the UNIQUE (user_id, review_date) index, not a lock and not
 * a check-then-insert: whichever writer loses the race catches the constraint
 * violation and reads back the row that won.
 *
 * This is the first version. Weighted sampling (US2) and mastery cards (US3)
 * arrive by replacing the candidate query, not by changing this contract.
 */
final class ReviewBuilder
{
    public function __construct(
        private readonly LocalDayResolver $days,
        private readonly HighlightSampler $sampler,
        private readonly MasteryScheduler $mastery,
    ) {}

    /**
     * Return the user's review for a local day, generating it if needed.
     *
     * Returns null when there is nothing to show: a user with no eligible
     * highlights gets no review at all rather than an empty one, which is
     * also what stops the pipeline mailing them a blank page (FR-037).
     */
    public function buildFor(User $user, ?CarbonImmutable $localDay = null): ?Review
    {
        $day = $localDay ?? $this->days->localDayFor($user);

        $existing = $this->find($user, $day);

        if ($existing instanceof Review) {
            return $existing;
        }

        // Mastery cards take their share first, but only as many as are
        // actually due. Whatever the ratio does not claim — or claims but
        // cannot fill — goes back to ordinary highlights, so a reader with no
        // cards yet still gets a full review (FR-034, FR-036).
        $masteryQuota = (int) floor($user->review_size * ($user->mastery_ratio / 100));
        $cards = $masteryQuota > 0
            ? $this->mastery->dueCards($user, $masteryQuota)->all()
            : [];

        $highlights = $this->sampler->sample($user, $user->review_size - count($cards), $day);

        if ($highlights === [] && $cards === []) {
            return null;
        }

        try {
            return DB::transaction(fn (): Review => $this->create($user, $day, $highlights, $cards));
        } catch (UniqueConstraintViolationException) {
            // Someone else built it between the check and the insert. Their
            // review is as valid as ours would have been; use it.
            return $this->find($user, $day);
        }
    }

    public function find(User $user, CarbonImmutable $localDay): ?Review
    {
        return $user->reviews()
            ->with($this->relations())
            ->where('review_date', $localDay->toDateString())
            ->first();
    }

    /**
     * @param  array<int, Highlight>  $highlights
     * @param  array<int, MasteryCard>  $cards
     */
    private function create(User $user, CarbonImmutable $localDay, array $highlights, array $cards): Review
    {
        $review = new Review;
        $review->user_id = $user->id;
        $review->review_date = Carbon::parse($localDay->toDateString());

        // The size recorded is what was actually built, not what was asked
        // for. A user with four eligible highlights and a size of eight gets
        // a four-card review rather than an error (FR-036).
        $review->size = count($highlights) + count($cards);
        $review->status = Review::STATUS_PENDING;
        $review->save();

        $now = Carbon::now();
        $position = 0;
        $rows = [];

        // Highlights first, then mastery cards. Reading comes before being
        // asked questions: the ritual should open gently (FR-035).
        foreach ($highlights as $highlight) {
            $rows[] = [
                'user_id' => $user->id,
                'review_id' => $review->id,
                'position' => ++$position,
                'item_type' => ReviewItem::TYPE_HIGHLIGHT,
                'highlight_id' => $highlight->id,
                'mastery_card_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($cards as $card) {
            $rows[] = [
                'user_id' => $user->id,
                'review_id' => $review->id,
                'position' => ++$position,
                'item_type' => ReviewItem::TYPE_MASTERY,
                'highlight_id' => null,
                'mastery_card_id' => $card->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ReviewItem::query()->insert($rows);

        return $review->load($this->relations());
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return ['items.highlight.source', 'items.masteryCard.highlight'];
    }
}

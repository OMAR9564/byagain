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
 * The guarantee is the UNIQUE (user_id, review_date, round) index, not a lock
 * and not a check-then-insert: whichever writer loses the race catches the
 * constraint violation and reads back the row that won.
 *
 * A day may hold further rounds if the reader asks for them, but only round 1
 * is "the day": `buildFor` and `find` mean round 1 and nothing else, so the
 * pipeline, the email and the reminder are untouched by any of it.
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

        return $this->generate($user, $day, Review::FIRST_ROUND);
    }

    /**
     * Build the next round of a day that already has one.
     *
     * Returns null when there is nothing left to draw on — a reader who has
     * seen everything eligible gets told so, rather than handed the same cards
     * again with a different heading.
     */
    public function buildNextRound(User $user, ?CarbonImmutable $localDay = null): ?Review
    {
        $day = $localDay ?? $this->days->localDayFor($user);
        $latest = $this->latestFor($user, $day);

        if ($latest === null) {
            return $this->buildFor($user, $day);
        }

        if (! $latest->isCompleted()) {
            // Nothing to add to: the round they are in is still open.
            return $latest;
        }

        if ($latest->round >= (int) config('byagain.review.max_rounds_per_day')) {
            return null;
        }

        return $this->generate($user, $day, $latest->round + 1);
    }

    /**
     * Whether another round could be built today, without building it.
     *
     * The done screen needs this because `GET /review` no longer opens rounds
     * on its own (FR-101): with nothing generated there is nothing to read the
     * answer off, and offering a button that returns "nothing new to draw on"
     * is worse than not offering it (R-205).
     *
     * Deliberately mirrors the one condition on which `generate()` gives up —
     * both wells empty — so the button and the action cannot disagree. Writes
     * nothing: no review, no item, no `last_shown_at`.
     */
    public function hasMaterialFor(User $user, CarbonImmutable $localDay): bool
    {
        // One row is the whole question. `exists()` stops at the first match
        // rather than paying for the weighted ordering of a real draw.
        if ($this->sampler->candidates($user, $localDay)->exists()) {
            return true;
        }

        // Mastery cards only count when the ratio actually reserves room for
        // them, because that is the only case in which `generate()` asks
        // (FR-034).
        $masteryQuota = (int) floor($user->review_size * ($user->mastery_ratio / 100));

        return $masteryQuota > 0 && $this->mastery->dueCards($user, 1)->isNotEmpty();
    }

    /**
     * The most recent round of a local day — what the screen shows.
     */
    public function latestFor(User $user, CarbonImmutable $localDay): ?Review
    {
        return $user->reviews()
            ->with($this->relations())
            ->where('review_date', $localDay->toDateString())
            ->orderByDesc('round')
            ->first();
    }

    /**
     * How many rounds this local day has produced so far.
     */
    public function roundsToday(User $user, CarbonImmutable $localDay): int
    {
        return $user->reviews()
            ->where('review_date', $localDay->toDateString())
            ->count();
    }

    private function generate(User $user, CarbonImmutable $day, int $round): ?Review
    {
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
            return DB::transaction(fn (): Review => $this->create($user, $day, $round, $highlights, $cards));
        } catch (UniqueConstraintViolationException) {
            // Someone else built it between the check and the insert. Their
            // review is as valid as ours would have been; use it.
            return $this->findRound($user, $day, $round);
        }
    }

    /**
     * The day's own review: round 1, the one everything outside the screen
     * means when it says "today's review".
     */
    public function find(User $user, CarbonImmutable $localDay): ?Review
    {
        return $this->findRound($user, $localDay, Review::FIRST_ROUND);
    }

    private function findRound(User $user, CarbonImmutable $localDay, int $round): ?Review
    {
        return $user->reviews()
            ->with($this->relations())
            ->where('review_date', $localDay->toDateString())
            ->where('round', $round)
            ->first();
    }

    /**
     * @param  array<int, Highlight>  $highlights
     * @param  array<int, MasteryCard>  $cards
     */
    private function create(User $user, CarbonImmutable $localDay, int $round, array $highlights, array $cards): Review
    {
        $review = new Review;
        $review->user_id = $user->id;
        $review->review_date = Carbon::parse($localDay->toDateString());
        $review->round = $round;

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
        return Review::cardRelations();
    }
}

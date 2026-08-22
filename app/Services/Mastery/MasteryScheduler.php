<?php

declare(strict_types=1);

namespace App\Services\Mastery;

use App\Models\MasteryCard;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * When a card comes back.
 *
 * The model is a half-life: recall probability is p(t) = 2^(-Δt / H), and
 * feedback stretches or shrinks H. There is deliberately no right or wrong
 * answer anywhere in this class. The reader is asked when they want to see
 * the card again, not whether they got it — a question you cannot fail is a
 * question you keep answering honestly (SPEC 6, FR-046).
 *
 * The first feedback sets the half-life outright, because there is no prior
 * interval to scale. Every later one multiplies it, and the result is clamped
 * so a card never becomes daily noise nor effectively dead (FR-047…FR-049).
 */
final class MasteryScheduler
{
    /**
     * Apply feedback and reschedule.
     */
    public function applyFeedback(MasteryCard $card, string $feedback, ?Carbon $at = null): MasteryCard
    {
        $now = $at ?? Carbon::now();

        if ($feedback === (string) config('byagain.mastery.retire_feedback')) {
            return $this->retire($card, $now);
        }

        $card->half_life_days = $this->nextHalfLife($card, $feedback);

        // `sooner` is the reader saying this keeps slipping. Counting it is
        // what lets the interface eventually offer a hint rather than letting
        // them grind the same card forever (FR-052).
        if ($feedback === 'sooner') {
            $card->struggle_count++;
        }

        $card->last_reviewed_at = $now;
        $card->due_at = $now->copy()->addDays($card->half_life_days);
        $card->review_count++;
        $card->save();

        return $card;
    }

    /**
     * "I know this." The card stops appearing; it is never deleted (FR-050).
     */
    public function retire(MasteryCard $card, ?Carbon $at = null): MasteryCard
    {
        $card->status = MasteryCard::STATUS_RETIRED;
        $card->last_reviewed_at = $at ?? Carbon::now();
        $card->due_at = null;
        $card->review_count++;
        $card->save();

        return $card;
    }

    /**
     * Cards ready to come round again, closest to being forgotten first.
     *
     * Ordering by elapsed-over-half-life is the same ordering as ascending
     * recall probability, without asking the database to evaluate a power
     * (FR-051). Ties are broken randomly so the same handful does not lead
     * every morning.
     *
     * @return Collection<int, MasteryCard>
     */
    public function dueCards(User $user, int $limit, ?Carbon $at = null): Collection
    {
        $now = $at ?? Carbon::now();

        return $user->masteryCards()
            ->where('status', MasteryCard::STATUS_ACTIVE)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now)
            ->with('highlight')
            ->orderByRaw(
                'TIMESTAMPDIFF(SECOND, last_reviewed_at, ?) / (half_life_days * 86400) DESC, RAND()',
                [$now],
            )
            ->limit($limit)
            ->get();
    }

    /**
     * Probability the reader still holds this, as p = 2^(-Δt / H).
     */
    public function recallProbability(MasteryCard $card, ?Carbon $at = null): float
    {
        return $card->recallProbability($at);
    }

    /**
     * The half-life this feedback produces.
     */
    private function nextHalfLife(MasteryCard $card, string $feedback): float
    {
        /** @var array<string, int> $initial */
        $initial = config('byagain.mastery.initial_half_lives');

        /** @var array<string, float> $multipliers */
        $multipliers = config('byagain.mastery.multipliers');

        if (! array_key_exists($feedback, $multipliers)) {
            throw new InvalidArgumentException("Unknown mastery feedback [{$feedback}].");
        }

        $value = $card->review_count === 0
            ? (float) $initial[$feedback]
            : $card->half_life_days * $multipliers[$feedback];

        return $this->clamp($value);
    }

    private function clamp(float $days): float
    {
        $min = (float) config('byagain.mastery.min_half_life');
        $max = (float) config('byagain.mastery.max_half_life');

        return max($min, min($max, $days));
    }
}

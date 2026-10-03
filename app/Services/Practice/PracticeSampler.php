<?php

declare(strict_types=1);

namespace App\Services\Practice;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Draw a practice set from a source, mixing passages and cards.
 *
 * Unlike the daily review's HighlightSampler, this does not apply any of
 * the filters (cooldown, 3-day block, novelty multiplier, source frequency,
 * weight, passage length) — practice is a student choosing to work one source
 * without the schedule's constraints, and the set is not recorded. This keeps
 * practice from ever touching the streak, scheduled review, or mastery plan
 * (FR-203, R-305).
 *
 * Both passages and active mastery cards from this source are included,
 * mixed by the user's mastery_ratio with shortfall fill, and shuffled
 * (FR-202, SC-201).
 */
final class PracticeSampler
{
    /**
     * Draw passages and cards for practice on a source.
     *
     * @return Collection<int, array{type: string, model: Highlight|MasteryCard}>
     */
    public function draw(User $user, Source $source): Collection
    {
        // Passages: this source, not discarded
        $passageQuery = Highlight::query()
            ->where('source_id', $source->id)
            ->where('user_id', $user->id)
            ->where('is_discarded', false)
            ->inRandomOrder()
            ->with('source');

        // Cards: active, whose highlight belongs to this source and is not discarded
        $cardQuery = MasteryCard::query()
            ->where('status', MasteryCard::STATUS_ACTIVE)
            ->where('user_id', $user->id)
            ->whereHas('highlight', fn ($q) => $q
                ->where('source_id', $source->id)
                ->where('is_discarded', false)
            )
            ->inRandomOrder()
            ->with('highlight.source');

        return ItemMixer::mix($user, $user->review_size, $passageQuery, $cardQuery);
    }

    /**
     * Count active (non-discarded) passages in a source.
     * Used to check if practice should redirect (FR-206).
     */
    public function countActivePassages(Source $source): int
    {
        return $source->highlights()
            ->where('is_discarded', false)
            ->count();
    }
}

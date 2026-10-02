<?php

declare(strict_types=1);

namespace App\Services\Practice;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Draw a practice set from a source.
 *
 * Unlike the daily review's HighlightSampler, this does not apply any of
 * the filters (cooldown, 3-day block, novelty multiplier, source frequency,
 * weight, passage length) — practice is a student choosing to work one source
 * without the schedule's constraints, and the set is not recorded. This keeps
 * practice from ever touching the streak, scheduled review, or mastery plan
 * (FR-203, R-305).
 */
final class PracticeSampler
{
    /**
     * @return Collection<int, Highlight>
     */
    public function draw(User $user, Source $source): Collection
    {
        return $source->highlights()
            ->where('is_discarded', false)
            ->inRandomOrder()
            ->limit($user->review_size)
            ->with('source')
            ->get();
    }
}

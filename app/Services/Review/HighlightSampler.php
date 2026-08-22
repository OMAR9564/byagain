<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Highlight;
use App\Models\User;
use App\Services\Time\LocalDayResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Chooses which highlights make up a review.
 *
 * This is the first version: it applies the full eligibility filter but picks
 * at random within it. Weighting by source frequency, cooldown and novelty
 * (SPEC 4.1, FR-027) replaces the ordering in US2 — the candidate filter and
 * this signature stay as they are.
 */
final class HighlightSampler
{
    public function __construct(private readonly LocalDayResolver $days) {}

    /**
     * @return array<int, Highlight>
     */
    public function sample(User $user, int $size, CarbonImmutable $localDay): array
    {
        return $this->candidates($user, $localDay)
            ->inRandomOrder()
            ->limit($size)
            ->get()
            ->all();
    }

    /**
     * Everything the user could be shown today.
     *
     * @return Builder<Highlight>
     */
    public function candidates(User $user, CarbonImmutable $localDay): Builder
    {
        $query = Highlight::query()
            ->where('highlights.user_id', $user->id)
            ->where('highlights.is_discarded', false)
            ->whereHas('source', function (Builder $source): void {
                $source->where('is_archived', false)
                    ->where('frequency', '!=', config('byagain.sampling.excluded_source_frequency'));
            })
            ->with('source');

        $this->applyRecencyBlock($query, $user, $localDay);
        $this->applyQualityFilter($query, $user);

        return $query;
    }

    /**
     * A highlight shown recently does not come back yet.
     *
     * Measured from the start of the local day rather than from "now", so the
     * block is a whole number of the user's days however late in the evening
     * they get to it (FR-029, SC-009).
     *
     * @param  Builder<Highlight>  $query
     */
    private function applyRecencyBlock(Builder $query, User $user, CarbonImmutable $localDay): void
    {
        $blockDays = (int) config('byagain.sampling.block_days');

        [$dayStart] = $this->days->windowForLocalDay($user, $localDay);
        $cutoff = $dayStart->subDays($blockDays);

        $query->where(function (Builder $q) use ($cutoff): void {
            $q->whereNull('highlights.last_shown_at')
                ->orWhere('highlights.last_shown_at', '<', $cutoff);
        });
    }

    /**
     * Optionally skip very short highlights. Code is always exempt: three
     * lines of code is a complete thought where three words of prose is not
     * (FR-031).
     *
     * @param  Builder<Highlight>  $query
     */
    private function applyQualityFilter(Builder $query, User $user): void
    {
        if (! $user->quality_filter_enabled) {
            return;
        }

        $minimum = (int) config('byagain.sampling.quality_min_chars');

        $query->where(function (Builder $q) use ($minimum): void {
            $q->where('highlights.char_count', '>=', $minimum)
                ->orWhere('highlights.contains_code', true);
        });
    }
}

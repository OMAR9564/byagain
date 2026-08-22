<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Highlight;
use App\Models\User;
use App\Services\Time\LocalDayResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Chooses which highlights make up a review — the "right passage on the right
 * day" half of the product (SPEC 4.1, FR-027…FR-033).
 *
 * Three multipliers combine into one weight:
 *
 *     weight = source_weight
 *            * (1 - exp(-days_since_shown / tau))     cooldown
 *            * (novelty ? 1.5 : 1.0)
 *            / (equal_source_weighting ? source_size : 1)
 *
 * Cooldown is the interesting one. Something shown yesterday is nearly
 * weightless; something untouched for two months is back at full strength.
 * It decays smoothly rather than switching on at a threshold, so there is no
 * cliff where a highlight suddenly becomes eligible again.
 *
 * Selection uses `ORDER BY -LOG(RAND()) / weight` — exponential jumping, the
 * Efraimidis–Spirakis method. It draws without replacement in proportion to
 * weight in a single pass, which is what keeps this inside 300ms on a 20.000
 * highlight account (SC-004). Pulling the pool into PHP to weight it there
 * would be both slower and far more memory than it is worth.
 *
 * The raw SQL here is constant text with every value bound — the narrow
 * exception the constitution allows for the fixed mathematics of SPEC 4.1.
 */
final class HighlightSampler
{
    /**
     * How many rows to over-fetch before the per-source quota is applied.
     *
     * The quota can reject a candidate, so the query has to offer more than
     * the review needs. Three times is enough in practice because the quota
     * itself is n/3 (R-04).
     */
    private const int OVERSAMPLE_FACTOR = 3;

    public function __construct(private readonly LocalDayResolver $days) {}

    /**
     * @return array<int, Highlight>
     */
    public function sample(User $user, int $size, CarbonImmutable $localDay): array
    {
        $quota = (int) ceil($size / (int) config('byagain.review.source_quota_divisor'));

        $fetch = $size * self::OVERSAMPLE_FACTOR;
        $candidates = [];

        // Fetch, then widen. A quota-limited pass can come up short simply
        // because the draw was dominated by one large source — that is a
        // sample too small, not a pool too small, and the fix is to look at
        // more rows rather than to abandon the quota.
        while (true) {
            $candidates = $this->weighted($user, $localDay)->limit($fetch)->get()->all();
            $chosen = $this->takeWithin($candidates, $size, $quota);

            if (count($chosen) >= $size || count($candidates) < $fetch) {
                break;
            }

            $fetch *= self::OVERSAMPLE_FACTOR;
        }

        return $this->applySourceQuota($candidates, $size);
    }

    /**
     * Eligible highlights, ordered so that taking the first k is a weighted
     * random draw.
     *
     * @return Builder<Highlight>
     */
    public function weighted(User $user, CarbonImmutable $localDay): Builder
    {
        $query = $this->candidates($user, $localDay);

        [$expression, $bindings] = $this->weightExpression($user);

        return $query
            ->select('highlights.*')
            ->selectRaw("({$expression}) as sampling_weight", $bindings)
            // GREATEST keeps the divisor away from zero: a highlight shown a
            // moment ago has a cooldown term of almost exactly 0, and dividing
            // by it would be a fatal rather than simply a low priority.
            ->orderByRaw("-LOG(RAND()) / GREATEST({$expression}, 1e-9)", $bindings);
    }

    /**
     * Everything the user could be shown today.
     *
     * @return Builder<Highlight>
     */
    public function candidates(User $user, CarbonImmutable $localDay): Builder
    {
        // A join rather than whereHas: the weight expression needs columns
        // from `sources`, and a correlated subquery would be evaluated per
        // row on a table that can hold 20.000 of them.
        $query = Highlight::query()
            ->join('sources', 'sources.id', '=', 'highlights.source_id')
            ->where('highlights.user_id', $user->id)
            ->where('highlights.is_discarded', false)
            ->where('sources.is_archived', false)
            ->where('sources.frequency', '!=', config('byagain.sampling.excluded_source_frequency'))
            ->with('source');

        $this->applyRecencyBlock($query, $user, $localDay);
        $this->applyQualityFilter($query, $user);

        return $query;
    }

    /**
     * The weight expression and its bindings.
     *
     * Returned as a pair so the same text can be used in both the SELECT and
     * the ORDER BY without the two drifting apart.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function weightExpression(User $user): array
    {
        /** @var array<string, float> $weights */
        $weights = config('byagain.sampling.source_weights');

        $bindings = [];

        // Frequency tier → multiplier, as a CASE the database can evaluate.
        // The keys come from config, so the tiers cannot drift out of step
        // with what the settings screen offers (Constitution art. V).
        $cases = '';

        foreach ($weights as $tier => $multiplier) {
            $cases .= ' WHEN ? THEN ?';
            $bindings[] = $tier;
            $bindings[] = $multiplier;
        }

        $sourceWeight = "CASE sources.frequency{$cases} ELSE 1.0 END";

        // Cooldown. A highlight never shown skips the decay entirely and
        // sits at full weight (data-model.md).
        $now = Carbon::now();
        $cooldown = 'CASE WHEN highlights.last_shown_at IS NULL THEN 1.0 '
            .'ELSE 1 - EXP(-(TIMESTAMPDIFF(SECOND, highlights.last_shown_at, ?) / 86400.0) / ?) END';
        $bindings[] = $now;
        $bindings[] = (float) config('byagain.sampling.cooldown_tau');

        // Novelty: never shown, or added recently enough to still feel new.
        $novelty = 'CASE WHEN highlights.last_shown_at IS NULL OR highlights.created_at >= ? THEN ? ELSE 1.0 END';
        $bindings[] = $now->copy()->subDays((int) config('byagain.sampling.novelty_window_days'));
        $bindings[] = (float) config('byagain.sampling.novelty_multiplier');

        // Equal source weighting: dividing by the source's size cancels out
        // the advantage of simply having more highlights in it (FR-032).
        $divisor = $user->equal_source_weighting
            ? 'GREATEST(sources.highlights_count, 1)'
            : '1';

        return ["({$sourceWeight}) * ({$cooldown}) * ({$novelty}) / {$divisor}", $bindings];
    }

    /**
     * No single source may supply more than ceil(n / divisor) cards, so one
     * large book cannot crowd out everything else (FR-033).
     *
     * Applied in PHP rather than in SQL on purpose: the block is a filter and
     * the quota is a selection constraint, and expressing both in one
     * statement needs a window function wrapped around the weighted ordering —
     * correct, but not something anyone could read or test (R-05).
     *
     * @param  array<int, Highlight>  $candidates
     * @return array<int, Highlight>
     */
    private function applySourceQuota(array $candidates, int $size): array
    {
        $quota = (int) ceil($size / (int) config('byagain.review.source_quota_divisor'));

        // By the time this runs the candidate list is as wide as the pool
        // allows, so falling short here means the library really is that
        // narrow — most often a reader with a single book. Rather than
        // abandoning the cap the moment it bites, raise it a step at a time:
        // a reader with four books keeps a real limit, and a reader with one
        // ends up at quota == size, which is the same as no limit at all
        // (FR-033, FR-036).
        for ($limit = $quota; $limit <= $size; $limit++) {
            $chosen = $this->takeWithin($candidates, $size, $limit);

            if (count($chosen) >= $size) {
                return $chosen;
            }
        }

        // Still short means the pool itself is small, not that the quota is
        // in the way. A shorter review is the correct answer.
        return $chosen ?? [];
    }

    /**
     * Take up to $size candidates, allowing at most $limit from any one
     * source, preserving the weighted order.
     *
     * @param  array<int, Highlight>  $candidates
     * @return array<int, Highlight>
     */
    private function takeWithin(array $candidates, int $size, int $limit): array
    {
        $chosen = [];
        $perSource = [];

        foreach ($candidates as $highlight) {
            if (count($chosen) >= $size) {
                break;
            }

            $used = $perSource[$highlight->source_id] ?? 0;

            if ($used >= $limit) {
                continue;
            }

            $chosen[] = $highlight;
            $perSource[$highlight->source_id] = $used + 1;
        }

        return $chosen;
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

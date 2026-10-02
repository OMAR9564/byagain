<?php

declare(strict_types=1);

namespace App\Services\Practice;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Draw an endless shuffled batch mixing passages and mastery cards.
 *
 * The Mix is practice across all sources without the daily schedule's filters,
 * cooldown, or streaks. Each item drawn is either a Highlight passage or an
 * active MasteryCard, shuffled together and weighted by the user's mastery_ratio
 * (the percentage of cards vs. passages they want to practice).
 *
 * Like PracticeSampler, Mix leaves no trace: shown_count, last_shown_at, and
 * card schedules never move. The reader sees "Next" instead of scheduling
 * choices (FR-203, R-305).
 */
final class MixSampler
{
    /**
     * Draw a mix of passages and cards for endless practice.
     *
     * @return Collection<int, array{type: string, model: Highlight|MasteryCard}>
     */
    public function draw(User $user): Collection
    {
        $batchSize = (int) config('byagain.mix.batch_size');

        return ItemMixer::mix(
            $user,
            $batchSize,
            $this->passageQuery($user),
            $this->cardQuery($user),
        );
    }

    /**
     * Passages eligible for Mix: non-discarded, from non-archived sources
     * whose frequency is not "never". Scoped to the user explicitly as well as
     * by the model's global scope, so the query stays safe if that ever moves.
     *
     * @return Builder<Highlight>
     */
    private function passageQuery(User $user): Builder
    {
        return Highlight::query()
            ->whereHas('source', fn ($q) => $q
                ->where('is_archived', false)
                ->where('frequency', '!=', config('byagain.sampling.excluded_source_frequency'))
            )
            ->where('is_discarded', false)
            ->where('user_id', $user->id)
            ->inRandomOrder()
            ->with('source');
    }

    /**
     * Cards eligible for Mix: active, and whose passage was not discarded.
     *
     * @return Builder<MasteryCard>
     */
    private function cardQuery(User $user): Builder
    {
        return MasteryCard::query()
            ->where('status', MasteryCard::STATUS_ACTIVE)
            ->where('user_id', $user->id)
            ->whereHas('highlight', fn ($q) => $q->where('is_discarded', false))
            ->inRandomOrder()
            ->with('highlight.source');
    }
}

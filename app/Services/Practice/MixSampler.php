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
        $masteryRatio = $user->mastery_ratio;

        // Calculate how many cards vs passages to aim for, based on the user's
        // mastery_ratio. For example, ratio 50 and batch_size 20 means 10 each.
        $targetCards = (int) round($batchSize * $masteryRatio / 100);
        $targetPassages = $batchSize - $targetCards;

        $passages = $this->passageQuery($user)
            ->limit($targetPassages)
            ->get();

        $cards = $this->cardQuery($user)
            ->limit($targetCards)
            ->get();

        // If one kind is short, fill from the other so we always return
        // a full batch if possible.
        if ($passages->count() < $targetPassages) {
            $cards = $cards->concat(
                $this->cardQuery($user)
                    ->whereNotIn('id', $cards->pluck('id'))
                    ->limit($targetPassages - $passages->count())
                    ->get()
            );
        }

        if ($cards->count() < $targetCards) {
            $passages = $passages->concat(
                $this->passageQuery($user)
                    ->whereNotIn('id', $passages->pluck('id'))
                    ->limit($targetCards - $cards->count())
                    ->get()
            );
        }

        // Construct uniform items and shuffle them together.
        return Collection::make()
            ->concat($passages->map(fn (Highlight $h): array => [
                'type' => 'highlight',
                'model' => $h,
            ]))
            ->concat($cards->map(fn (MasteryCard $c): array => [
                'type' => 'card',
                'model' => $c,
            ]))
            ->shuffle();
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

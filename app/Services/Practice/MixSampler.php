<?php

declare(strict_types=1);

namespace App\Services\Practice;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\User;
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

        // Fetch candidates. Passages: non-discarded, from non-archived sources
        // with frequency != never. Cards: status = active only.
        $passages = Highlight::query()
            ->whereHas('source', fn ($q) => $q
                ->where('is_archived', false)
                ->where('frequency', '!=', config('byagain.sampling.excluded_source_frequency'))
            )
            ->where('is_discarded', false)
            ->where('user_id', $user->id)
            ->inRandomOrder()
            ->limit($targetPassages)
            ->with('source')
            ->get();

        $cards = MasteryCard::query()
            ->where('status', MasteryCard::STATUS_ACTIVE)
            ->where('user_id', $user->id)
            ->whereHas('highlight', fn ($q) => $q->where('is_discarded', false))
            ->inRandomOrder()
            ->limit($targetCards)
            ->with('highlight.source')
            ->get();

        // If one kind is short, fill from the other so we always return
        // a full batch if possible.
        if ($passages->count() < $targetPassages) {
            $shortfall = $targetPassages - $passages->count();
            $additional = MasteryCard::query()
                ->where('status', MasteryCard::STATUS_ACTIVE)
                ->where('user_id', $user->id)
                ->whereHas('highlight', fn ($q) => $q->where('is_discarded', false))
                ->whereNotIn('id', $cards->pluck('id'))
                ->inRandomOrder()
                ->limit($shortfall)
                ->with('highlight.source')
                ->get();

            $cards = $cards->concat($additional);
        }

        if ($cards->count() < $targetCards) {
            $shortfall = $targetCards - $cards->count();
            $additional = Highlight::query()
                ->whereHas('source', fn ($q) => $q
                    ->where('is_archived', false)
                    ->where('frequency', '!=', config('byagain.sampling.excluded_source_frequency'))
                )
                ->where('is_discarded', false)
                ->where('user_id', $user->id)
                ->whereNotIn('id', $passages->pluck('id'))
                ->inRandomOrder()
                ->limit($shortfall)
                ->with('source')
                ->get();

            $passages = $passages->concat($additional);
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
}

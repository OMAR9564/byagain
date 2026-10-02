<?php

declare(strict_types=1);

namespace App\Services\Practice;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Mix passages and mastery cards by ratio, with shortfall fill.
 *
 * Both source-specific practice and endless Mix use this to balance
 * passages and cards: aim for a target ratio, fill underages from
 * the other kind, then shuffle (FR-203, SPEC 10.2).
 *
 * This keeps the draw logic in one place so changes to the mix
 * strategy apply everywhere practice or Mix happens.
 */
final class ItemMixer
{
    /**
     * Draw a mix of passages and cards, with optional source scope.
     *
     * @param  Builder<Highlight>|null  $passageQueryBuilder  Query builder for passages (can be pre-scoped)
     * @param  Builder<MasteryCard>|null  $cardQueryBuilder  Query builder for cards (can be pre-scoped)
     * @return Collection<int, array{type: string, model: Highlight|MasteryCard}>
     */
    public static function mix(
        User $user,
        int $batchSize,
        ?Builder $passageQueryBuilder = null,
        ?Builder $cardQueryBuilder = null,
    ): Collection {
        $masteryRatio = $user->mastery_ratio;

        // Calculate how many cards vs passages to aim for
        $targetCards = (int) round($batchSize * $masteryRatio / 100);
        $targetPassages = $batchSize - $targetCards;

        // Use provided query builders or create empty queries to be filled
        $passages = ($passageQueryBuilder ?? Highlight::query())
            ->limit($targetPassages)
            ->get();

        $cards = ($cardQueryBuilder ?? MasteryCard::query())
            ->limit($targetCards)
            ->get();

        // Fill shortfall: if passages are short, draw more cards
        if ($passages->count() < $targetPassages && $cardQueryBuilder !== null) {
            $cards = $cards->concat(
                $cardQueryBuilder
                    ->whereNotIn('id', $cards->pluck('id'))
                    ->limit($targetPassages - $passages->count())
                    ->get()
            );
        }

        // If cards are short, draw more passages
        if ($cards->count() < $targetCards && $passageQueryBuilder !== null) {
            $passages = $passages->concat(
                $passageQueryBuilder
                    ->whereNotIn('id', $passages->pluck('id'))
                    ->limit($targetCards - $cards->count())
                    ->get()
            );
        }

        // Construct uniform items and shuffle them together
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

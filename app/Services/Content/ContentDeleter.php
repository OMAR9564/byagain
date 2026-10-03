<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\ReviewItem;
use App\Models\Source;
use Illuminate\Support\Facades\DB;

/**
 * Permanently delete sources, passages, and cards.
 *
 * Deletion must remove unacted review items pointing at the deleted content,
 * because they would otherwise block review completion forever. Acted items
 * stay as history with null foreign keys.
 */
final class ContentDeleter
{
    /**
     * Delete a source and all its passages and cards.
     */
    public function deleteSource(Source $source): void
    {
        DB::transaction(function () use ($source): void {
            // Collect all highlight IDs that will be deleted.
            $highlightIds = $source->highlights()->pluck('id')->toArray();

            // Delete unacted review items pointing at these highlights or their cards.
            ReviewItem::query()
                ->whereNull('acted_at')
                ->where(function ($query) use ($highlightIds): void {
                    $query->whereIn('highlight_id', $highlightIds)
                        ->orWhereIn('mastery_card_id',
                            MasteryCard::query()
                                ->whereIn('highlight_id', $highlightIds)
                                ->pluck('id')
                        );
                })
                ->delete();

            // Delete the source (cascades to highlights and their cards).
            $source->delete();
        });
    }

    /**
     * Delete a passage (highlight) and all its cards.
     */
    public function deleteHighlight(Highlight $highlight): void
    {
        DB::transaction(function () use ($highlight): void {
            // Collect all card IDs that will be deleted.
            $cardIds = $highlight->masteryCards()->pluck('id')->toArray();

            // Delete unacted review items pointing at this highlight or its cards.
            ReviewItem::query()
                ->whereNull('acted_at')
                ->where(function ($query) use ($highlight, $cardIds): void {
                    $query->where('highlight_id', $highlight->id)
                        ->orWhereIn('mastery_card_id', $cardIds);
                })
                ->delete();

            // Delete the highlight (cascades to its cards).
            $highlight->delete();
        });
    }

    /**
     * Delete a mastery card.
     */
    public function deleteCard(MasteryCard $card): void
    {
        DB::transaction(function () use ($card): void {
            // Delete unacted review items pointing at this card.
            ReviewItem::query()
                ->whereNull('acted_at')
                ->where('mastery_card_id', $card->id)
                ->delete();

            // Delete the card.
            $card->delete();
        });
    }
}

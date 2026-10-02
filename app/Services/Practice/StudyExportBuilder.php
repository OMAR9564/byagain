<?php

declare(strict_types=1);

namespace App\Services\Practice;

use App\Models\Source;

/**
 * Build a study export for an LLM.
 *
 * The export includes all active (non-discarded) passages and their active mastery
 * cards, formatted as Markdown. It follows contracts/study-export.md strictly.
 *
 * @see contracts/study-export.md
 */
final class StudyExportBuilder
{
    public function build(Source $source): string
    {
        $lines = [];

        // Instruction at the top
        $lines[] = __('practice.export.instruction');
        $lines[] = '';

        // Title
        $lines[] = "# {$source->title}";

        // Author (if present)
        if ($source->author !== null) {
            $lines[] = $source->author;
        }

        $lines[] = '';

        // Passages section
        $lines[] = '## Passages';
        $lines[] = '';

        // Get all non-discarded passages, ordered by id ascending
        $passages = $source->highlights()
            ->where('is_discarded', false)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($passages as $index => $passage) {
            $number = $index + 1;
            $lines[] = "### {$number}";
            $lines[] = '';
            $lines[] = $passage->content_md;
            $lines[] = '';

            // Location line (if present)
            if ($passage->location !== null) {
                $lines[] = "_{$passage->location}_";
                $lines[] = '';
            }
        }

        // Questions section (only if there are active cards)
        // Load highlights with their active cards, then flatten and sort
        $highlights = $source->highlights()
            ->where('is_discarded', false)
            ->with(['masteryCards' => function ($query): void {
                $query->where('status', 'active');
            }])
            ->orderBy('id', 'asc')
            ->get();

        $activeCards = $highlights
            ->flatMap(fn ($highlight) => $highlight->masteryCards)
            ->sortBy(['id', 'asc']);

        if ($activeCards->count() > 0) {
            $lines[] = '## Questions';
            $lines[] = '';

            foreach ($activeCards as $index => $card) {
                $number = $index + 1;
                $lines[] = "### Q{$number}";
                $lines[] = '';
                $lines[] = "**Q:** {$card->question}";
                $lines[] = '';
                $lines[] = "**A:** {$card->answer}";
                $lines[] = '';
            }
        }

        $result = implode("\n", $lines);

        // Ensure single trailing newline
        return rtrim($result)."\n";
    }

    /**
     * Count active passages and cards.
     *
     * @return array{passages: int, cards: int}
     */
    public function counts(Source $source): array
    {
        $passages = $source->highlights()
            ->where('is_discarded', false)
            ->count();

        $cards = $source->highlights()
            ->where('is_discarded', false)
            ->with(['masteryCards' => function ($query): void {
                $query->where('status', 'active');
            }])
            ->get()
            ->flatMap(fn ($highlight) => $highlight->masteryCards)
            ->count();

        return [
            'passages' => $passages,
            'cards' => $cards,
        ];
    }
}

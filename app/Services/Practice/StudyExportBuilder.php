<?php

declare(strict_types=1);

namespace App\Services\Practice;

use App\Models\Highlight;
use App\Models\MasteryCard;
use App\Models\Source;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

        // One load serves both sections: passages and their cards share the
        // highlight order (id asc), then card id, as contracts/study-export.md
        // requires. Numbering uses running counters so Q1..Qn never has gaps.
        $highlights = $this->activeHighlights($source)
            ->with(['masteryCards' => function ($query): void {
                $query->where('status', MasteryCard::STATUS_ACTIVE)->orderBy('id');
            }])
            ->orderBy('id')
            ->get();

        foreach ($highlights->values() as $index => $passage) {
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

        $cards = $highlights->flatMap(fn (Highlight $highlight) => $highlight->masteryCards);

        // Questions section (only if there are active cards)
        if ($cards->isNotEmpty()) {
            $lines[] = '## Questions';
            $lines[] = '';

            $number = 0;
            foreach ($cards as $card) {
                $number++;
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
        $passages = $this->activeHighlights($source)->count();

        $cards = MasteryCard::query()
            ->where('status', MasteryCard::STATUS_ACTIVE)
            ->whereIn('highlight_id', $this->activeHighlights($source)->select('id'))
            ->count();

        return [
            'passages' => $passages,
            'cards' => $cards,
        ];
    }

    /**
     * The source's non-discarded highlights.
     *
     * @return HasMany<Highlight, Source>
     */
    private function activeHighlights(Source $source): HasMany
    {
        return $source->highlights()->where('is_discarded', false);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Highlight;
use App\Models\Source;

/**
 * The single write path for a highlight.
 *
 * Controllers never assemble the derived columns themselves. If they did, the
 * day someone adds a second place highlights are created — an importer, a
 * command, a Filament action — is the day one of them forgets to purify, and
 * `content_html` is the one value the app echoes unescaped.
 */
final class HighlightWriter
{
    public function __construct(
        private readonly MarkdownRenderer $renderer,
        private readonly PastedTextCleaner $cleaner,
    ) {}

    /**
     * @param  array{source_id: int, content_md: string, note?: string|null, location?: string|null}  $input
     */
    public function create(array $input): Highlight
    {
        $highlight = new Highlight;
        $highlight->fill([
            'source_id' => $input['source_id'],
            'note' => $input['note'] ?? null,
            'location' => $input['location'] ?? null,
        ]);

        $this->applyContent($highlight, $input['content_md']);
        $highlight->save();

        $this->syncSourceCount($highlight->source_id);

        return $highlight;
    }

    /**
     * @param  array{source_id: int, content_md: string, note?: string|null, location?: string|null}  $input
     */
    public function update(Highlight $highlight, array $input): Highlight
    {
        $previousSourceId = $highlight->source_id;

        $highlight->fill([
            'source_id' => $input['source_id'],
            'note' => $input['note'] ?? null,
            'location' => $input['location'] ?? null,
        ]);

        $this->applyContent($highlight, $input['content_md']);
        $highlight->save();

        if ($previousSourceId !== $highlight->source_id) {
            $this->syncSourceCount($previousSourceId);
        }

        $this->syncSourceCount($highlight->source_id);

        return $highlight;
    }

    /**
     * Hide a highlight from future reviews without deleting it, and without
     * disturbing any review it already appears in (FR-011, FR-013).
     */
    public function discard(Highlight $highlight): Highlight
    {
        $highlight->is_discarded = true;
        $highlight->save();

        $this->syncSourceCount($highlight->source_id);

        return $highlight;
    }

    public function restore(Highlight $highlight): Highlight
    {
        $highlight->is_discarded = false;
        $highlight->save();

        $this->syncSourceCount($highlight->source_id);

        return $highlight;
    }

    public function toggleFavorite(Highlight $highlight): Highlight
    {
        $highlight->is_favorite = ! $highlight->is_favorite;
        $highlight->save();

        return $highlight;
    }

    /**
     * Clean the paste, render it, and derive everything the sampler needs.
     */
    private function applyContent(Highlight $highlight, string $markdown): void
    {
        // Cleaning happens before rendering, not after: the hyphen-wrapped
        // line breaks a PDF produces are markdown-invisible, and rendering
        // first would bake them into the HTML (FR-022).
        $markdown = $this->cleaner->clean($markdown);
        $text = $this->renderer->toText($markdown);

        $highlight->content_md = $markdown;
        $highlight->content_html = $this->renderer->toHtml($markdown);
        $highlight->content_text = $text;
        $highlight->char_count = mb_strlen($text);
        $highlight->contains_code = $this->renderer->containsCode($markdown);
    }

    /**
     * Keep `sources.highlights_count` honest.
     *
     * Denormalised so the equal-source-weighting option can divide by it
     * inside the sampling query rather than in PHP (R-04). Discarded
     * highlights do not count — they cannot be sampled.
     */
    private function syncSourceCount(int $sourceId): void
    {
        $source = Source::query()->find($sourceId);

        if ($source === null) {
            return;
        }

        $source->highlights_count = $source->highlights()
            ->where('is_discarded', false)
            ->count();

        $source->save();
    }
}

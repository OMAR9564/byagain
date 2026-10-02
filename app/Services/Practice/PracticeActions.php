<?php

declare(strict_types=1);

namespace App\Services\Practice;

use App\Models\Highlight;
use App\Models\Source;
use App\Services\Content\HighlightWriter;

/**
 * Apply user decisions from a practice session.
 *
 * Practice actions persist only explicit, persistent decisions — discard, favorite,
 * and source frequency — while never touching the exposure record (shown_count,
 * last_shown_at). This ensures practice does not alter the schedule or skew the
 * algorithm's data (FR-206, FR-208, R-303).
 *
 * Idempotent: calling twice with the same input produces the same state both times,
 * which means the offline queue's replay is safe.
 */
final class PracticeActions
{
    public function __construct(
        private readonly HighlightWriter $writer,
    ) {}

    /**
     * @param  array{action?: string, favorite?: bool|null, source_frequency?: string|null}  $input
     */
    public function apply(Source $source, Highlight $highlight, array $input): void
    {
        $action = $input['action'] ?? null;

        if ($action === 'discard') {
            // Only discard if not already discarded; HighlightWriter::discard
            // also syncs the source count. Do not call it twice on a replay.
            if (! $highlight->is_discarded) {
                $this->writer->discard($highlight);
            }
        }

        // Favorite is idempotent: only setting to true, never unsetting
        // (FR-208). A `favorite: false` payload from the offline queue
        // is ignored — the user saw no button to unfavorite in practice.
        if (($input['favorite'] ?? null) === true) {
            $highlight->is_favorite = true;
            $highlight->save();
        }

        // Source frequency: applies to the source, not the highlight.
        $frequency = $input['source_frequency'] ?? null;

        if ($frequency !== null) {
            $source->frequency = $frequency;
            $source->save();
        }
    }
}

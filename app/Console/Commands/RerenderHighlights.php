<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Highlight;
use App\Services\Content\MarkdownRenderer;
use Illuminate\Console\Command;

/**
 * Rebuilds `content_html` and `content_text` from `content_md`.
 *
 * `content_md` is the source of truth and this command never touches it. The
 * rendered columns are a cache of a pure function, so they can always be
 * regenerated — which is what makes it safe to tighten the purifier whitelist
 * or upgrade CommonMark later without leaving old passages rendered by old
 * rules (FR-092).
 */
final class RerenderHighlights extends Command
{
    protected $signature = 'byagain:rerender-highlights
                            {--user= : Restrict to a single user id}
                            {--dry-run : Report what would change, write nothing}';

    protected $description = 'Regenerate rendered HTML and plain text for highlights from their markdown source.';

    private const int CHUNK = 200;

    public function handle(MarkdownRenderer $renderer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $changed = 0;
        $total = 0;

        Highlight::query()
            ->when($this->option('user'), fn ($q) => $q->where('user_id', $this->option('user')))
            ->orderBy('id')
            // Chunked by id: a full account can hold 20.000 highlights, and
            // hydrating them all would be a memory problem for no benefit.
            ->chunkById(self::CHUNK, function ($highlights) use ($renderer, $dryRun, &$changed, &$total): void {
                foreach ($highlights as $highlight) {
                    $total++;

                    $html = $renderer->toHtml($highlight->content_md);
                    $text = $renderer->toText($highlight->content_md);

                    if ($html === $highlight->content_html && $text === $highlight->content_text) {
                        continue;
                    }

                    $changed++;

                    if ($dryRun) {
                        continue;
                    }

                    $highlight->content_html = $html;
                    $highlight->content_text = $text;
                    $highlight->char_count = mb_strlen($text);
                    $highlight->contains_code = $renderer->containsCode($highlight->content_md);
                    $highlight->save();
                }
            });

        if ($dryRun) {
            $this->comment('Dry run — nothing was written.');
        }

        $this->line("checked={$total} changed={$changed}");

        return self::SUCCESS;
    }
}

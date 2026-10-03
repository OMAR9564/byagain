<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Source;
use App\Models\User;
use App\Services\Content\PassageImportParser;
use App\Services\Content\PassageWithCards;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Import passages with cards from a Markdown file.
 *
 * Handles the "Kart N" format with Açıklama, Hatırla, and Soru/Cevap sections.
 */
final class ImportPassages extends Command
{
    protected $signature = 'byagain:import-passages {file : Markdown file to import}
                            {--user= : Owner, by id or email}
                            {--source= : Source title; created (type note) if the user has none with that title}
                            {--dry-run : Parse and report, write nothing}';

    protected $description = 'Import passages with their cards from a Markdown file.';

    public function handle(PassageImportParser $parser, PassageWithCards $passages): int
    {
        $filePath = $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');

        // Read the file.
        if (! file_exists($filePath)) {
            $this->error("File not found: $filePath");

            return self::FAILURE;
        }

        $markdown = file_get_contents($filePath);

        if ($markdown === false) {
            $this->error("Could not read file: $filePath");

            return self::FAILURE;
        }

        // Parse the markdown.
        $parsed = $parser->parse($markdown);

        // Resolve the user.
        $userArg = $this->option('user');

        if (! $userArg) {
            $this->error('--user is required');

            return self::FAILURE;
        }

        // Try to parse as ID first, then as email.
        if (is_numeric($userArg)) {
            $user = User::query()->find((int) $userArg);
        } else {
            $user = User::query()->where('email', $userArg)->first();
        }

        if ($user === null) {
            $this->error("User not found: $userArg");

            return self::FAILURE;
        }

        // Set the authenticated user so BelongsToUser scope works.
        Auth::setUser($user);

        // Resolve or create the source.
        $sourceTitle = $this->option('source');

        if (! $sourceTitle) {
            $this->error('--source is required');

            return self::FAILURE;
        }

        $source = Source::query()
            ->where('user_id', $user->id)
            ->where('title', $sourceTitle)
            ->first();

        if ($source === null) {
            if ($dryRun) {
                $this->line("Would create source: $sourceTitle");

                // Create a temporary source object for dry-run purposes.
                $source = new Source([
                    'title' => $sourceTitle,
                    'type' => 'note',
                    'frequency' => config('byagain.sampling.default_source_frequency'),
                ]);
                $source->user_id = $user->id;
            } else {
                $source = Source::query()->create([
                    'title' => $sourceTitle,
                    'type' => 'note',
                    'frequency' => config('byagain.sampling.default_source_frequency'),
                ]);
            }
        }

        // Process passages.
        $created = 0;
        $skipped = 0;
        $totalCards = 0;

        foreach ($parsed as $passageData) {
            $contentMd = $passageData['content_md'];
            $cards = $passageData['cards'];

            // Check if this passage already exists in the source.
            $exists = Source::query()->find($source->id)?->highlights()
                ->where('content_md', $contentMd)
                ->exists() ?? false;

            if ($exists) {
                $skipped++;
                $this->line('Skipped duplicate passage.');

                continue;
            }

            $created++;
            $totalCards += count($cards);

            if ($dryRun) {
                $this->line('Would create passage with '.count($cards).' cards.');

                continue;
            }

            // Create the passage with its cards.
            $passages->create([
                'source_id' => $source->id,
                'content_md' => $contentMd,
                'cards' => $cards,
            ]);
        }

        if ($dryRun) {
            $this->comment('Dry run — nothing was written.');
        }

        $this->line("passages_created=$created passages_skipped=$skipped cards_created=$totalCards");

        return self::SUCCESS;
    }
}

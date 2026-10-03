<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Highlight;
use App\Services\Mastery\MasteryCardWriter;
use Illuminate\Support\Facades\DB;

/**
 * Write a passage together with the question cards added beside it.
 *
 * A reader can add cards while writing a passage (FR-044, owner decision
 * 2026-10-02). The passage and its cards are one save, so they share a
 * transaction: a card that fails leaves no half-saved passage behind.
 * HighlightWriter stays the only writer of highlights and MasteryCardWriter
 * the only builder of cards; this class only sequences them.
 */
final class PassageWithCards
{
    public function __construct(
        private readonly HighlightWriter $highlightWriter,
        private readonly MasteryCardWriter $cardWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated passage fields, optionally with `cards`
     * @return array{0: Highlight, 1: int} the passage and how many cards were created
     */
    public function create(array $data): array
    {
        [$passage, $cards] = $this->split($data);

        return DB::transaction(function () use ($passage, $cards): array {
            $highlight = $this->highlightWriter->create($passage);

            return [$highlight, $this->writeCards($highlight, $cards)];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return int how many cards were created
     */
    public function update(Highlight $highlight, array $data): int
    {
        [$passage, $cards] = $this->split($data);

        return DB::transaction(function () use ($highlight, $passage, $cards): int {
            $this->highlightWriter->update($highlight, $passage);

            return $this->writeCards($highlight, $cards);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function split(array $data): array
    {
        $cards = $data['cards'] ?? [];
        unset($data['cards']);

        return [$data, array_values((array) $cards)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     */
    private function writeCards(Highlight $highlight, array $cards): int
    {
        foreach ($cards as $card) {
            $this->cardWriter->create($highlight, $card);
        }

        return count($cards);
    }
}

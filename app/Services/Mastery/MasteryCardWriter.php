<?php

declare(strict_types=1);

namespace App\Services\Mastery;

use App\Models\Highlight;
use App\Models\MasteryCard;

/**
 * Create mastery cards from form input.
 *
 * Centralizes card-building logic so there is exactly one path by which
 * a card is created — from the dedicated form or inline during passage
 * creation (FR-044, FR-208).
 */
final class MasteryCardWriter
{
    /**
     * Build and save a card linked to a highlight.
     *
     * @param  array{type: string, question: string, answer?: string|null}  $data
     */
    public function create(Highlight $highlight, array $data): MasteryCard
    {
        $card = new MasteryCard;
        $card->fill([
            'highlight_id' => $highlight->id,
            'type' => $data['type'],
            'question' => $data['question'],
            'answer' => $this->answerFor($data),
        ]);

        // Left unscheduled on purpose: the first feedback sets the half-life
        // outright, so there is nothing meaningful to guess at now (FR-047).
        $card->save();

        return $card;
    }

    /**
     * A cloze carries its answer inside the question, so it is derived rather
     * than asked for twice — two fields that must agree are two fields that
     * will eventually disagree.
     *
     * @param  array<string, mixed>  $data
     */
    public function answerFor(array $data): string
    {
        if ($data['type'] !== MasteryCard::TYPE_CLOZE) {
            return (string) ($data['answer'] ?? '');
        }

        preg_match_all('/\{\{(.+?)\}\}/s', (string) $data['question'], $matches);

        return implode(', ', $matches[1]);
    }
}

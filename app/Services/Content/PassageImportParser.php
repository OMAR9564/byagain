<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\MasteryCard;

/**
 * Parse passages with cards from Markdown format.
 *
 * Handles the "Kart N" format with Açıklama, Hatırla, and Soru/Cevap sections.
 */
final class PassageImportParser
{
    /**
     * Parse markdown into passages with their cards.
     *
     * @return array<int, array{content_md: string, cards: array<int, array{type: string, question: string, answer: string}>}>
     */
    public function parse(string $markdown): array
    {
        $passages = [];

        $sections = preg_split('/^## /m', $markdown, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($sections as $section) {
            $section = trim($section);

            if ($section === '') {
                continue;
            }

            $lines = explode("\n", $section);

            $rawTitle = array_shift($lines);
            $title = $this->stripTitlePrefix($rawTitle);

            $content = implode("\n", $lines);

            [$contentMd, $cards] = $this->parseContent($title, $content);

            if ($contentMd !== '' || ! empty($cards)) {
                $passages[] = [
                    'content_md' => $contentMd,
                    'cards' => $cards,
                ];
            }
        }

        return $passages;
    }

    /**
     * Remove the "Kart N — " or "Card N — " prefix from the title.
     *
     * Handles em dash (—), en dash (–), and hyphen (-) as separators.
     */
    private function stripTitlePrefix(string $title): string
    {
        $stripped = preg_replace('/^(?:Kart|Card)\s*\d+\s*[—–-]\s*/iu', '', $title);

        return trim($stripped);
    }

    /**
     * Parse the content section to extract Açıklama, Hatırla, and cards.
     *
     * Driven by labels alone: exports put blank lines between blocks
     * inconsistently, so layout must not decide what is kept.
     *
     * @return array{0: string, 1: array<int, array{type: string, question: string, answer: string}>}
     */
    private function parseContent(string $title, string $content): array
    {
        $aciklama = '';
        $hatirla = '';
        $cards = [];
        $question = null;

        foreach (explode("\n", $content) as $rawLine) {
            $line = rtrim($rawLine);

            if (str_starts_with($line, '**Açıklama:**')) {
                $aciklama = $this->extractContent($line, '**Açıklama:**');
            } elseif (str_starts_with($line, '**Hatırla:**')) {
                $hatirla = $this->extractContent($line, '**Hatırla:**');
            } elseif (preg_match('/^\*\*Soru\s+\d+:\*\*/', $line)) {
                $question = $this->extractContent($line, null);
            } elseif ($question !== null && str_starts_with($line, '**Cevap:**')) {
                $cards[] = [
                    'type' => MasteryCard::TYPE_QA,
                    'question' => $question,
                    'answer' => $this->extractContent($line, '**Cevap:**'),
                ];
                $question = null;
            }
        }

        $paragraphs = ["## $title"];

        if ($aciklama !== '') {
            $paragraphs[] = "**Açıklama:** $aciklama";
        }

        if ($hatirla !== '') {
            $paragraphs[] = "**Hatırla:** $hatirla";
        }

        return [implode("\n\n", $paragraphs), $cards];
    }

    /**
     * Extract content from a line by removing the label.
     */
    private function extractContent(string $line, ?string $label): string
    {
        if ($label === null) {
            return (string) preg_replace('/^\*\*Soru\s+\d+:\*\*\s*/', '', $line);
        }

        $pattern = preg_quote($label);

        return trim((string) preg_replace("/$pattern\s*/", '', $line));
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Content;

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

        // Split by h2 sections (## Title)
        $sections = preg_split('/^## /m', $markdown, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($sections as $section) {
            // Trim the section and split into lines.
            $section = trim($section);

            if ($section === '') {
                continue;
            }

            $lines = explode("\n", $section);

            // First line is the title.
            $rawTitle = array_shift($lines);
            $title = $this->stripTitlePrefix($rawTitle);

            // Join the rest back and parse the content.
            $content = implode("\n", $lines);

            // Parse the content to extract Açıklama, Hatırla, and cards.
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
        // Remove the prefix like "Kart 24 — " or "Card 41 — "
        $stripped = preg_replace('/^(?:Kart|Card)\s*\d+\s*[—–-]\s*/iu', '', $title);

        return trim($stripped);
    }

    /**
     * Parse the content section to extract Açıklama, Hatırla, and cards.
     *
     * @return array{0: string, 1: array<int, array{type: string, question: string, answer: string}>}
     */
    private function parseContent(string $title, string $content): array
    {
        // Trim the content and split into lines.
        $lines = array_map(fn ($line) => rtrim($line), explode("\n", trim($content)));

        $aciklama = '';
        $hatirla = '';
        $cards = [];

        $i = 0;
        $lineCount = count($lines);

        // Parse Açıklama section.
        while ($i < $lineCount && $lines[$i] !== '') {
            $line = $lines[$i];

            if (str_starts_with($line, '**Açıklama:**')) {
                $aciklama = $this->extractContent($line, '**Açıklama:**');
                break;
            }

            $i++;
        }

        $i++;

        // Parse Hatırla section if it exists.
        if ($i < $lineCount && str_starts_with($lines[$i], '**Hatırla:**')) {
            $hatirla = $this->extractContent($lines[$i], '**Hatırla:**');
            $i++;
        }

        // Skip empty lines.
        while ($i < $lineCount && $lines[$i] === '') {
            $i++;
        }

        // Parse Soru/Cevap pairs.
        while ($i < $lineCount) {
            $line = $lines[$i];

            // Check if this is a Soru line.
            if (preg_match('/^\*\*Soru\s+\d+:\*\*/', $line)) {
                $question = $this->extractContent($line, null);
                $answer = '';

                // Look for the next Cevap line.
                $i++;
                while ($i < $lineCount && ! str_starts_with($lines[$i], '**Cevap:**')) {
                    $i++;
                }

                if ($i < $lineCount && str_starts_with($lines[$i], '**Cevap:**')) {
                    $answer = $this->extractContent($lines[$i], '**Cevap:**');
                    $i++;
                } else {
                    // Cevap not found, skip this question.
                    continue;
                }

                $cards[] = [
                    'type' => 'qa',
                    'question' => $question,
                    'answer' => $answer,
                ];

                continue;
            }

            $i++;
        }

        // Build the content_md in the expected format.
        $contentMd = "## $title\n\n";
        $contentMd .= "**Açıklama:** $aciklama\n";

        if ($hatirla !== '') {
            $contentMd .= "\n**Hatırla:** $hatirla\n";
        }

        return [trim($contentMd), $cards];
    }

    /**
     * Extract content from a line by removing the label.
     */
    private function extractContent(string $line, ?string $label): string
    {
        if ($label === null) {
            // For Soru lines, extract everything after the label pattern.
            return (string) preg_replace('/^\*\*Soru\s+\d+:\*\*\s*/', '', $line);
        }

        // For labeled lines, extract everything after the label.
        $pattern = preg_quote($label);

        return trim((string) preg_replace("/$pattern\s*/", '', $line));
    }
}

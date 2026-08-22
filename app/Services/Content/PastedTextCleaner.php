<?php

declare(strict_types=1);

namespace App\Services\Content;

/**
 * Repairs text pasted out of a PDF or an e-reader.
 *
 * Those sources wrap at a fixed column and hyphenate across the break, so what
 * arrives in the editor looks like:
 *
 *     The most important thing is not to be de-
 *     ceived by appearances.
 *
 * Left alone it renders as one long line with a stray hyphen in the middle of
 * a word — the single ugliest thing that can happen to a highlight, and the
 * product's whole promise is that the text looks right on the phone (FR-022).
 *
 * The rules are deliberately conservative. Markdown structure is preserved:
 * blank lines still separate paragraphs, and list items, headings, quotes and
 * fenced code blocks are never joined.
 */
final class PastedTextCleaner
{
    /** Lines that begin a markdown block and must keep their own line. */
    private const string BLOCK_START = '/^\s*(?:[-*+]\s|\d+[.)]\s|#{1,6}\s|>|\||```|~~~)/';

    public function clean(string $text): string
    {
        $text = $this->normaliseLineEndings($text);
        $text = $this->collapseBlankRuns($text);

        return trim($this->unwrapLines($text));
    }

    private function normaliseLineEndings(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    /**
     * Three or more blank lines mean nothing markdown does not already say
     * with one.
     */
    private function collapseBlankRuns(string $text): string
    {
        return preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    }

    /**
     * Rejoin lines that a fixed-width source broke mid-sentence.
     */
    private function unwrapLines(string $text): string
    {
        $lines = explode("\n", $text);
        $output = [];
        $inFence = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s*(?:```|~~~)/', $line) === 1) {
                $inFence = ! $inFence;
                $output[] = $line;

                continue;
            }

            // Inside a fenced block every line break is intentional.
            if ($inFence || $output === []) {
                $output[] = $line;

                continue;
            }

            $previousIndex = count($output) - 1;
            $previous = $output[$previousIndex];

            if (! $this->shouldJoin($previous, $line)) {
                $output[] = $line;

                continue;
            }

            // A hyphen at the end of a wrapped line is the wrap, not a real
            // hyphen: "de-\nceived" is one word. A word that is genuinely
            // hyphenated would not have the break there.
            if (preg_match('/(\p{L})-$/u', $previous) === 1) {
                $output[$previousIndex] = mb_substr($previous, 0, -1).ltrim($line);

                continue;
            }

            $output[$previousIndex] = rtrim($previous).' '.ltrim($line);
        }

        return implode("\n", $output);
    }

    /**
     * Whether two consecutive lines are one wrapped sentence.
     */
    private function shouldJoin(string $previous, string $current): bool
    {
        // A blank line is a paragraph break; that is the author speaking.
        if (trim($previous) === '' || trim($current) === '') {
            return false;
        }

        // Never swallow markdown structure into the line above it.
        if (preg_match(self::BLOCK_START, $current) === 1) {
            return false;
        }

        if (preg_match(self::BLOCK_START, $previous) === 1) {
            return false;
        }

        // An indented line is code or a continuation the user chose.
        if (preg_match('/^(?: {4}|\t)/', $current) === 1) {
            return false;
        }

        // A line ending in sentence-final punctuation was probably meant to
        // end there. A hyphen or a lowercase letter was not.
        return preg_match('/[.!?:;"\')\]]$/u', rtrim($previous)) !== 1;
    }
}

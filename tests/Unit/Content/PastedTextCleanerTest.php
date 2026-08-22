<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Services\Content\PastedTextCleaner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PastedTextCleanerTest extends TestCase
{
    private PastedTextCleaner $cleaner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleaner = new PastedTextCleaner;
    }

    #[Test]
    public function a_word_broken_across_a_line_by_a_hyphen_is_rejoined(): void
    {
        $pasted = "The most important thing is not to be de-\nceived by appearances.";

        $this->assertSame(
            'The most important thing is not to be deceived by appearances.',
            $this->cleaner->clean($pasted),
        );
    }

    #[Test]
    public function hard_wrapped_lines_become_one_paragraph(): void
    {
        $pasted = "You have power over your mind, not\noutside events. Realise this, and you\nwill find strength.";

        $this->assertSame(
            'You have power over your mind, not outside events. Realise this, and you will find strength.',
            $this->cleaner->clean($pasted),
        );
    }

    #[Test]
    public function a_blank_line_still_separates_paragraphs(): void
    {
        $pasted = "First thought here\nand its continuation.\n\nSecond thought.";

        $this->assertSame(
            "First thought here and its continuation.\n\nSecond thought.",
            $this->cleaner->clean($pasted),
        );
    }

    #[Test]
    public function runs_of_blank_lines_collapse_to_one(): void
    {
        $pasted = "One.\n\n\n\n\nTwo.";

        $this->assertSame("One.\n\nTwo.", $this->cleaner->clean($pasted));
    }

    #[Test]
    public function list_items_keep_their_own_lines(): void
    {
        $pasted = "Three things:\n- first\n- second\n- third";

        $this->assertSame(
            "Three things:\n- first\n- second\n- third",
            $this->cleaner->clean($pasted),
        );
    }

    #[Test]
    public function headings_and_quotes_are_never_swallowed(): void
    {
        $pasted = "# Chapter one\nThe opening line\n> a quotation";

        $this->assertSame(
            "# Chapter one\nThe opening line\n> a quotation",
            $this->cleaner->clean($pasted),
        );
    }

    #[Test]
    public function line_breaks_inside_a_fenced_block_are_left_alone(): void
    {
        $pasted = "```php\n\$a = 1;\n\$b = 2;\n```";

        $this->assertSame($pasted, $this->cleaner->clean($pasted));
    }

    #[Test]
    public function indented_lines_are_left_alone(): void
    {
        $pasted = "Example:\n    indented code\n    more code";

        $this->assertSame(
            "Example:\n    indented code\n    more code",
            $this->cleaner->clean($pasted),
        );
    }

    #[Test]
    public function windows_and_classic_mac_line_endings_are_normalised(): void
    {
        $this->assertSame('One two.', $this->cleaner->clean("One\r\ntwo."));
        $this->assertSame('One two.', $this->cleaner->clean("One\rtwo."));
    }

    #[Test]
    public function a_genuine_trailing_hyphen_between_paragraphs_is_not_joined(): void
    {
        // A blank line means the author stopped there, hyphen or not.
        $pasted = "A dash of something-\n\nA new thought.";

        $this->assertSame("A dash of something-\n\nA new thought.", $this->cleaner->clean($pasted));
    }

    #[Test]
    public function clean_text_passes_through_untouched(): void
    {
        $text = 'A single tidy sentence.';

        $this->assertSame($text, $this->cleaner->clean($text));
    }
}

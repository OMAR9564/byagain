<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Services\Content\MarkdownRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `content_html` is the one value the app echoes unescaped. Everything below
 * is what earns it that exemption (SC-012).
 */
final class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new MarkdownRenderer;
    }

    #[Test]
    public function a_script_tag_survives_as_visible_text_not_as_markup(): void
    {
        $html = $this->renderer->toHtml('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('alert(1)', $html);
    }

    #[Test]
    public function an_image_with_an_event_handler_never_becomes_an_element(): void
    {
        $html = $this->renderer->toHtml('<img src=x onerror="alert(1)">');

        // The `onerror` text survives, but only as escaped characters inside a
        // paragraph — there is no element for a browser to fire it on.
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    #[Test]
    public function javascript_links_are_dropped(): void
    {
        $html = $this->renderer->toHtml('[click me](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('click me', $html);
    }

    #[Test]
    public function data_uri_links_are_dropped(): void
    {
        $html = $this->renderer->toHtml('[x](data:text/html;base64,PHNjcmlwdD4=)');

        $this->assertStringNotContainsString('data:', $html);
    }

    #[Test]
    public function external_links_carry_the_full_rel_guard(): void
    {
        $html = $this->renderer->toHtml('[Anthropic](https://example.com)');

        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringContainsString('noopener', $html);
        $this->assertStringContainsString('noreferrer', $html);
        $this->assertStringContainsString('nofollow', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }

    #[Test]
    public function code_blocks_are_preserved(): void
    {
        $html = $this->renderer->toHtml("```php\n\$x = 1;\n```");

        $this->assertStringContainsString('<pre>', $html);
        $this->assertStringContainsString('<code', $html);
        $this->assertStringContainsString('$x = 1;', $html);
    }

    #[Test]
    public function gfm_tables_are_preserved(): void
    {
        $markdown = <<<'MD'
            | Term | Meaning |
            | --- | --- |
            | apatheia | freedom from passion |
            MD;

        $html = $this->renderer->toHtml($markdown);

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('apatheia', $html);
    }

    #[Test]
    public function structural_markdown_renders_as_expected(): void
    {
        $html = $this->renderer->toHtml("# Heading\n\n- one\n- two\n\n> quoted\n\n~~gone~~");

        $this->assertStringContainsString('<h1>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('<del>', $html);
    }

    #[Test]
    public function plain_text_rendering_drops_markup_and_decodes_entities(): void
    {
        $text = $this->renderer->toText('**Bold** & *italic* with [a link](https://example.com)');

        $this->assertStringNotContainsString('<', $text);
        $this->assertStringContainsString('&', $text);
        $this->assertStringContainsString('Bold', $text);
        $this->assertStringContainsString('a link', $text);
    }

    #[Test]
    public function code_detection_distinguishes_prose_from_snippets(): void
    {
        $this->assertTrue($this->renderer->containsCode("```\nconst x = 1\n```"));
        $this->assertTrue($this->renderer->containsCode('Call `array_map()` here.'));
        $this->assertFalse($this->renderer->containsCode('Just an ordinary sentence.'));
    }
}

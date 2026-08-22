<?php

declare(strict_types=1);

namespace App\Services\Content;

use HTMLPurifier;
use HTMLPurifier_Config;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Turns what the user typed into what the phone shows.
 *
 * Two passes, and both are load-bearing:
 *
 *   1. CommonMark with `html_input: escape`. Raw HTML in the source is
 *      rendered as visible text rather than markup, so a pasted
 *      `<script>` reads as the characters the user pasted.
 *
 *   2. HTMLPurifier over the result. Belt and braces: even if a future
 *      extension emits an attribute CommonMark considers safe, only the
 *      whitelist below survives.
 *
 * This class is the only writer of `highlights.content_html`, which is in turn
 * the only value the app is allowed to echo unescaped (Constitution art. III).
 * Nothing that skips this path may ever reach a `{!! !!}`.
 */
final class MarkdownRenderer
{
    private ?MarkdownConverter $converter = null;

    private ?HTMLPurifier $purifier = null;

    /**
     * Render markdown to sanitised HTML fit for `{!! !!}`.
     */
    public function toHtml(string $markdown): string
    {
        $rendered = $this->converter()->convert($markdown)->getContent();

        return $this->purifier()->purify($rendered);
    }

    /**
     * A plain-text rendering, used for previews, search and the quality
     * filter's character count.
     */
    public function toText(string $markdown): string
    {
        $text = html_entity_decode(
            strip_tags($this->toHtml($markdown)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        return trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);
    }

    /**
     * Whether the passage contains a code block or inline code.
     *
     * Code is exempt from the short-highlight quality filter: three lines of
     * code is a complete thought where three words of prose is not (FR-031).
     */
    public function containsCode(string $markdown): bool
    {
        return str_contains($this->toHtml($markdown), '<code');
    }

    private function converter(): MarkdownConverter
    {
        if ($this->converter instanceof MarkdownConverter) {
            return $this->converter;
        }

        $environment = new Environment([
            // Raw HTML becomes visible text, never markup.
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'external_link' => [
                'internal_hosts' => config('app.url'),
                'open_in_new_window' => true,
                'html_class' => 'external',
                'nofollow' => 'external',
                'noopener' => 'all',
                'noreferrer' => 'all',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);

        // GFM brings tables and strikethrough, which readers of technical
        // books actually paste (FR-016).
        $environment->addExtension(new GithubFlavoredMarkdownExtension);

        // Applies the `external_link` settings above. Without it those options
        // are inert config and links go out bare.
        $environment->addExtension(new ExternalLinkExtension);

        return $this->converter = new MarkdownConverter($environment);
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier instanceof HTMLPurifier) {
            return $this->purifier;
        }

        $config = HTMLPurifier_Config::createDefault();

        // HTMLPurifier 4.x has no HTML5 doctype. Transitional is chosen over
        // Strict because it is the one that permits `target` on a link, and
        // external links must open away from the review (FR-014).
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.SerializerPath', storage_path('framework/cache/htmlpurifier'));

        // An explicit whitelist rather than a blacklist: anything not named
        // here is stripped, including every event handler attribute.
        $config->set('HTML.Allowed', implode(',', [
            'p', 'br', 'hr',
            'strong', 'em', 'del', 'sup', 'sub',
            'blockquote',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li',
            'pre', 'code[class]',
            'table', 'thead', 'tbody', 'tr', 'th[align]', 'td[align]',
            'a[href|title|rel|target]',
        ]));

        // Only these schemes survive, so `javascript:` and `data:` links are
        // dropped rather than escaped (SC-012).
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);

        $config->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'nofollow']);
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        $this->ensureCacheDirectoryExists($config->get('Cache.SerializerPath'));

        return $this->purifier = new HTMLPurifier($config);
    }

    private function ensureCacheDirectoryExists(mixed $path): void
    {
        if (! is_string($path) || is_dir($path)) {
            return;
        }

        mkdir($path, 0755, true);
    }
}

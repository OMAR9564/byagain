<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The editor's preview.
 *
 * It exists so a writer can see what they will get. That only holds if it goes
 * through the same renderer as the save does — which is why it is a request to
 * the server and not a library in the browser bundle.
 */
final class EditorPreviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_markdown_rather_than_echoing_the_source(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('highlights.preview'), [
                'content_md' => "## Judul\n\nSatu **kata** penting.",
            ])
            ->assertOk()
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, '<h2>')
                && str_contains($html, '<strong>kata</strong>'));
    }

    #[Test]
    public function it_shows_what_would_actually_be_saved(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        $markdown = "| a | b |\n| --- | --- |\n| 1 | 2 |\n\nSonra `kod` ve [bir bağlantı](https://example.com).";

        $preview = (string) $this->actingAs($user)
            ->postJson(route('highlights.preview'), ['content_md' => $markdown])
            ->assertOk()
            ->json('html');

        $this->actingAs($user)->post(route('highlights.store'), [
            'source_id' => $source->id,
            'content_md' => $markdown,
        ])->assertRedirect();

        // Byte for byte. A preview that differs from the stored value is worse
        // than no preview: it tells the writer their table is fine when the
        // saved version will not have one.
        $this->assertSame($preview, $user->highlights()->sole()->content_html);
    }

    #[Test]
    public function the_preview_is_purified_like_everything_else(): void
    {
        $html = (string) $this->actingAs(User::factory()->create())
            ->postJson(route('highlights.preview'), [
                'content_md' => "<script>alert(1)</script>\n\n[tıkla](javascript:alert(2))",
            ])
            ->assertOk()
            ->json('html');

        // The preview writes into the page with innerHTML, so it carries the
        // same trust as the review card and must be held to the same standard
        // (SC-012).
        //
        // Raw HTML survives as visible text — that is CommonMark's `html_input:
        // escape` doing its job, and the point is that it is text and not
        // markup. What must not survive is an attribute.
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    #[Test]
    public function an_empty_passage_previews_as_nothing_rather_than_an_error(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('highlights.preview'), ['content_md' => ''])
            ->assertOk()
            ->assertJsonPath('html', '');
    }

    #[Test]
    public function it_is_closed_to_visitors(): void
    {
        $this->postJson(route('highlights.preview'), ['content_md' => 'merhaba'])
            ->assertUnauthorized();
    }

    #[Test]
    public function the_browser_bundle_still_has_no_markdown_renderer(): void
    {
        /** @var array<string, mixed> $package */
        $package = json_decode(
            (string) file_get_contents(base_path('package.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $dependencies = array_keys(array_merge(
            (array) ($package['dependencies'] ?? []),
            (array) ($package['devDependencies'] ?? []),
        ));

        // The preview has to stay a request. The day a markdown library is
        // added here is the day there are two renderers, and the one without
        // a purifier is the one writing to the page.
        foreach (['marked', 'markdown-it', 'showdown', 'commonmark', 'remark', 'dompurify'] as $renderer) {
            $this->assertNotContains($renderer, $dependencies);
        }

        // And the editor has to be asking the server for it.
        $this->assertStringContainsString(
            'data-preview-url',
            (string) file_get_contents(resource_path('views/components/editor/form.blade.php')),
        );
    }
}

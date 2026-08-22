<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use App\Services\Content\HighlightWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The product's first promise is that text entered here looks right on a
 * phone. These tests cover what can be asserted from PHP; the visual side is
 * checked by hand at 375px against quickstart.md V1 (SC-008).
 */
final class MobileRenderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_long_mixed_passage_survives_the_whole_pipeline_onto_the_page(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create(['title' => 'A Technical Book']);

        $markdown = $this->longMixedPassage();

        $highlight = $this->actingAsWriter($user, $source, $markdown);

        $this->assertGreaterThan(3000, mb_strlen($markdown));

        $response = $this->actingAs($user)->get("/library/sources/{$source->id}");

        $response->assertOk()
            ->assertSee('<h2>', false)
            ->assertSee('<ul>', false)
            ->assertSee('<pre>', false)
            ->assertSee('<table>', false);

        // Past the collapse threshold, so the card offers to expand rather
        // than dumping the whole wall of text (FR-082).
        $this->assertGreaterThan((int) config('byagain.content.collapse_after_chars'), $highlight->char_count);
        $response->assertSee(__('actions.show_more'));
    }

    #[Test]
    public function the_only_unescaped_echo_in_the_codebase_is_content_html(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match_all('/\{!!(.+?)!!\}/s', $contents, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $expression) {
                if (! str_contains($expression, 'content_html')) {
                    $offenders[] = $file.': '.trim($expression);
                }
            }
        }

        // Constitution art. III allows exactly one exception, and it is the
        // purified output of MarkdownRenderer. If this fails, something else
        // is being echoed raw.
        $this->assertSame([], $offenders);
    }

    #[Test]
    public function the_unescaped_echo_carries_its_justifying_comment(): void
    {
        $component = resource_path('views/components/highlight-content.blade.php');
        $contents = (string) file_get_contents($component);

        $this->assertStringContainsString('{{-- purified: MarkdownRenderer --}}', $contents);
    }

    private function actingAsWriter(User $user, Source $source, string $markdown): Highlight
    {
        $this->actingAs($user);

        return app(HighlightWriter::class)->create([
            'source_id' => $source->id,
            'content_md' => $markdown,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function bladeFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views')),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function longMixedPassage(): string
    {
        $prose = str_repeat(
            'The impediment to action advances action; what stands in the way becomes the way. ',
            34,
        );

        return <<<MD
            ## On obstacles

            {$prose}

            - The first thing worth remembering
            - The second thing worth remembering
            - The third thing worth remembering

            > Waste no more time arguing about what a good man should be. Be one.

            ```php
            \$obstacle = new Obstacle();
            \$path = \$obstacle->becomesTheWay();
            ```

            | Term | Meaning |
            | --- | --- |
            | apatheia | freedom from passion |
            | prohairesis | the faculty of choice |

            {$prose}
            MD;
    }
}

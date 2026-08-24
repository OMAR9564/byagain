<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Models\Source;
use App\Models\User;
use App\Services\Content\HighlightWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Wide content gets its own scroll box, and that box owns the horizontal axis.
 *
 * The gesture itself cannot be tested from here — there is no JS test runner in
 * this stack, and adding one is a new dependency rather than a detail (art. V).
 * So the touch is checked by hand on a real phone against quickstart section 2,
 * and what stands guard between those runs is this: the markup the renderer
 * emits, and the two CSS rules that make it scrollable and keep the card off
 * its axis (issue #1, FR-121, FR-124).
 *
 * A cheap gate for a bug that took a long time to find: `touch-action: pan-y`
 * on the card silently disowned horizontal panning for everything inside it, so
 * a 120-character line of code could not be read at all — and trying to read it
 * discarded the passage.
 */
final class HighlightRenderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_code_block_and_a_wide_table_render_inside_the_highlight_body(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();

        $this->actingAs($user);

        app(HighlightWriter::class)->create([
            'source_id' => $source->id,
            'content_md' => $this->wideContent(),
        ]);

        $response = $this->actingAs($user)->get("/library/sources/{$source->id}");

        $response->assertOk()
            ->assertSee('highlight-body', false)
            ->assertSee('<pre>', false)
            ->assertSee('<table>', false);

        // The long line survives intact. Wrapping it would make the scroll box
        // unnecessary and the passage wrong: a broken code line reads as two
        // statements (app.css keeps `white-space: pre` for exactly this).
        $response->assertSee('--exclude-from=/home/deploy/releases/2026-08-24/storage/framework/cache', false);
    }

    #[Test]
    public function the_scrollable_boxes_keep_the_horizontal_axis_for_themselves(): void
    {
        $css = $this->appCss();

        $rule = $this->ruleFor($css, '.highlight-content .highlight-body pre,');

        $this->assertStringContainsString('overflow-x: auto;', $rule);

        // The fix for #1. Without it the card's `pan-y` reaches down the tree
        // and the browser refuses to scroll this box at all.
        $this->assertStringContainsString('touch-action: auto;', $rule);
    }

    #[Test]
    public function the_card_still_owns_the_axis_everywhere_else(): void
    {
        $rule = $this->ruleFor($this->appCss(), '[data-swipe-surface] {');

        // Giving the scroll boxes their axis back must not have been done by
        // taking the gesture's away: an ordinary swipe on the passage is the
        // review screen's primary interaction (FR-123).
        $this->assertStringContainsString('touch-action: pan-y;', $rule);
    }

    #[Test]
    public function overflowing_content_shows_that_it_scrolls(): void
    {
        $rule = $this->ruleFor($this->appCss(), '.highlight-content .highlight-body pre,');

        // Painted with backgrounds rather than measured in JS: an affordance
        // that costs a scroll listener on the most-scrolled screen in the
        // product is not worth having (FR-124).
        $this->assertStringContainsString('background-attachment: local, local, scroll, scroll;', $rule);
    }

    private function appCss(): string
    {
        return (string) file_get_contents(resource_path('css/app.css'));
    }

    /**
     * The declaration block opened by the given selector line.
     */
    private function ruleFor(string $css, string $selector): string
    {
        $start = mb_strpos($css, $selector);

        $this->assertNotFalse($start, "no rule found for `{$selector}`");

        $end = mb_strpos($css, '}', $start);

        $this->assertNotFalse($end, "unterminated rule for `{$selector}`");

        return mb_substr($css, $start, $end - $start);
    }

    private function wideContent(): string
    {
        return <<<'MD'
            A passage with something too wide for a phone in it.

            ```bash
            rsync -avz --delete --exclude-from=/home/deploy/releases/2026-08-24/storage/framework/cache ./public/ deploy@byagain:/var/www/byagain/public/
            ```

            | Setting | Default | What it does |
            | --- | --- | --- |
            | `nudge_delay_minutes` | 60 | how long after the daily email the reminder waits |
            | `max_rounds_per_day` | 10 | where "one more round" stops being answered |
            MD;
    }
}

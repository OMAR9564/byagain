<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The parts of accessibility that can be checked from PHP.
 *
 * Contrast ratios, focus visibility and touch target sizes are decided in
 * tokens.css and verified by eye at 375px against quickstart V7; what is
 * asserted here is the structural work that regresses silently.
 */
final class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_page_declares_a_language_and_a_viewport(): void
    {
        $user = $this->reader();
        $practice = '/library/sources/'.$user->sources()->firstOrFail()->id.'/practice';

        foreach (['/', '/library', '/settings', '/streak', '/mastery', $practice] as $path) {
            $html = (string) $this->actingAs($user)->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('<html lang="en"', $html, "{$path} has no language");
            $this->assertStringContainsString('name="viewport"', $html, "{$path} has no viewport");
        }
    }

    #[Test]
    public function pages_have_exactly_one_first_level_heading(): void
    {
        $user = $this->reader();
        $practice = '/library/sources/'.$user->sources()->firstOrFail()->id.'/practice';

        foreach (['/', '/library', '/settings', '/streak', $practice] as $path) {
            $html = (string) $this->actingAs($user)->get($path)->assertOk()->getContent();

            $this->assertSame(
                1,
                substr_count($html, '<h1'),
                "{$path} should have exactly one h1",
            );
        }
    }

    #[Test]
    public function the_focus_ring_is_never_removed(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        // The only way a keyboard user knows where they are.
        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringNotContainsString('outline: none', $css);
        $this->assertStringNotContainsString('outline:none', $css);
    }

    #[Test]
    public function form_controls_are_large_enough_not_to_zoom_ios(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        // Under 16px, iOS Safari zooms on focus and yanks the layout sideways
        // mid-typing (FR-080).
        $this->assertStringContainsString('font-size: max(1rem', $css);
    }

    #[Test]
    public function motion_is_reduced_when_the_system_asks(): void
    {
        $tokens = (string) file_get_contents(resource_path('css/tokens.css'));

        $this->assertStringContainsString('prefers-reduced-motion', $tokens);
    }

    #[Test]
    public function the_dark_theme_answers_both_the_system_and_the_override(): void
    {
        $tokens = (string) file_get_contents(resource_path('css/tokens.css'));

        $this->assertStringContainsString('prefers-color-scheme: dark', $tokens);

        // The explicit choice has to beat the system in both directions.
        $this->assertStringContainsString("data-theme='dark'", $tokens);
        $this->assertStringContainsString("data-theme='light'", $tokens);
    }

    #[Test]
    public function icon_only_controls_carry_an_accessible_name(): void
    {
        $user = $this->reader();

        $html = (string) $this->actingAs($user)->get('/')->getContent();

        // The bottom nav is icon-plus-label; the label is the accessible name,
        // and the icon itself must be hidden from assistive technology.
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('aria-label', $html);
    }

    #[Test]
    public function the_progress_bar_reports_its_numbers_even_though_it_hides_them(): void
    {
        $user = $this->reader();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count(5)->create();

        $html = (string) $this->actingAs($user)->get('/review')->assertOk()->getContent();

        // Visually it is a bar, not a counter — but "card 3 of 8" is exactly
        // what a screen reader needs (FR-043).
        $this->assertStringContainsString('role="progressbar"', $html);
        $this->assertStringContainsString('aria-valuenow', $html);
        $this->assertStringContainsString('aria-valuemax', $html);
    }

    private function reader(): User
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count(3)->create();

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The chrome around every signed-in screen.
 */
final class NavigationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_tab_label_is_a_single_word(): void
    {
        /** @var array<string, string> $labels */
        $labels = trans('nav');

        foreach ($labels as $key => $label) {
            if ($key === 'label') {
                continue;
            }

            // Five tabs across a 375px screen leave about 70px each. A label
            // of two words wraps, which pushes its icon up and leaves one tab
            // visibly taller than its neighbours (FR-078).
            $this->assertStringNotContainsString(' ', trim($label), "nav.{$key} would wrap");
        }
    }

    #[Test]
    public function the_bottom_bar_names_every_destination(): void
    {
        $html = (string) $this->actingAs(User::factory()->create())->get('/')->getContent();

        foreach (['review', 'library', 'add', 'mastery', 'streak'] as $key) {
            $this->assertStringContainsString(__("nav.{$key}"), $html);
        }
    }

    #[Test]
    public function settings_are_reachable_from_the_screen(): void
    {
        $html = (string) $this->actingAs(User::factory()->create())->get('/')->getContent();

        // There was no link to settings anywhere in the app before the
        // masthead: every preference the product has was reachable only by
        // typing the URL.
        $this->assertStringContainsString(route('settings.edit'), $html);
    }

    #[Test]
    public function the_reader_is_greeted_by_name(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Ada');
    }

    #[Test]
    public function one_account_is_greeted_differently(): void
    {
        $ada = User::factory()->create(['name' => 'Ada']);
        $mila = User::factory()->create(['name' => 'Mila']);

        /** @var array<int, string> $lines */
        $lines = config('byagain.endearment.lines');

        $plain = (string) $this->actingAs($ada)->get('/')->getContent();
        $noted = (string) $this->actingAs($mila)->get('/')->getContent();

        $this->assertSame(0, $this->countLines($plain, $lines));
        $this->assertSame(1, $this->countLines($noted, $lines));
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function countLines(string $html, array $lines): int
    {
        $found = 0;

        foreach ($lines as $line) {
            $found += substr_count($html, e($line)) > 0 ? 1 : 0;
        }

        return $found;
    }
}

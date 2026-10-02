<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Highlight;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SC-018: a user-facing page must never load a Filament, Livewire or Alpine
 * asset.
 *
 * This is a budget question as much as an architectural one. Livewire and
 * Alpine together are larger than the entire 150KB first-load allowance, and
 * the moment one user-facing layout picks them up the mobile experience the
 * product exists for is gone — silently, and without any test failing unless
 * one looks for it.
 */
final class AssetIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function no_user_facing_page_references_an_admin_asset(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        Highlight::factory()->for($user)->for($source)->count(3)->create();

        $paths = ['/', '/library', "/library/sources/{$source->id}", "/library/sources/{$source->id}/practice", '/add', '/mastery', '/streak', '/settings', '/review'];

        foreach ($paths as $path) {
            $html = $this->actingAs($user)->get($path)->assertOk()->getContent();

            foreach (['livewire', 'alpine', 'filament'] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    strtolower((string) $html),
                    "{$path} references a {$forbidden} asset",
                );
            }
        }
    }

    #[Test]
    public function the_signed_out_pages_are_equally_clean(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $html = strtolower((string) $this->get($path)->assertOk()->getContent());

            foreach (['livewire', 'alpine', 'filament'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $html, "{$path} references a {$forbidden} asset");
            }
        }
    }

    #[Test]
    public function the_ownership_scope_is_only_bypassed_inside_the_admin_panel(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn(app_path()) as $file) {
            if (str_contains($file, DIRECTORY_SEPARATOR.'Filament'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            // `->withoutGlobalScope`, so that documentation naming the rule
            // does not read as a violation of it.
            if (preg_match('/->\s*withoutGlobalScopes?\s*\(/', $contents) === 1) {
                $offenders[] = $file;
            }
        }

        // Constitution art. III names app/Filament/ as the only place allowed
        // to read across accounts.
        $this->assertSame([], $offenders);
    }

    /**
     * @return array<int, string>
     */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}

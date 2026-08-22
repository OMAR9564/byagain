<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SC-006: the user-facing first load stays under 150KB, fonts excluded.
 *
 * The budget is the whole reason this app is server-rendered Blade with two
 * small vanilla modules. It is easy to lose by accident — one convenient
 * dependency, one framework pulled in for a single widget — and nothing else
 * in the suite would notice.
 *
 * Fonts are excluded from the rule and byagain has none: the system stack
 * costs nothing and never blocks first paint.
 */
#[Group('budget')]
final class AssetBudgetTest extends TestCase
{
    private const int BUDGET_BYTES = 150 * 1024;

    #[Test]
    public function the_user_facing_first_load_is_within_budget(): void
    {
        $manifest = $this->manifest();

        $entries = ['resources/css/app.css', 'resources/js/app.js'];
        $files = [];

        foreach ($entries as $entry) {
            $this->assertArrayHasKey($entry, $manifest, "{$entry} is missing from the build. Run `npm run build`.");

            $files[] = $manifest[$entry]['file'];

            foreach ($manifest[$entry]['css'] ?? [] as $css) {
                $files[] = $css;
            }
        }

        $total = 0;

        foreach (array_unique($files) as $file) {
            $path = public_path('build/'.$file);
            $this->assertFileExists($path);
            $total += (int) filesize($path);
        }

        $this->assertLessThan(
            self::BUDGET_BYTES,
            $total,
            sprintf('First load is %.1fKB against a %dKB budget (SC-006).', $total / 1024, self::BUDGET_BYTES / 1024),
        );
    }

    #[Test]
    public function no_webfont_is_requested(): void
    {
        $css = (string) file_get_contents($this->pathFor('resources/css/app.css'));

        // The system font stack is what makes the budget reachable and stops
        // text reflowing after first paint.
        $this->assertStringNotContainsString('@font-face', $css);
        $this->assertStringNotContainsString('fonts.googleapis', $css);
        $this->assertStringNotContainsString('fonts.bunny', $css);
    }

    #[Test]
    public function the_admin_bundle_is_built_separately(): void
    {
        $manifest = $this->manifest();

        // A single shared bundle would put Filament's CSS on every reading
        // screen (SC-018).
        $this->assertArrayHasKey('resources/css/admin.css', $manifest);
        $this->assertNotSame(
            $manifest['resources/css/admin.css']['file'],
            $manifest['resources/css/app.css']['file'],
        );
    }

    #[Test]
    public function the_build_output_stays_in_the_repository(): void
    {
        $ignore = (string) file_get_contents(base_path('.gitignore'));

        // Committing build output is unusual, and someone tidying up will
        // eventually want to ignore it again. They must not: production is
        // shared hosting with no node, so it cannot build, and an ignored
        // bundle means uploading it by hand — which is what made the
        // stylesheet 404 twice. See docs/DEPLOYMENT.md section 6.
        $lines = array_map(trim(...), explode("\n", $ignore));

        $this->assertNotContains('/public/build', $lines);
        $this->assertNotContains('public/build', $lines);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function manifest(): array
    {
        $path = public_path('build/manifest.json');

        if (! file_exists($path)) {
            $this->markTestSkipped('No Vite build present. Run `npm run build`.');
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function pathFor(string $entry): string
    {
        return public_path('build/'.$this->manifest()[$entry]['file']);
    }
}

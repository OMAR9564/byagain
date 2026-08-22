<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The app has to be addable to a home screen and open without a connection
 * (FR-086, FR-087).
 */
final class PwaTest extends TestCase
{
    #[Test]
    public function the_manifest_is_served_and_complete(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(public_path('manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame('byagain', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);

        // Straight into the ritual, not a landing page.
        $this->assertSame('/review', $manifest['start_url']);

        $this->assertNotEmpty($manifest['icons']);
    }

    #[Test]
    public function every_icon_the_manifest_names_actually_exists(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(public_path('manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($manifest['icons'] as $icon) {
            // A manifest pointing at a 404 is worse than no manifest: the
            // install prompt simply never appears, silently.
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    #[Test]
    public function a_maskable_icon_is_provided(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(public_path('manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $purposes = array_column($manifest['icons'], 'purpose');

        // Without one, Android crops the square and clips the glyph.
        $this->assertContains('maskable', $purposes);
    }

    #[Test]
    public function every_icon_the_pages_reference_exists(): void
    {
        foreach ([
            'icons/icon.svg',
            'icons/favicon-32.png',
            'icons/favicon-16.png',
            'icons/apple-touch-icon.png',
        ] as $icon) {
            $this->assertFileExists(public_path($icon));
        }
    }

    #[Test]
    public function the_svg_mark_and_the_rasters_agree_on_the_brand_colour(): void
    {
        $svg = (string) file_get_contents(public_path('icons/icon.svg'));

        // The SVG is the source of truth; scripts/make-icons.php mirrors its
        // geometry by hand, so a colour change there has to be carried over.
        $this->assertStringContainsString('#8a5a2b', $svg);

        $png = imagecreatefrompng(public_path('icons/icon-512.png'));
        $corner = imagecolorat($png, 4, 4);

        $this->assertSame(0x8A, ($corner >> 16) & 0xFF);
        $this->assertSame(0x5A, ($corner >> 8) & 0xFF);
        $this->assertSame(0x2B, $corner & 0xFF);

        imagedestroy($png);
    }

    #[Test]
    public function the_offline_page_stands_alone(): void
    {
        $html = (string) file_get_contents(public_path('offline.html'));

        // It has to render when nothing else can be fetched, so it may not
        // reference the build output or any other asset.
        $this->assertStringNotContainsString('/build/', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
        $this->assertStringNotContainsString('{{', $html, 'Blade syntax would be served literally here');
    }

    #[Test]
    public function the_service_worker_never_caches_a_review(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));

        // A review is a thing about today. Serving yesterday's from a cache
        // would be worse than an error, because it would look right.
        $this->assertStringContainsString('networkFirst', $sw);
        $this->assertStringContainsString("request.method !== 'GET'", $sw);
    }

    #[Test]
    public function the_layout_links_the_manifest(): void
    {
        $layout = (string) file_get_contents(
            resource_path('views/components/layouts/app.blade.php'),
        );

        $this->assertStringContainsString('rel="manifest"', $layout);
    }
}

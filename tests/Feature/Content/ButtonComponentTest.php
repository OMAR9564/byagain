<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ButtonComponentTest extends TestCase
{
    #[Test]
    public function button_merges_caller_style_with_variant_colors(): void
    {
        // When a caller passes a style attribute, it should merge with the variant
        // styles rather than creating two style attributes (which browsers ignore).
        $html = (string) $this->blade(
            '<x-button style="min-height: 1px;">Test</x-button>'
        );

        // Assert exactly one style= attribute exists
        preg_match('/style="([^"]*)"/', $html, $matches);
        self::assertNotEmpty($matches, 'No style attribute found');

        $styleValue = $matches[1];

        // Count occurrences of "style=" — should be exactly 1
        $styleCount = substr_count($html, 'style="');
        self::assertEquals(1, $styleCount, 'Expected exactly one style= attribute');

        // The variant colors should be present
        self::assertStringContainsString('var(--color-accent)', $styleValue);
        self::assertStringContainsString('min-height: 1px;', $styleValue);
    }
}

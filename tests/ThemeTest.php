<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests;

use Noeka\Svgraph\Theme;
use PHPUnit\Framework\TestCase;

final class ThemeTest extends TestCase
{
    public function test_color_at_returns_indexed_palette_color(): void
    {
        $theme = Theme::default();
        self::assertSame('#3b82f6', $theme->colorAt(0));
        self::assertSame('#10b981', $theme->colorAt(1));
    }

    public function test_color_at_wraps_around_palette(): void
    {
        $theme = Theme::default(); // 8-color palette
        self::assertSame($theme->colorAt(0), $theme->colorAt(8));
        self::assertSame($theme->colorAt(3), $theme->colorAt(11));
    }

    public function test_color_at_empty_palette_falls_back_to_stroke(): void
    {
        $theme = Theme::default()->withPalette();
        self::assertSame($theme->stroke, $theme->colorAt(0));
    }

    public function test_with_palette_replaces_colors_and_preserves_other_properties(): void
    {
        $original = Theme::default();
        $themed = $original->withPalette('#aabbcc', '#ddeeff');
        self::assertSame(['#aabbcc', '#ddeeff'], $themed->palette);
        self::assertSame($original->stroke, $themed->stroke);
        self::assertSame($original->strokeWidth, $themed->strokeWidth);
        self::assertSame($original->textColor, $themed->textColor);
    }
}

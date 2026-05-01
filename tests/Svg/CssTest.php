<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Svg;

use Noeka\Svgraph\Svg\Css;
use PHPUnit\Framework\TestCase;

final class CssTest extends TestCase
{
    public function test_color_accepts_hex_short_and_long(): void
    {
        self::assertSame('#abc', Css::color('#abc'));
        self::assertSame('#aabbcc', Css::color('#aabbcc'));
        self::assertSame('#aabbccdd', Css::color('#aabbccdd'));
    }

    public function test_color_accepts_rgb_and_rgba(): void
    {
        self::assertSame('rgb(10, 20, 30)', Css::color('rgb(10, 20, 30)'));
        self::assertSame('rgba(10, 20, 30, 0.5)', Css::color('rgba(10, 20, 30, 0.5)'));
    }

    public function test_color_accepts_hsl_and_hsla(): void
    {
        self::assertSame('hsl(120, 50%, 40%)', Css::color('hsl(120, 50%, 40%)'));
        self::assertSame('hsla(120, 50%, 40%, 0.8)', Css::color('hsla(120, 50%, 40%, 0.8)'));
    }

    public function test_color_accepts_named_keyword(): void
    {
        self::assertSame('currentColor', Css::color('currentColor'));
        self::assertSame('red', Css::color('red'));
    }

    public function test_color_rejects_injection_attempts(): void
    {
        self::assertNull(Css::color('red;background:url(http://evil.example/x)'));
        self::assertNull(Css::color('url(http://evil.example/x)'));
        self::assertNull(Css::color('expression(alert(1))'));
        self::assertNull(Css::color('#xyz'));
        self::assertNull(Css::color(''));
    }

    public function test_color_returns_null_for_null_input(): void
    {
        self::assertNull(Css::color(null));
    }

    public function test_length_accepts_units(): void
    {
        self::assertSame('12px', Css::length('12px'));
        self::assertSame('0.75rem', Css::length('0.75rem'));
        self::assertSame('100%', Css::length('100%'));
        self::assertSame('1.5em', Css::length('1.5em'));
    }

    public function test_length_accepts_unitless(): void
    {
        self::assertSame('0', Css::length('0'));
        self::assertSame('1.25', Css::length('1.25'));
    }

    public function test_length_rejects_invalid(): void
    {
        self::assertNull(Css::length('12px;color:red'));
        self::assertNull(Css::length('calc(100% - 10px)'));
        self::assertNull(Css::length('-12px'));
        self::assertNull(Css::length(''));
        self::assertNull(Css::length(null));
    }

    public function test_font_family_accepts_safe_values(): void
    {
        self::assertSame('inherit', Css::fontFamily('inherit'));
        self::assertSame('Arial, sans-serif', Css::fontFamily('Arial, sans-serif'));
        self::assertSame('"Helvetica Neue", Helvetica', Css::fontFamily('"Helvetica Neue", Helvetica'));
    }

    public function test_font_family_rejects_dangerous_chars(): void
    {
        self::assertNull(Css::fontFamily('Arial;background:red'));
        self::assertNull(Css::fontFamily('Arial</style>'));
        self::assertNull(Css::fontFamily(''));
        self::assertNull(Css::fontFamily(null));
    }
}

<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Geometry;

use Noeka\Svgraph\Geometry\Viewport;
use PHPUnit\Framework\TestCase;

final class ViewportTest extends TestCase
{
    public function test_default_plot_area_equals_full_dimensions(): void
    {
        $vp = new Viewport(100.0, 50.0);
        self::assertSame(0.0, $vp->plotLeft());
        self::assertSame(100.0, $vp->plotRight());
        self::assertSame(0.0, $vp->plotTop());
        self::assertSame(50.0, $vp->plotBottom());
        self::assertSame(100.0, $vp->plotWidth());
        self::assertSame(50.0, $vp->plotHeight());
    }

    public function test_padding_shrinks_plot_area(): void
    {
        $vp = new Viewport(100.0, 100.0, 10.0, 5.0, 15.0, 8.0);
        self::assertSame(8.0, $vp->plotLeft());
        self::assertSame(95.0, $vp->plotRight());
        self::assertSame(10.0, $vp->plotTop());
        self::assertSame(85.0, $vp->plotBottom());
        self::assertSame(87.0, $vp->plotWidth());
        self::assertSame(75.0, $vp->plotHeight());
    }

    public function test_plot_dimensions_clamp_to_zero_when_padding_exceeds_size(): void
    {
        $vp = new Viewport(10.0, 10.0, 0.0, 0.0, 0.0, 20.0);
        self::assertSame(0.0, $vp->plotWidth());
    }

    public function test_with_padding_returns_new_immutable_instance(): void
    {
        $original = new Viewport(100.0, 100.0);
        $padded = $original->withPadding(10.0, 5.0, 10.0, 5.0);

        self::assertNotSame($original, $padded);
        self::assertSame(0.0, $original->paddingTop);
        self::assertSame(10.0, $padded->paddingTop);
        self::assertSame(5.0, $padded->paddingLeft);
    }

    public function test_view_box_default(): void
    {
        self::assertSame('0 0 100 100', (new Viewport())->viewBox());
    }

    public function test_view_box_strips_trailing_zeros(): void
    {
        self::assertSame('0 0 200 75.5', (new Viewport(200.0, 75.5))->viewBox());
    }
}

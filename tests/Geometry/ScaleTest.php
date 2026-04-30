<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Geometry;

use Noeka\Svgraph\Geometry\Scale;
use PHPUnit\Framework\TestCase;

final class ScaleTest extends TestCase
{
    public function test_maps_domain_to_range(): void
    {
        $scale = Scale::linear(0, 100, 0, 50);
        self::assertSame(0.0, $scale->map(0));
        self::assertSame(25.0, $scale->map(50));
        self::assertSame(50.0, $scale->map(100));
    }

    public function test_inverted_scale_for_y_axis(): void
    {
        $scale = Scale::linear(0, 100, 0, 50, invert: true);
        self::assertSame(50.0, $scale->map(0));
        self::assertSame(0.0, $scale->map(100));
    }

    public function test_zero_domain_does_not_divide_by_zero(): void
    {
        $scale = Scale::linear(5, 5, 0, 100);
        self::assertSame(0.0, $scale->map(5));
    }

    public function test_ticks_returns_nice_values(): void
    {
        $scale = Scale::linear(0, 100, 0, 100);
        $ticks = $scale->ticks(5);
        self::assertNotEmpty($ticks);
        self::assertSame(0.0, $ticks[0]);
        self::assertSame(100.0, end($ticks));
    }
}

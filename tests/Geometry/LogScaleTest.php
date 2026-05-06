<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Geometry;

use InvalidArgumentException;
use Noeka\Svgraph\Geometry\LogScale;
use Noeka\Svgraph\Geometry\Scale;
use PHPUnit\Framework\TestCase;

final class LogScaleTest extends TestCase
{
    public function test_extends_scale(): void
    {
        $scale = LogScale::log(1.0, 1000.0, 0.0, 100.0);
        self::assertInstanceOf(Scale::class, $scale);
    }

    public function test_maps_powers_of_base_evenly_across_range(): void
    {
        $scale = LogScale::log(1.0, 1000.0, 0.0, 100.0);
        // log10(1) = 0, log10(1000) = 3 → each decade takes 1/3 of the range.
        self::assertSame(0.0, $scale->map(1.0));
        self::assertEqualsWithDelta(33.3333, $scale->map(10.0), 0.001);
        self::assertEqualsWithDelta(66.6666, $scale->map(100.0), 0.001);
        self::assertSame(100.0, $scale->map(1000.0));
    }

    public function test_inverted_scale_for_y_axis(): void
    {
        $scale = LogScale::log(1.0, 100.0, 0.0, 50.0, invert: true);
        self::assertSame(50.0, $scale->map(1.0));
        self::assertSame(0.0, $scale->map(100.0));
    }

    public function test_default_base_is_ten(): void
    {
        $scale = LogScale::log(1.0, 100.0, 0.0, 100.0);
        self::assertSame(10.0, $scale->base);
    }

    public function test_custom_base_two(): void
    {
        $scale = LogScale::log(1.0, 8.0, 0.0, 100.0, base: 2.0);
        // log2(1)=0, log2(2)=1, log2(4)=2, log2(8)=3 → each octave 1/3 of range.
        self::assertEqualsWithDelta(33.3333, $scale->map(2.0), 0.001);
        self::assertEqualsWithDelta(66.6666, $scale->map(4.0), 0.001);
    }

    public function test_ticks_returns_powers_of_base(): void
    {
        $scale = LogScale::log(1.0, 1000.0, 0.0, 100.0);
        self::assertSame([1.0, 10.0, 100.0, 1000.0], $scale->ticks());
    }

    public function test_ticks_with_base_two(): void
    {
        $scale = LogScale::log(1.0, 16.0, 0.0, 100.0, base: 2.0);
        self::assertSame([1.0, 2.0, 4.0, 8.0, 16.0], $scale->ticks());
    }

    public function test_ticks_skips_powers_outside_domain(): void
    {
        // 5..500 → only 10 and 100 are powers of 10 inside this domain.
        $scale = LogScale::log(5.0, 500.0, 0.0, 100.0);
        self::assertSame([10.0, 100.0], $scale->ticks());
    }

    public function test_constructor_rejects_non_positive_min(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('positive');
        new LogScale(0.0, 100.0, 0.0, 100.0);
    }

    public function test_constructor_rejects_negative_min(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LogScale(-1.0, 100.0, 0.0, 100.0);
    }

    public function test_constructor_rejects_non_positive_max(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LogScale(1.0, 0.0, 0.0, 100.0);
    }

    public function test_constructor_rejects_base_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('base');
        new LogScale(1.0, 100.0, 0.0, 100.0, base: 1.0);
    }

    public function test_constructor_rejects_base_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LogScale(1.0, 100.0, 0.0, 100.0, base: 0.5);
    }

    public function test_map_rejects_non_positive_value(): void
    {
        $scale = LogScale::log(1.0, 100.0, 0.0, 100.0);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-positive');
        $scale->map(0.0);
    }

    public function test_map_rejects_negative_value(): void
    {
        $scale = LogScale::log(1.0, 100.0, 0.0, 100.0);
        $this->expectException(InvalidArgumentException::class);
        $scale->map(-5.0);
    }

    public function test_log_factory_widens_degenerate_domain(): void
    {
        // domainMin == domainMax should not divide-by-zero in map().
        $scale = LogScale::log(10.0, 10.0, 0.0, 100.0);
        // Widened to [10, 100], so map(10)=0, map(100)=100.
        self::assertSame(0.0, $scale->map(10.0));
        self::assertSame(100.0, $scale->map(100.0));
    }
}

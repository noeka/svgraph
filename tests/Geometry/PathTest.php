<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Geometry;

use Noeka\Svgraph\Geometry\Path;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    public function test_line_empty_returns_empty_string(): void
    {
        self::assertSame('', Path::line([]));
    }

    public function test_line_single_point(): void
    {
        self::assertSame('M0,0', Path::line([[0.0, 0.0]]));
    }

    public function test_line_multiple_points(): void
    {
        self::assertSame('M0,0 L10,5 L20,10', Path::line([[0.0, 0.0], [10.0, 5.0], [20.0, 10.0]]));
    }

    public function test_smooth_line_empty_returns_empty_string(): void
    {
        self::assertSame('', Path::smoothLine([]));
    }

    public function test_smooth_line_delegates_to_line_for_fewer_than_three_points(): void
    {
        self::assertSame('M0,0 L10,5', Path::smoothLine([[0.0, 0.0], [10.0, 5.0]]));
    }

    public function test_smooth_line_produces_cubic_bezier_segments(): void
    {
        $d = Path::smoothLine([[0.0, 50.0], [50.0, 20.0], [100.0, 50.0]]);
        self::assertStringStartsWith('M0,50', $d);
        self::assertStringContainsString('C', $d);
    }

    public function test_smooth_line_ends_at_last_point(): void
    {
        // Verifies the final C segment terminates exactly at the last data point.
        $d = Path::smoothLine([[0.0, 10.0], [50.0, 0.0], [100.0, 10.0]]);
        self::assertStringEndsWith('100,10', $d);
    }

    public function test_area_empty_returns_empty_string(): void
    {
        self::assertSame('', Path::area([], 100.0));
    }

    public function test_area_closes_path_with_baseline(): void
    {
        $d = Path::area([[0.0, 20.0], [100.0, 40.0]], 100.0);
        self::assertStringContainsString('L100,100', $d);
        self::assertStringContainsString('L0,100', $d);
        self::assertStringEndsWith('Z', $d);
    }

    public function test_arc_pie_wedge_starts_at_center(): void
    {
        $d = Path::arc(50.0, 50.0, 40.0, 0.0, 0.0, M_PI / 2);
        self::assertStringStartsWith('M50,50', $d);
        self::assertStringContainsString('A40,40', $d);
        self::assertStringEndsWith('Z', $d);
    }

    public function test_arc_donut_ring_omits_center(): void
    {
        $d = Path::arc(50.0, 50.0, 40.0, 20.0, 0.0, M_PI / 2);
        self::assertStringNotContainsString('M50,50', $d);
        // One outer arc + one inner arc
        self::assertSame(2, substr_count($d, 'A40,40') + substr_count($d, 'A20,20'));
    }

    public function test_arc_full_circle_does_not_produce_degenerate_empty_path(): void
    {
        // start == end arc renders as nothing in browsers; fullRing uses two semicircles.
        $d = Path::arc(50.0, 50.0, 40.0, 0.0, 0.0, 2 * M_PI);
        self::assertNotSame('', $d);
        self::assertGreaterThan(1, substr_count($d, 'A'));
    }

    public function test_arc_full_donut_ring_includes_inner_circle(): void
    {
        $d = Path::arc(50.0, 50.0, 40.0, 20.0, 0.0, 2 * M_PI);
        // The inner circle uses the inner radius, the outer uses the outer radius.
        self::assertStringContainsString('A40,40', $d);
        self::assertStringContainsString('A20,20', $d);
    }

    public function test_arc_large_arc_flag_set_for_sweep_over_pi(): void
    {
        $d = Path::arc(50.0, 50.0, 40.0, 0.0, 0.0, M_PI * 1.5);
        self::assertStringContainsString('0 1 1 ', $d);
    }

    public function test_polar_twelve_oclock(): void
    {
        [$x, $y] = Path::polar(50.0, 50.0, 10.0, 0.0);
        self::assertEqualsWithDelta(50.0, $x, 1e-10);
        self::assertEqualsWithDelta(40.0, $y, 1e-10);
    }

    public function test_polar_three_oclock(): void
    {
        [$x, $y] = Path::polar(50.0, 50.0, 10.0, M_PI / 2);
        self::assertEqualsWithDelta(60.0, $x, 1e-10);
        self::assertEqualsWithDelta(50.0, $y, 1e-10);
    }

    public function test_polar_six_oclock(): void
    {
        [$x, $y] = Path::polar(50.0, 50.0, 10.0, M_PI);
        self::assertEqualsWithDelta(50.0, $x, 1e-10);
        self::assertEqualsWithDelta(60.0, $y, 1e-10);
    }

    public function test_polar_nine_oclock(): void
    {
        [$x, $y] = Path::polar(50.0, 50.0, 10.0, 3 * M_PI / 2);
        self::assertEqualsWithDelta(40.0, $x, 1e-10);
        self::assertEqualsWithDelta(50.0, $y, 1e-10);
    }

    public function test_band_empty_returns_empty_string(): void
    {
        self::assertSame('', Path::band([], []));
        self::assertSame('', Path::band([[0.0, 0.0]], []));
    }

    public function test_band_joins_forward_lows_with_reversed_highs(): void
    {
        $lows = [[0.0, 80.0], [50.0, 70.0], [100.0, 60.0]];
        $highs = [[0.0, 20.0], [50.0, 30.0], [100.0, 40.0]];
        $d = Path::band($lows, $highs);

        // Forward lows.
        self::assertStringStartsWith('M0,80 L50,70 L100,60', $d);
        // Reversed highs joined with L (not a fresh M sub-path).
        self::assertStringContainsString('L100,40 L50,30 L0,20', $d);
        self::assertStringEndsWith(' Z', $d);
    }

    public function test_band_smooth_uses_cubic_bezier(): void
    {
        $lows = [[0.0, 80.0], [50.0, 70.0], [100.0, 60.0]];
        $highs = [[0.0, 20.0], [50.0, 30.0], [100.0, 40.0]];
        $d = Path::band($lows, $highs, smooth: true);

        self::assertStringContainsString('C', $d);
        self::assertStringEndsWith(' Z', $d);
    }

    public function test_error_bars_empty_returns_empty_string(): void
    {
        self::assertSame('', Path::errorBars([], 1.0));
    }

    public function test_error_bars_emits_vertical_and_two_caps_per_bar(): void
    {
        $d = Path::errorBars([[50.0, 80.0, 20.0]], 2.0);

        // Vertical stroke at x=50, y from 80 to 20.
        self::assertStringContainsString('M50,80 L50,20', $d);
        // Lower cap at y=80, from x=48 to x=52.
        self::assertStringContainsString('M48,80 L52,80', $d);
        // Upper cap at y=20, from x=48 to x=52.
        self::assertStringContainsString('M48,20 L52,20', $d);
    }
}

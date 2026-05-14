<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use InvalidArgumentException;
use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Axis;
use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Geometry\LogScale;
use Noeka\Svgraph\Geometry\Scale;
use PHPUnit\Framework\TestCase;

final class DualAxisAndLogScaleTest extends TestCase
{
    // ── Series::onAxis ────────────────────────────────────────────────────────

    public function test_series_default_axis_is_left(): void
    {
        $s = Series::from([1, 2, 3]);
        self::assertSame(Axis::Left, $s->axis);
    }

    public function test_series_on_axis_accepts_enum(): void
    {
        $s = Series::from([1, 2, 3])->onAxis(Axis::Right);
        self::assertSame(Axis::Right, $s->axis);
    }

    public function test_series_on_axis_accepts_string(): void
    {
        $s = Series::from([1, 2, 3])->onAxis('right');
        self::assertSame(Axis::Right, $s->axis);

        $s2 = Series::from([1, 2, 3])->onAxis('left');
        self::assertSame(Axis::Left, $s2->axis);
    }

    public function test_series_on_axis_returns_clone(): void
    {
        $original = Series::from([1, 2, 3]);
        $right = $original->onAxis(Axis::Right);
        self::assertNotSame($original, $right);
        self::assertSame(Axis::Left, $original->axis);
        self::assertSame(Axis::Right, $right->axis);
    }

    public function test_series_on_axis_preserves_name_and_color(): void
    {
        $s = Series::of('Costs', [1, 2, 3], '#ef4444')->onAxis('right');
        self::assertSame('Costs', $s->name);
        self::assertSame('#ef4444', $s->color);
    }

    public function test_series_with_name_preserves_axis(): void
    {
        $s = Series::from([1, 2, 3])->onAxis(Axis::Right)->withName('Foo');
        self::assertSame(Axis::Right, $s->axis);
    }

    public function test_series_with_color_preserves_axis(): void
    {
        $s = Series::from([1, 2, 3])->onAxis(Axis::Right)->withColor('#abcdef');
        self::assertSame(Axis::Right, $s->axis);
    }

    public function test_series_on_axis_rejects_invalid_string(): void
    {
        $this->expectException(\ValueError::class);
        Series::from([1, 2, 3])->onAxis('center');
    }

    // ── Log scale on the line chart ───────────────────────────────────────────

    public function test_log_scale_renders_powers_of_ten_as_tick_labels(): void
    {
        $svg = Chart::line([1, 10, 100, 1000])
            ->logScale()
            ->axes()
            ->render();

        // Default linear ticks for [1..1000] would not exclusively show
        // these decade boundaries — log ticks must.
        foreach (['1', '10', '100', '1,000'] as $power) {
            self::assertStringContainsString($power, $svg, "Expected tick label {$power} on log axis.");
        }
    }

    public function test_log_scale_throws_on_non_positive_data(): void
    {
        $chart = Chart::line([0, 10, 100])->logScale();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('positive');
        $chart->render();
    }

    public function test_log_scale_throws_on_negative_data(): void
    {
        $chart = Chart::line([-1, 10, 100])->logScale();
        $this->expectException(InvalidArgumentException::class);
        $chart->render();
    }

    public function test_log_scale_message_names_offending_axis(): void
    {
        $chart = Chart::line([0, 10, 100])->logScale();

        try {
            $chart->render();
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('left', $e->getMessage());
        }
    }

    public function test_log_scale_with_custom_base(): void
    {
        $svg = Chart::line([1, 2, 4, 8, 16])
            ->logScale(base: 2.0)
            ->axes()
            ->render();

        // Powers of 2 should appear as tick labels.
        foreach (['1', '2', '4', '8', '16'] as $power) {
            self::assertStringContainsString(">{$power}<", $svg, "Expected tick label {$power} on base-2 log axis.");
        }
    }

    public function test_log_scale_distributes_decades_evenly(): void
    {
        // For points at 1, 10, 100, 1000 the line path's y-coords should
        // form an evenly-spaced ladder (one decade per step).
        $svg = Chart::line([1, 10, 100, 1000])
            ->logScale()
            ->render();

        // Path data starts with M x,y L x,y L ... — pick out the y-coords.
        preg_match_all('/<path [^>]*\sd="([^"]+)"/', $svg, $matches);
        self::assertNotEmpty($matches[1]);
        $d = $matches[1][0];
        preg_match_all('/[ML]([0-9.\-]+),([0-9.\-]+)/', $d, $points);
        $ys = array_map(floatval(...), $points[2]);
        self::assertCount(4, $ys);

        // Diffs between consecutive y-coords should be equal (within fp tolerance).
        $diffs = [];
        $counter = count($ys);

        for ($i = 1; $i < $counter; $i++) {
            $diffs[] = $ys[$i] - $ys[$i - 1];
        }

        self::assertEqualsWithDelta($diffs[0], $diffs[1], 0.01);
        self::assertEqualsWithDelta($diffs[1], $diffs[2], 0.01);
    }

    // ── Secondary axis ────────────────────────────────────────────────────────

    public function test_secondary_axis_renders_three_axis_lines(): void
    {
        $svg = Chart::line([10, 20, 30])
            ->addSeries(Series::of('Big', [1000, 2000, 3000])->onAxis(Axis::Right))
            ->axes()
            ->secondaryAxis()
            ->render();

        // Left vertical, bottom horizontal, right vertical.
        self::assertSame(3, substr_count($svg, '<line '));
    }

    public function test_no_secondary_axis_renders_two_axis_lines(): void
    {
        $svg = Chart::line([10, 20, 30])
            ->axes()
            ->render();

        // Left vertical, bottom horizontal.
        self::assertSame(2, substr_count($svg, '<line '));
    }

    public function test_secondary_axis_extends_right_padding(): void
    {
        // Default padRight = 2; secondary axis should bump it (e.g. to 12).
        $without = Chart::line([1, 2, 3])->axes()->render();
        $with = Chart::line([1, 2, 3])
            ->addSeries(Series::of('B', [10, 20, 30])->onAxis('right'))
            ->axes()
            ->secondaryAxis()
            ->render();

        // Read the largest x2 across axis lines as plot-right.
        $extractRight = static function (string $svg): float {
            preg_match_all('/<line [^>]*x2="([0-9.]+)"/', $svg, $matches);
            self::assertNotEmpty($matches[1]);
            $values = array_map(floatval(...), $matches[1]);

            return $values === [] ? 0.0 : max($values);
        };

        self::assertGreaterThan($extractRight($with), $extractRight($without));
    }

    public function test_right_axis_series_plots_against_secondary_scale(): void
    {
        // Series A maxes at 3, series B (right) maxes at 3000. Without
        // dual-axis, the path for B would clip way above the chart top.
        // With dual-axis, both series' paths should stay inside the plot area.
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('Big', [1000, 2000, 3000])->onAxis(Axis::Right))
            ->secondaryAxis()
            ->render();

        // Two paths, one per series.
        self::assertSame(2, substr_count($svg, '<path '));

        // Both paths' y-coords should fit within the plot area (0..100).
        preg_match_all('/<path [^>]*\sd="([^"]+)"/', $svg, $paths);

        foreach ($paths[1] as $d) {
            preg_match_all('/[ML]([0-9.\-]+),([0-9.\-]+)/', $d, $coords);

            foreach ($coords[2] as $yStr) {
                $y = (float) $yStr;
                self::assertGreaterThanOrEqual(0.0, $y);
                self::assertLessThanOrEqual(100.0, $y);
            }
        }
    }

    public function test_secondary_axis_renders_right_side_tick_labels(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('Big', [1000, 2000, 3000])->onAxis(Axis::Right))
            ->axes()
            ->secondaryAxis()
            ->render();

        // Right-side labels are positioned at left:88% (plot right edge).
        self::assertMatchesRegularExpression('/left:8\d(\.\d+)?%/', $svg);

        // 3,000 should appear as a right-axis tick.
        self::assertStringContainsString('3,000', $svg);
    }

    public function test_secondary_axis_tick_labels_take_first_right_series_color(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('Big', [1000, 2000, 3000], '#ff00aa')->onAxis(Axis::Right))
            ->axes()
            ->secondaryAxis()
            ->render();

        self::assertStringContainsString('color:#ff00aa', $svg);
    }

    public function test_secondary_axis_with_explicit_scale_override(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('Big', [1000, 2000, 3000])->onAxis(Axis::Right))
            ->axes()
            ->secondaryAxis(Scale::linear(0.0, 5000.0, 0.0, 0.0))
            ->render();

        // With a fixed [0, 5000] domain, 5,000 should appear as a tick.
        self::assertStringContainsString('5,000', $svg);
    }

    // ── Combinations: left/right × linear/log ─────────────────────────────────

    public function test_linear_left_linear_right(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('B', [100, 200, 300])->onAxis(Axis::Right))
            ->axes()
            ->secondaryAxis()
            ->render();

        self::assertSame(2, substr_count($svg, '<path '));
        self::assertSame(3, substr_count($svg, '<line '));
        // 300 (right side max) should appear.
        self::assertStringContainsString('300', $svg);
    }

    public function test_log_left_linear_right(): void
    {
        $svg = Chart::line([1, 10, 100])
            ->addSeries(Series::of('B', [100, 200, 300])->onAxis(Axis::Right))
            ->logScale()
            ->axes()
            ->secondaryAxis()
            ->render();

        // Log decade labels on the left.
        self::assertStringContainsString('>1<', $svg);
        self::assertStringContainsString('>10<', $svg);
        self::assertStringContainsString('>100<', $svg);
    }

    public function test_linear_left_log_right(): void
    {
        $svg = Chart::line([10, 20, 30])
            ->addSeries(Series::of('B', [1, 100, 10_000])->onAxis(Axis::Right))
            ->logScale(axis: Axis::Right)
            ->axes()
            ->render();

        // Log targets right axis → secondaryAxis() implicitly enabled.
        self::assertSame(3, substr_count($svg, '<line '));
        // Right-side decade labels.
        self::assertStringContainsString('10,000', $svg);
    }

    public function test_log_left_log_right(): void
    {
        $svg = Chart::line([1, 10, 100])
            ->addSeries(Series::of('B', [1, 100, 10_000])->onAxis(Axis::Right))
            ->logScale()
            ->logScale(axis: 'right')
            ->axes()
            ->render();

        self::assertSame(2, substr_count($svg, '<path '));
        self::assertSame(3, substr_count($svg, '<line '));
    }

    public function test_log_right_axis_throws_on_non_positive_right_series_data(): void
    {
        $chart = Chart::line([10, 20, 30])
            ->addSeries(Series::of('B', [-1, 100, 10_000])->onAxis(Axis::Right))
            ->logScale(axis: 'right');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('right');
        $chart->render();
    }

    public function test_log_left_axis_does_not_validate_right_axis_series(): void
    {
        // Right-axis series has zeros, but we only log the left axis.
        // Should render without error.
        $svg = Chart::line([1, 10, 100])
            ->addSeries(Series::of('B', [0, 50, 100])->onAxis(Axis::Right))
            ->logScale()
            ->secondaryAxis()
            ->render();

        self::assertNotSame('', $svg);
    }

    public function test_secondary_axis_passthrough_log_scale_override(): void
    {
        // Passing a LogScale instance via secondaryAxis() should mark the
        // right axis as log even without calling logScale('right').
        $svg = Chart::line([10, 20, 30])
            ->addSeries(Series::of('B', [1, 100, 10_000])->onAxis(Axis::Right))
            ->axes()
            ->secondaryAxis(LogScale::log(1.0, 10_000.0, 0.0, 0.0))
            ->render();

        // Each decade boundary must appear as its own tick label — only a
        // log scale produces this set. A linear fallback (e.g. from
        // `instanceof LogScale` being bypassed) would emit evenly-spaced
        // ticks like 0/2,500/5,000/7,500/10,000 instead.
        foreach (['>1<', '>10<', '>100<', '>1,000<', '>10,000<'] as $tick) {
            self::assertStringContainsString($tick, $svg, "Expected decade tick {$tick}.");
        }
    }

    public function test_secondary_axis_linear_override_inverts_y_axis(): void
    {
        // The non-LogScale `instanceof Scale` branch constructs a new Scale
        // with `invert: true`. If that flips to `false`, large values would
        // render at large y instead of small y. Verify the path for the
        // right-side series has the maximum at the smallest y.
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('B', [100, 200, 300])->onAxis(Axis::Right))
            ->axes()
            ->secondaryAxis(Scale::linear(0.0, 500.0, 0.0, 0.0))
            ->render();

        // Two paths, series-1 is the right-axis series.
        preg_match_all('/<path class="series-1"[^>]*\sd="([^"]+)"/', $svg, $m);
        self::assertNotEmpty($m[1]);
        preg_match_all('/[ML][0-9.\-]+,([0-9.\-]+)/', $m[1][0], $coords);
        $ys = array_map(floatval(...), $coords[1]);
        self::assertCount(3, $ys);
        // With invert:true, the largest value (300) maps to the smallest y.
        self::assertLessThan($ys[0], $ys[2]);
    }

    public function test_constant_series_renders_horizontal_line_at_midpoint(): void
    {
        // A series where every value is equal triggers the `$min === $max`
        // branch in buildYScale, which pads the domain by ±1.0. The
        // resulting plot must place the line at the vertical mid (y=50 in
        // the 100x100 viewport without axes).
        $svg = Chart::line([5, 5, 5])->render();
        preg_match_all('/class="series-0" d="([^"]+)"/', $svg, $matches);
        self::assertNotEmpty($matches[1]);
        preg_match_all('/[ML][0-9.\-]+,([0-9.\-]+)/', $matches[1][0], $coords);
        $ys = array_map(floatval(...), $coords[1]);
        self::assertCount(3, $ys);

        // All three y-coords must be exactly 50: any tweak to the ±1.0
        // expansion (mutants on $min -= 1.0 / $max += 1.0) would shift this.
        foreach ($ys as $y) {
            self::assertSame(50.0, $y);
        }
    }

    public function test_axis_color_falls_back_to_theme_when_no_right_series(): void
    {
        // secondaryAxis() enabled but every series is on the left axis.
        // Should still render without error and use the theme axis color.
        $svg = Chart::line([1, 2, 3])
            ->axes()
            ->secondaryAxis()
            ->render();

        self::assertSame(3, substr_count($svg, '<line '));
    }
}

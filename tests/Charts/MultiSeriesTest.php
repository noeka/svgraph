<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;
use PHPUnit\Framework\TestCase;

final class MultiSeriesTest extends TestCase
{
    // ── Series metadata ────────────────────────────────────────────────────────

    public function test_series_of_attaches_name_and_color(): void
    {
        $s = Series::of('Revenue', [1, 2, 3], '#3b82f6');
        self::assertSame('Revenue', $s->name);
        self::assertSame('#3b82f6', $s->color);
        self::assertSame([1.0, 2.0, 3.0], $s->values);
    }

    public function test_series_of_color_optional(): void
    {
        $s = Series::of('Costs', [10, 20]);
        self::assertSame('Costs', $s->name);
        self::assertNull($s->color);
    }

    public function test_series_with_name_returns_clone(): void
    {
        $s = Series::from([1, 2, 3])->withName('Foo');
        self::assertSame('Foo', $s->name);
    }

    public function test_series_with_color_returns_clone(): void
    {
        $s = Series::from([1, 2, 3])->withColor('#abcdef');
        self::assertSame('#abcdef', $s->color);
    }

    // ── LineChart ──────────────────────────────────────────────────────────────

    public function test_line_chart_renders_multiple_polylines(): void
    {
        $svg = Chart::line([10, 20, 30])
            ->addSeries(Series::of('B', [5, 15, 25]))
            ->render();

        // One path per series.
        self::assertSame(2, substr_count($svg, '<path '));
        self::assertStringContainsString('class="series-0"', $svg);
        self::assertStringContainsString('class="series-1"', $svg);
    }

    public function test_line_chart_per_series_color_from_palette(): void
    {
        $svg = Chart::line([10, 20, 30])
            ->addSeries(Series::of('B', [5, 15, 25]))
            ->render();

        // Default palette: series 0 → #3b82f6, series 1 → #10b981.
        self::assertStringContainsString('stroke="#3b82f6"', $svg);
        self::assertStringContainsString('stroke="#10b981"', $svg);
    }

    public function test_line_chart_explicit_series_color_wins_over_palette(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('B', [4, 5, 6], '#ff00ff'))
            ->render();

        self::assertStringContainsString('stroke="#ff00ff"', $svg);
    }

    public function test_line_chart_y_scale_extends_to_cover_all_series(): void
    {
        // Series 1's max (100) is far above series 0's max (3); the chart
        // must scale its Y-axis to fit both. Without a combined scale, the
        // 100-value line would render off-canvas.
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('Big', [50, 75, 100]))
            ->axes()
            ->render();

        // 100 should appear in the y-axis ticks.
        self::assertStringContainsString('100', $svg);
    }

    public function test_line_chart_markers_per_series(): void
    {
        $svg = Chart::line([10, 20])
            ->addSeries(Series::of('B', [30, 40]))
            ->points()
            ->render();

        // 2 series × 2 points × 2 ellipses (visual + hit) = 8 ellipses.
        self::assertSame(8, substr_count($svg, '<ellipse '));
        // Marker groups carry the series class.
        self::assertMatchesRegularExpression('/<g class="series-0">/', $svg);
        self::assertMatchesRegularExpression('/<g class="series-1">/', $svg);
    }

    public function test_line_chart_marker_id_includes_series_index(): void
    {
        $svg = Chart::line([10, 20])
            ->addSeries(Series::of('B', [30, 40]))
            ->points()
            ->render();

        // Series 0, point 0 and series 1, point 1 must both have unique IDs.
        self::assertMatchesRegularExpression('/id="svgraph-\d+-s0-pt-0"/', $svg);
        self::assertMatchesRegularExpression('/id="svgraph-\d+-s1-pt-1"/', $svg);
    }

    public function test_line_multi_series_tooltip_includes_series_name(): void
    {
        $svg = Chart::line([['Mon', 10]])
            ->addSeries(Series::of('Costs', [['Mon', 5]]))
            ->points()
            ->render();

        self::assertStringContainsString('<title>Costs — Mon: 5</title>', $svg);
    }

    public function test_line_chart_data_resets_to_single_series(): void
    {
        // Calling data() after addSeries() should drop everything and
        // start fresh — preserves builder ergonomics.
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('B', [4, 5, 6]))
            ->data([10, 20, 30])
            ->render();

        self::assertSame(1, substr_count($svg, '<path '));
    }

    public function test_line_chart_handles_series_of_different_lengths(): void
    {
        // 3-point series and 5-point series should both render against the
        // same x-axis; the longer series determines x extent.
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('B', [10, 20, 30, 40, 50]))
            ->render();

        self::assertSame(2, substr_count($svg, '<path '));
    }

    // ── BarChart: grouped ─────────────────────────────────────────────────────

    public function test_bar_grouped_renders_one_rect_per_series_per_slot(): void
    {
        $svg = Chart::bar(['Q1' => 10, 'Q2' => 20])
            ->addSeries(Series::of('Costs', ['Q1' => 5, 'Q2' => 8]))
            ->grouped()
            ->render();

        // 2 slots × 2 series = 4 rects.
        self::assertSame(4, substr_count($svg, '<rect '));
    }

    public function test_bar_grouped_assigns_per_series_class(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])
            ->addSeries(Series::of('Costs', ['A' => 3, 'B' => 4]))
            ->grouped()
            ->render();

        self::assertSame(2, substr_count($svg, 'class="series-0"'));
        self::assertSame(2, substr_count($svg, 'class="series-1"'));
    }

    public function test_bar_multi_series_defaults_to_grouped(): void
    {
        $defaulted = Chart::bar(['A' => 1, 'B' => 2])
            ->addSeries(Series::of('Costs', ['A' => 3, 'B' => 4]))
            ->render();

        $explicit = Chart::bar(['A' => 1, 'B' => 2])
            ->addSeries(Series::of('Costs', ['A' => 3, 'B' => 4]))
            ->grouped()
            ->render();

        // Each render uses a new auto-incrementing chart ID; normalise so
        // we're comparing layout, not identity.
        $normalise = static fn(string $svg): string => preg_replace('/svgraph-\d+/', 'svgraph-N', $svg) ?? '';
        self::assertSame($normalise($defaulted), $normalise($explicit));
    }

    public function test_bar_grouped_y_scale_extends_to_cover_all_series(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])
            ->addSeries(Series::of('Big', ['A' => 90, 'B' => 100]))
            ->grouped()
            ->axes()
            ->render();

        self::assertStringContainsString('100', $svg);
    }

    public function test_bar_grouped_per_series_color_from_palette(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])
            ->addSeries(Series::of('Two', ['A' => 3, 'B' => 4]))
            ->grouped()
            ->render();

        // Default palette: series 0 → #3b82f6, series 1 → #10b981.
        self::assertSame(2, substr_count($svg, 'fill="#3b82f6"'));
        self::assertSame(2, substr_count($svg, 'fill="#10b981"'));
    }

    public function test_bar_grouped_explicit_series_color_wins(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])
            ->addSeries(Series::of('Two', ['A' => 3, 'B' => 4], '#abcdef'))
            ->grouped()
            ->render();

        self::assertSame(2, substr_count($svg, 'fill="#abcdef"'));
    }

    public function test_bar_grouped_horizontal_renders_rects(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])
            ->addSeries(Series::of('Two', ['A' => 3, 'B' => 4]))
            ->grouped()
            ->horizontal()
            ->render();

        self::assertSame(4, substr_count($svg, '<rect '));
    }

    public function test_bar_grouped_bars_are_narrower_than_single(): void
    {
        // Two side-by-side bars must be narrower than one full-slot bar.
        $single = Chart::bar(['A' => 1, 'B' => 2])->render();
        $grouped = Chart::bar(['A' => 1, 'B' => 2])
            ->addSeries(Series::of('Two', ['A' => 3, 'B' => 4]))
            ->grouped()
            ->render();

        self::assertGreaterThan(
            $this->firstRectWidth($grouped),
            $this->firstRectWidth($single),
        );
    }

    private function firstRectWidth(string $svg): float
    {
        if (preg_match('/<rect [^>]*width="([0-9.]+)"/', $svg, $match) !== 1) {
            self::fail('No rect width found in SVG.');
        }
        return (float) $match[1];
    }

    // ── BarChart: stacked ─────────────────────────────────────────────────────

    public function test_bar_stacked_renders_one_rect_per_value(): void
    {
        $svg = Chart::bar(['A' => 10, 'B' => 20])
            ->addSeries(Series::of('Two', ['A' => 5, 'B' => 8]))
            ->stacked()
            ->render();

        self::assertSame(4, substr_count($svg, '<rect '));
    }

    public function test_bar_stacked_y_max_uses_cumulative_sum(): void
    {
        // Series A max = 10, Series B max = 30. Single max would be 30,
        // but stacked must extend to 10 + 30 = 40 (the column total).
        $svg = Chart::bar(['X' => 10])
            ->addSeries(Series::of('B', ['X' => 30]))
            ->stacked()
            ->axes()
            ->render();

        // 40 should appear in the y-axis ticks.
        self::assertStringContainsString('40', $svg);
    }

    public function test_bar_stacked_segments_dont_overlap(): void
    {
        // For column "X" with A=10 and B=20 stacked, we expect:
        //   Series A segment: y=baseline-10..baseline (height = 10 in Y units)
        //   Series B segment: y=baseline-30..baseline-10 (sits on top of A)
        // The two rects must have different y values.
        $svg = Chart::bar(['X' => 10])
            ->addSeries(Series::of('B', ['X' => 20]))
            ->stacked()
            ->render();

        preg_match_all('/<rect [^>]*y="([0-9.]+)"/', $svg, $matches);
        $ys = array_unique($matches[1]);
        // Two distinct y positions for the two stacked segments.
        self::assertCount(2, $ys);
    }

    public function test_bar_stacked_horizontal_uses_cumulative_sum(): void
    {
        $svg = Chart::bar(['X' => 10])
            ->addSeries(Series::of('B', ['X' => 30]))
            ->stacked()
            ->horizontal()
            ->axes()
            ->render();

        self::assertStringContainsString('40', $svg);
    }

    public function test_bar_stacked_assigns_per_series_class(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])
            ->addSeries(Series::of('Two', ['A' => 3, 'B' => 4]))
            ->stacked()
            ->render();

        self::assertSame(2, substr_count($svg, 'class="series-0"'));
        self::assertSame(2, substr_count($svg, 'class="series-1"'));
    }

    public function test_bar_stacked_skips_zero_values(): void
    {
        // A zero value contributes nothing to the stack and is not drawn.
        $svg = Chart::bar(['A' => 10, 'B' => 0])
            ->addSeries(Series::of('Two', ['A' => 5, 'B' => 5]))
            ->stacked()
            ->render();

        // 4 expected segments minus 1 zero = 3.
        self::assertSame(3, substr_count($svg, '<rect '));
    }

    public function test_bar_stacked_negative_values_extend_y_axis_below_zero(): void
    {
        // Two negatives stacked downward: cumulative minimum is the sum.
        $svg = Chart::bar(['X' => -10])
            ->addSeries(Series::of('B', ['X' => -20]))
            ->stacked()
            ->axes()
            ->render();

        // -30 should appear as a tick label on the y axis.
        self::assertStringContainsString('-30', $svg);
    }

    public function test_bar_stacked_horizontal_negative_values_extend_x_axis(): void
    {
        $svg = Chart::bar(['X' => -10])
            ->addSeries(Series::of('B', ['X' => -20]))
            ->stacked()
            ->horizontal()
            ->axes()
            ->render();

        self::assertStringContainsString('-30', $svg);
    }

    public function test_bar_stacked_three_series_y_max_is_total(): void
    {
        $svg = Chart::bar(['X' => 10])
            ->addSeries(Series::of('B', ['X' => 20]))
            ->addSeries(Series::of('C', ['X' => 30]))
            ->stacked()
            ->axes()
            ->render();

        // 10 + 20 + 30 = 60.
        self::assertStringContainsString('60', $svg);
    }

    public function test_bar_grouped_then_stacked_uses_last_choice(): void
    {
        $svg = Chart::bar(['A' => 10, 'B' => 20])
            ->addSeries(Series::of('Two', ['A' => 5, 'B' => 5]))
            ->grouped()
            ->stacked()
            ->render();

        // Stacked produces 4 rects in 2 distinct y-positions per slot.
        preg_match_all('/<rect [^>]*y="([0-9.]+)"/', $svg, $matches);
        self::assertGreaterThan(2, count(array_unique($matches[1])));
    }

    // ── BarChart: backward-compat single-series ──────────────────────────────

    public function test_bar_single_series_unchanged_layout(): void
    {
        // Single-series rendering must continue to occupy the full slot width.
        $svg = Chart::bar(['A' => 10, 'B' => 20])->render();
        self::assertSame(2, substr_count($svg, '<rect '));
    }

    public function test_bar_single_series_id_format_includes_series_index(): void
    {
        $svg = Chart::bar(['A' => 1])->render();
        self::assertMatchesRegularExpression('/id="svgraph-\d+-s0-pt-0"/', $svg);
    }

    // ── Tooltips with series name ─────────────────────────────────────────────

    public function test_bar_grouped_tooltip_includes_series_name(): void
    {
        $svg = Chart::bar(['Q1' => 10])
            ->addSeries(Series::of('Costs', ['Q1' => 5]))
            ->grouped()
            ->render();

        self::assertStringContainsString('<title>Costs — Q1: 5</title>', $svg);
    }

    public function test_bar_stacked_tooltip_includes_series_name(): void
    {
        $svg = Chart::bar(['Q1' => 10])
            ->addSeries(Series::of('Costs', ['Q1' => 5]))
            ->stacked()
            ->render();

        self::assertStringContainsString('<title>Costs — Q1: 5</title>', $svg);
    }
}

<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `Series::withTrendLine()` and the line-chart trend overlay
 * (issue #15).
 *
 * The overlay is a single dashed `<path class="svgraph-trend series-N">`
 * drawn on top of the raw data, clipped to the data range and never
 * extrapolated.
 */
final class TrendLineTest extends TestCase
{
    public function test_trend_off_by_default(): void
    {
        $svg = Chart::line([1, 2, 3, 4, 5])->render();

        self::assertStringNotContainsString('svgraph-trend', $svg);
    }

    public function test_trend_emits_exactly_one_extra_path(): void
    {
        // Acceptance criterion: toggling trend on a series renders exactly
        // one extra <path> vs. the same chart without trend.
        $plain = Chart::line()->addSeries(Series::from([1, 4, 9, 16, 25]))->render();
        $trended = Chart::line()->addSeries(Series::from([1, 4, 9, 16, 25])->withTrendLine())->render();

        self::assertSame(
            substr_count($plain, '<path ') + 1,
            substr_count($trended, '<path '),
        );
    }

    public function test_trend_path_carries_distinct_class(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::from([1, 2, 3])->withTrendLine())
            ->render();

        // Class ordering matters: `svgraph-trend` first so the hover rules
        // `path[class^="series-"]` don't fire on the muted trend overlay,
        // but legend toggles (which match by class name, not prefix) still hide it.
        self::assertStringContainsString('class="svgraph-trend series-0"', $svg);
    }

    public function test_trend_path_is_dashed_and_half_opaque(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::from([1, 2, 3, 4, 5])->withTrendLine())
            ->render();

        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-trend[^"]*"[^>]+stroke-dasharray="4,3"/',
            $svg,
        );
        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-trend[^"]*"[^>]+opacity="0\.5"/',
            $svg,
        );
    }

    public function test_trend_path_uses_resolved_series_color(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::of('Revenue', [10, 20, 30, 40], '#ff8800')->withTrendLine())
            ->render();

        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-trend[^"]*"[^>]+stroke="#ff8800"/',
            $svg,
        );
    }

    public function test_trend_path_has_accessible_title_with_slope_and_r2(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::of('Sales', [0, 1, 2, 3, 4])->withTrendLine())
            ->render();

        // Perfect line through (0,0)–(4,4): slope 1, R² 1.
        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-trend[^"]*"[^>]*>.*?<title>Sales — Trend: slope 1, R² 1<\/title>/',
            $svg,
        );
    }

    public function test_trend_path_title_drops_series_prefix_when_unnamed(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::from([0, 1, 2, 3, 4])->withTrendLine())
            ->render();

        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-trend[^"]*"[^>]*>.*?<title>Trend: slope 1, R² 1<\/title>/',
            $svg,
        );
    }

    public function test_trend_path_is_clipped_to_data_range(): void
    {
        // For an axes-off chart on the default viewport, the plot area spans
        // x=0 to x=100. The trend line must run from the first data x to the
        // last data x — i.e. the same span — without extrapolation beyond.
        $svg = Chart::line()
            ->addSeries(Series::from([0, 1, 2, 3, 4])->withTrendLine())
            ->render();

        $matched = preg_match(
            '/<path[^>]+class="svgraph-trend[^"]*"[^>]+d="M([\d.\-]+),[\d.\-]+ L([\d.\-]+),[\d.\-]+"/',
            $svg,
            $m,
        );
        self::assertSame(1, $matched);
        self::assertCount(3, $m);

        self::assertEqualsWithDelta(0.0, (float) $m[1], 0.01);
        self::assertEqualsWithDelta(100.0, (float) $m[2], 0.01);
    }

    public function test_trend_skipped_for_single_point_series(): void
    {
        // A single point can't fit a line — render must succeed without crashing.
        $svg = Chart::line()
            ->addSeries(Series::from([42])->withTrendLine())
            ->render();

        self::assertStringNotContainsString('svgraph-trend', $svg);
    }

    public function test_trend_skipped_for_empty_series(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::from([])->withTrendLine())
            ->render();

        self::assertStringNotContainsString('svgraph-trend', $svg);
    }

    public function test_trend_can_be_disabled_again(): void
    {
        $series = Series::of('X', [1, 2, 3])->withTrendLine()->withTrendLine(false);

        $svg = Chart::line()->addSeries($series)->render();

        self::assertStringNotContainsString('svgraph-trend', $svg);
    }

    public function test_trend_flag_preserved_across_with_modifiers(): void
    {
        $series = Series::of('X', [1, 2, 3])->withTrendLine();
        self::assertTrue($series->withName('Y')->showTrend);
        self::assertTrue($series->withColor('#abcdef')->showTrend);
        self::assertTrue($series->onAxis('right')->showTrend);
    }

    public function test_trend_stats_match_regression_on_index_value_pairs(): void
    {
        // R² is exposed even when the overlay is not rendered.
        $stats = Series::of('X', [2.0, 4.0, 6.0, 8.0])->trendStats();

        self::assertNotNull($stats);
        self::assertEqualsWithDelta(2.0, $stats['slope'], 1e-12);
        self::assertEqualsWithDelta(2.0, $stats['intercept'], 1e-12);
        self::assertEqualsWithDelta(1.0, $stats['r2'], 1e-12);
    }

    public function test_trend_stats_null_for_short_series(): void
    {
        self::assertNull(Series::from([])->trendStats());
        self::assertNull(Series::from([5])->trendStats());
    }

    public function test_multi_series_trend_renders_per_enabled_series(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::of('A', [1, 2, 3, 4])->withTrendLine())
            ->addSeries(Series::of('B', [4, 3, 2, 1])->withTrendLine())
            ->addSeries(Series::of('C', [1, 1, 1, 1])) // no trend toggle
            ->render();

        self::assertStringContainsString('class="svgraph-trend series-0"', $svg);
        self::assertStringContainsString('class="svgraph-trend series-1"', $svg);
        self::assertStringNotContainsString('class="svgraph-trend series-2"', $svg);
    }

    public function test_trend_description_mentions_slope_and_r2(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::of('Sales', [0, 1, 2, 3])->withTrendLine())
            ->render();

        // The accessible <desc> appends the trend summary so screen readers
        // hear "Sales trend: slope 1, R² 1." after the data range.
        self::assertMatchesRegularExpression(
            '/<desc id="svgraph-\d+-desc">[^<]*Sales trend: slope 1, R² 1\.<\/desc>/',
            $svg,
        );
    }

    public function test_trend_path_does_not_match_series_hover_class_prefix(): void
    {
        // The series CSS hover rule keys on `path[class^="series-"]`. Because
        // the trend path's class starts with `svgraph-trend`, it must NOT
        // share the same prefix — otherwise hovering the trend (or any path
        // whose selector matches) would brighten unrelated elements.
        $svg = Chart::line()
            ->addSeries(Series::of('Sales', [1, 2, 3])->withTrendLine())
            ->render();

        // The trend path's class starts with `svgraph-trend`, not `series-`.
        self::assertSame(
            1,
            preg_match_all('/<path[^>]+class="svgraph-trend series-0"/', $svg),
        );
        self::assertSame(
            0,
            preg_match_all('/<path[^>]+class="series-0 svgraph-trend"/', $svg),
        );
    }
}

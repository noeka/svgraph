<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\ErrorDisplay;
use Noeka\Svgraph\Data\Point;
use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Geometry\LogScale;
use Noeka\Svgraph\Theme;
use PHPUnit\Framework\TestCase;

/**
 * Error bars and confidence bands on line charts (issue #16).
 *
 * A point carries an optional `low`/`high` range; a series toggles its
 * presentation via `withErrorBars()` or `withConfidenceBand()`. Both modes
 * must scale correctly under linear, log, and time axes and surface the
 * range in tooltips and the screen-reader data table.
 */
final class ErrorRangeTest extends TestCase
{
    public function test_no_overlay_when_no_range_data_present(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::from([4, 5, 6])->withErrorBars())
            ->render();

        self::assertStringNotContainsString('svgraph-errorbars', $svg);
        self::assertStringNotContainsString('svgraph-band', $svg);
    }

    public function test_no_overlay_when_range_data_but_mode_is_none(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::from([['Mon', 10, 5, 15], ['Tue', 20, 16, 24]]))
            ->render();

        self::assertStringNotContainsString('svgraph-errorbars', $svg);
        self::assertStringNotContainsString('svgraph-band', $svg);
    }

    public function test_error_bars_emit_one_path_per_series(): void
    {
        $svg = Chart::line()
            ->addSeries(
                Series::from([['Mon', 10, 5, 15], ['Tue', 20, 16, 24], ['Wed', 14, 12, 18]])
                    ->withErrorBars(),
            )
            ->render();

        self::assertSame(
            1,
            preg_match_all('/<path[^>]+class="svgraph-errorbars series-0"/', $svg),
        );
    }

    public function test_error_bars_path_contains_one_vertical_and_two_caps_per_point(): void
    {
        // Three ranged points → vertical (M..L) + 2 caps each = 9 sub-paths.
        $svg = Chart::line()
            ->addSeries(
                Series::from([['Mon', 10, 5, 15], ['Tue', 20, 16, 24], ['Wed', 14, 12, 18]])
                    ->withErrorBars(),
            )
            ->render();

        $matched = preg_match(
            '/<path[^>]+class="svgraph-errorbars[^"]*"[^>]+d="([^"]+)"/',
            $svg,
            $m,
        );
        self::assertSame(1, $matched);
        self::assertSame(9, substr_count($m[1], 'M'));
    }

    public function test_error_bars_geometry_at_fixed_dataset(): void
    {
        // Two ranged points with constant value=50, low=0, high=100. The
        // bounds-aware y-axis has domain [0,100] + 10% padding = [-10,110];
        // the value 0 maps to y≈91.67 and 100 maps to y≈8.33 in the
        // axes-off 100×100 viewport. Both bars sit at x=0 and x=100.
        $svg = Chart::line()
            ->addSeries(
                Series::from([['Mon', 50, 0, 100], ['Tue', 50, 0, 100]])
                    ->withErrorBars(),
            )
            ->render();

        $matched = preg_match(
            '/<path[^>]+class="svgraph-errorbars[^"]*"[^>]+d="([^"]+)"/',
            $svg,
            $m,
        );
        self::assertSame(1, $matched);
        $d = $m[1];
        // Vertical bar at the first column (x=0) spans low→high.
        self::assertStringContainsString('M0,91.6667 L0,8.3333', $d);
        // Vertical bar at the last column (x=100) spans the same range.
        self::assertStringContainsString('M100,91.6667 L100,8.3333', $d);
    }

    public function test_error_bars_default_class_ordering_preserves_legend_toggles(): void
    {
        // `svgraph-errorbars` must come first so the path[class^="series-"]
        // hover rule doesn't match the overlay, but legend toggles still hide it.
        $svg = Chart::line()
            ->addSeries(
                Series::from([['A', 10, 5, 15], ['B', 12, 9, 15]])
                    ->withErrorBars(),
            )
            ->render();

        self::assertStringContainsString('class="svgraph-errorbars series-0"', $svg);
    }

    public function test_error_bars_use_series_color(): void
    {
        $svg = Chart::line()
            ->addSeries(
                Series::of('Sales', [['A', 10, 5, 15], ['B', 12, 9, 15]], '#ff5500')
                    ->withErrorBars(),
            )
            ->render();

        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-errorbars[^"]*"[^>]+stroke="#ff5500"/',
            $svg,
        );
    }

    public function test_error_bars_can_be_disabled_again(): void
    {
        $series = Series::from([['A', 10, 5, 15], ['B', 12, 9, 15]])
            ->withErrorBars()
            ->withErrorBars(false);

        self::assertSame(ErrorDisplay::None, $series->errorDisplay);

        $svg = Chart::line()->addSeries($series)->render();
        self::assertStringNotContainsString('svgraph-errorbars', $svg);
    }

    public function test_confidence_band_emits_one_filled_path(): void
    {
        $svg = Chart::line()
            ->addSeries(
                Series::from([['A', 10, 5, 15], ['B', 12, 9, 15], ['C', 14, 11, 17]])
                    ->withConfidenceBand(),
            )
            ->render();

        self::assertSame(
            1,
            preg_match_all('/<path[^>]+class="svgraph-band series-0"/', $svg),
        );
        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-band[^"]*"[^>]+fill-opacity="0\.18"/',
            $svg,
        );
        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-band[^"]*"[^>]+stroke="none"/',
            $svg,
        );
    }

    public function test_confidence_band_path_is_closed(): void
    {
        $svg = Chart::line()
            ->addSeries(
                Series::from([['A', 10, 5, 15], ['B', 12, 9, 15], ['C', 14, 11, 17]])
                    ->withConfidenceBand(),
            )
            ->render();

        $matched = preg_match(
            '/<path[^>]+class="svgraph-band[^"]*"[^>]+d="([^"]+)"/',
            $svg,
            $m,
        );
        self::assertSame(1, $matched);
        self::assertStringEndsWith(' Z', $m[1]);
    }

    public function test_confidence_band_breaks_across_unranged_points(): void
    {
        // Two contiguous ranges separated by a point without range data must
        // produce two sub-paths, each ending in Z.
        $svg = Chart::line()
            ->addSeries(
                new Series([
                    new Point(10, 'A', low: 8, high: 12),
                    new Point(20, 'B', low: 16, high: 24),
                    new Point(30, 'C'), // gap
                    new Point(40, 'D', low: 36, high: 44),
                    new Point(50, 'E', low: 44, high: 56),
                ], errorDisplay: ErrorDisplay::Band),
            )
            ->render();

        $matched = preg_match(
            '/<path[^>]+class="svgraph-band[^"]*"[^>]+d="([^"]+)"/',
            $svg,
            $m,
        );
        self::assertSame(1, $matched);
        self::assertSame(2, substr_count($m[1], ' Z'));
    }

    public function test_confidence_band_skipped_for_single_ranged_point(): void
    {
        // A single ranged point cannot form a polyline — skip emission silently.
        $svg = Chart::line()
            ->addSeries(
                new Series([
                    new Point(10, 'A', low: 5, high: 15),
                    new Point(20, 'B'),
                    new Point(30, 'C'),
                ], errorDisplay: ErrorDisplay::Band),
            )
            ->render();

        self::assertStringNotContainsString('svgraph-band', $svg);
    }

    public function test_modes_are_mutually_exclusive(): void
    {
        $series = Series::from([['A', 10, 5, 15], ['B', 12, 9, 15]])
            ->withErrorBars()
            ->withConfidenceBand();

        self::assertSame(ErrorDisplay::Band, $series->errorDisplay);

        $svg = Chart::line()->addSeries($series)->render();
        self::assertStringContainsString('svgraph-band', $svg);
        self::assertStringNotContainsString('svgraph-errorbars', $svg);
    }

    public function test_tooltip_includes_range_when_present(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::from([['Mon', 10, 8, 12]])->withErrorBars())
            ->points()
            ->render();

        // Tooltip "Mon: 10 (8–12)" surfaces in the SVG <title> on the marker.
        self::assertStringContainsString('<title>Mon: 10 (8–12)</title>', $svg);
    }

    public function test_data_table_appends_range_to_value_cell(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::from([['Mon', 10, 8, 12], ['Tue', 20, 15, 25]]))
            ->render();

        self::assertStringContainsString('<th scope="row">Mon</th><td>10 (8–12)</td>', $svg);
        self::assertStringContainsString('<th scope="row">Tue</th><td>20 (15–25)</td>', $svg);
    }

    public function test_y_axis_extends_to_include_range(): void
    {
        // Values 10..20 with low=5, high=25 expand the axis domain past the
        // value range. "Nice" ticks then land at 5, 10, 15, 20, 25 — the
        // label "25" only appears when the bounds-aware axisDomain runs.
        $svg = Chart::line()
            ->addSeries(
                Series::from([['A', 10, 5, 15], ['B', 20, 15, 25]]),
            )
            ->axes()
            ->render();

        self::assertMatchesRegularExpression('/>25<\/span>/', $svg);
        self::assertMatchesRegularExpression('/>5<\/span>/', $svg);
    }

    public function test_band_renders_with_log_scale(): void
    {
        // Lows and highs are strictly positive — log scale must compute
        // without throwing and produce a band path.
        $svg = Chart::line()
            ->addSeries(
                Series::from([['A', 10, 5, 20], ['B', 100, 50, 200], ['C', 1000, 500, 2000]])
                    ->withConfidenceBand(),
            )
            ->logScale()
            ->render();

        self::assertStringContainsString('svgraph-band', $svg);
        // Path must contain a Z (closed band) — it didn't bail out.
        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-band[^"]*"[^>]+d="[^"]*Z[^"]*"/',
            $svg,
        );
    }

    public function test_error_bars_render_with_time_axis(): void
    {
        $points = [
            [new \DateTimeImmutable('2026-01-01T00:00:00Z'), 10, 8, 12],
            [new \DateTimeImmutable('2026-02-01T00:00:00Z'), 14, 11, 17],
            [new \DateTimeImmutable('2026-03-01T00:00:00Z'), 12, 9, 15],
        ];
        $svg = Chart::line()
            ->addSeries(Series::from($points)->withErrorBars())
            ->timeAxis(tz: 'UTC')
            ->axes()
            ->render();

        self::assertStringContainsString('svgraph-errorbars', $svg);
    }

    public function test_band_renders_with_smooth_line(): void
    {
        $svg = Chart::line()
            ->addSeries(
                Series::from([['A', 10, 5, 15], ['B', 12, 9, 15], ['C', 14, 11, 17], ['D', 18, 14, 22]])
                    ->withConfidenceBand(),
            )
            ->smooth()
            ->render();

        self::assertStringContainsString('svgraph-band', $svg);
        // Smooth band uses cubic Bezier ("C" command) in the path data.
        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-band[^"]*"[^>]+d="[^"]*C/',
            $svg,
        );
    }

    public function test_multi_series_supports_independent_modes(): void
    {
        $svg = Chart::line()
            ->addSeries(Series::of('A', [['Q1', 10, 5, 15], ['Q2', 12, 9, 15]])->withConfidenceBand())
            ->addSeries(Series::of('B', [['Q1', 20, 18, 22], ['Q2', 24, 22, 26]])->withErrorBars())
            ->addSeries(Series::of('C', [['Q1', 30, 28, 32], ['Q2', 34, 32, 36]]))
            ->render();

        self::assertStringContainsString('class="svgraph-band series-0"', $svg);
        self::assertStringContainsString('class="svgraph-errorbars series-1"', $svg);
        self::assertStringNotContainsString('series-2 svgraph-errorbars', $svg);
        self::assertStringNotContainsString('series-2 svgraph-band', $svg);
    }

    public function test_band_does_not_use_series_hover_class_prefix(): void
    {
        // CSS hover rule keys on `path[class^="series-"]`; the band class
        // must start with `svgraph-band` so hover doesn't brighten the band.
        $svg = Chart::line()
            ->addSeries(
                Series::from([['A', 10, 5, 15], ['B', 12, 9, 15]])->withConfidenceBand(),
            )
            ->render();

        self::assertSame(0, preg_match_all('/<path[^>]+class="series-0 svgraph-band/', $svg));
        self::assertSame(1, preg_match_all('/<path[^>]+class="svgraph-band series-0/', $svg));
    }

    public function test_theme_overlay_tokens_control_stroke_and_opacity(): void
    {
        $theme = Theme::default()->withErrorOverlay(strokeWidth: 2.5, cap: 3.0, bandOpacity: 0.5);
        $svg = Chart::line()
            ->theme($theme)
            ->addSeries(
                Series::from([['A', 10, 5, 15], ['B', 12, 9, 15]])->withConfidenceBand(),
            )
            ->render();

        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-band[^"]*"[^>]+fill-opacity="0\.5"/',
            $svg,
        );

        $svgBars = Chart::line()
            ->theme($theme)
            ->addSeries(
                Series::from([['A', 10, 5, 15], ['B', 12, 9, 15]])->withErrorBars(),
            )
            ->render();

        self::assertMatchesRegularExpression(
            '/<path[^>]+class="svgraph-errorbars[^"]*"[^>]+stroke-width="2\.5"/',
            $svgBars,
        );
    }

    public function test_error_display_preserved_across_with_modifiers(): void
    {
        $series = Series::of('A', [['Q1', 10, 5, 15]])->withErrorBars();
        self::assertSame(ErrorDisplay::Bars, $series->withName('B')->errorDisplay);
        self::assertSame(ErrorDisplay::Bars, $series->withColor('#ff0000')->errorDisplay);
        self::assertSame(ErrorDisplay::Bars, $series->onAxis('right')->errorDisplay);
        self::assertSame(ErrorDisplay::Bars, $series->withTrendLine()->errorDisplay);
    }

    public function test_log_scale_rejects_negative_low(): void
    {
        // Log axis requires strictly positive bounds — a negative `low`
        // pushes the boundsMin below zero and must trigger the existing
        // validation rather than silently misrender.
        $this->expectException(\InvalidArgumentException::class);

        Chart::line()
            ->addSeries(
                Series::from([['A', 100, -10, 200]])->withConfidenceBand(),
            )
            ->logScale()
            ->render();
    }

    public function test_log_scale_band_supplies_log_geometry(): void
    {
        // LogScale produces monotonic outputs across decades — the band
        // shouldn't degenerate to a single y-level just because the input
        // values cross orders of magnitude.
        $svg = Chart::line()
            ->addSeries(
                Series::from([
                    ['A', 10, 5, 20],
                    ['B', 100, 50, 200],
                    ['C', 1000, 500, 2000],
                ])->withConfidenceBand(),
            )
            ->logScale()
            ->render();

        $matched = preg_match(
            '/<path[^>]+class="svgraph-band[^"]*"[^>]+d="([^"]+)"/',
            $svg,
            $m,
        );
        self::assertSame(1, $matched);
        // Sample y-values: LogScale maps log(5)..log(2000) across 0..100, so
        // the path must contain at least three distinct y values.
        preg_match_all('/[ML]\d+(?:\.\d+)?,(\d+(?:\.\d+)?)/', $m[1], $ys);
        self::assertGreaterThanOrEqual(3, count(array_unique($ys[1])));
        // Inner usage referenced for clarity — LogScale is the scale class
        // assertions depend on for log mapping.
        self::assertInstanceOf(LogScale::class, LogScale::log(1.0, 1000.0, 0.0, 100.0));
    }
}

<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Charts\BarChart;
use Noeka\Svgraph\Charts\LineChart;
use Noeka\Svgraph\Charts\PieChart;
use Noeka\Svgraph\Charts\ProgressChart;
use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Theme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ChartConfigurationTest extends TestCase
{
    public function test_factory_methods_accept_null_data(): void
    {
        self::assertInstanceOf(LineChart::class, Chart::line());
        self::assertInstanceOf(LineChart::class, Chart::sparkline());
        self::assertInstanceOf(BarChart::class, Chart::bar());
        self::assertInstanceOf(PieChart::class, Chart::pie());
        self::assertInstanceOf(PieChart::class, Chart::donut());
    }

    public function test_chart_to_string_renders(): void
    {
        $chart = Chart::line([1, 2, 3]);
        self::assertSame($chart->render(), (string) $chart);
    }

    public function test_aspect_ratio_changes_padding_bottom(): void
    {
        $wide = Chart::line([1, 2, 3])->aspect(4.0)->render();
        $square = Chart::line([1, 2, 3])->aspect(1.0)->render();
        self::assertStringContainsString('padding-bottom:25', $wide);
        self::assertStringContainsString('padding-bottom:100', $square);
    }

    public function test_line_stroke_overrides_color_and_width(): void
    {
        $svg = Chart::line([1, 2, 3])->stroke('#ff00ff', 5.0)->render();
        self::assertStringContainsString('stroke="#ff00ff"', $svg);
        self::assertStringContainsString('stroke-width="5"', $svg);
    }

    public function test_line_stroke_color_only_keeps_default_width(): void
    {
        $svg = Chart::line([1, 2, 3])->stroke('#00ff00')->render();
        self::assertStringContainsString('stroke="#00ff00"', $svg);
    }

    public function test_line_stroke_width_setter_independently(): void
    {
        $svg = Chart::line([1, 2, 3])->strokeWidth(7.5)->render();
        self::assertStringContainsString('stroke-width="7.5"', $svg);
    }

    public function test_line_series_alias_of_data(): void
    {
        $a = (new LineChart())->series([5, 10, 15])->render();
        $b = (new LineChart())->data([5, 10, 15])->render();
        // Each instance gets a unique chart ID, so normalise it before comparing.
        $stripId = static fn(string $svg): string => preg_replace('/svgraph-\d+/', 'svgraph-N', $svg) ?? '';
        self::assertSame($stripId($a), $stripId($b));
    }

    public function test_line_constant_values_still_render_path(): void
    {
        $svg = Chart::line([5, 5, 5])->render();
        self::assertStringContainsString('<path', $svg);
    }

    public function test_line_axes_without_labels_omits_label_div(): void
    {
        $svg = Chart::line([1, 2, 3])->axes()->render();
        self::assertSame(2, substr_count($svg, '<line '));
    }

    public function test_line_grid_only_pads_same_as_axes_only(): void
    {
        // Padding for top/right/bottom/left is gated on `showAxes || showGrid`;
        // flipping any of those to `&&` would zero the padding when only one is set.
        // Both renders must place the data path inside the padded plot rect
        // (x in [12, 98], y in [~10.83, ~79.17] given padTop=4, padBottom=14).
        $gridOnly = Chart::line(['A' => 1, 'B' => 2, 'C' => 3])->grid()->render();
        $axesOnly = Chart::line(['A' => 1, 'B' => 2, 'C' => 3])->axes()->render();

        $extractPath = static function (string $svg): string {
            preg_match('/class="series-0" d="([^"]+)"/', $svg, $m);

            return $m[1] ?? '';
        };
        $expectedPath = 'M12,79.1667 L55,45 L98,10.8333';
        self::assertSame($expectedPath, $extractPath($gridOnly));
        self::assertSame($expectedPath, $extractPath($axesOnly));
    }

    public function test_bar_color_setter_applies_uniform_fill(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2, 'C' => 3])->color('#abcdef')->render();
        self::assertSame(3, substr_count($svg, 'fill="#abcdef"'));
    }

    public function test_bar_horizontal_with_axes_emits_value_labels(): void
    {
        $svg = Chart::bar(['A' => 10, 'B' => 20])->horizontal()->axes()->render();
        self::assertStringContainsString('svgraph__labels', $svg);
    }

    public function test_bar_horizontal_with_grid_emits_vertical_lines(): void
    {
        $svg = Chart::bar(['A' => 10, 'B' => 20])->horizontal()->grid()->ticks(3)->render();
        self::assertGreaterThanOrEqual(3, substr_count($svg, '<line '));
    }

    public function test_bar_horizontal_with_rounded_corners(): void
    {
        $svg = Chart::bar(['A' => 5, 'B' => 10])->horizontal()->rounded(1.5)->render();
        self::assertStringContainsString('rx="1.5"', $svg);
    }

    public function test_bar_empty_data_renders_empty_wrapper(): void
    {
        $svg = Chart::bar([])->render();
        self::assertStringContainsString('svgraph--bar', $svg);
        self::assertStringNotContainsString('<rect', $svg);
    }

    public function test_bar_horizontal_empty_data_renders_empty_wrapper(): void
    {
        $svg = (new BarChart())->horizontal()->render();
        self::assertStringContainsString('svgraph--bar', $svg);
        self::assertStringNotContainsString('<rect', $svg);
    }

    public function test_bar_constant_values_still_render(): void
    {
        $svg = Chart::bar(['A' => 7, 'B' => 7])->render();
        self::assertSame(2, substr_count($svg, '<rect '));
    }

    public function test_bar_gap_clamped_to_valid_range(): void
    {
        $tooNarrow = Chart::bar(['A' => 1, 'B' => 2])->gap(-1.0)->render();
        $tooWide = Chart::bar(['A' => 1, 'B' => 2])->gap(2.0)->render();
        self::assertStringContainsString('<rect', $tooNarrow);
        self::assertStringContainsString('<rect', $tooWide);
    }

    public function test_bar_ticks_floor_clamped(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])->grid()->ticks(0)->render();
        self::assertStringContainsString('<line', $svg);
    }

    public function test_horizontal_bar_widths_scale_linearly_from_left_edge(): void
    {
        // For a horizontal bar [10, 20, 30] over the default 100x100 viewport
        // with no axes/grid (padLeft=2, padRight=2 → plot width=96):
        //   widths = 96 * value / 30 → 32, 64, 96.
        // All bars start at x=2 (the left padding edge).
        $svg = Chart::bar([10, 20, 30])->horizontal()->render();
        preg_match_all('/<rect [^>]*x="([^"]+)"[^>]*width="([^"]+)"/', $svg, $m);
        self::assertSame(['2', '2', '2'], $m[1]);
        self::assertSame(['32', '64', '96'], $m[2]);
    }

    public function test_horizontal_grouped_bar_offsets_series_within_each_row(): void
    {
        // Two series, two columns → grouped layout: each slot's bars stack
        // vertically by series index. Each bar height = group height / 2,
        // so series-1's y must equal series-0's y + barHeight.
        $svg = Chart::bar(['A' => 10, 'B' => 20])->horizontal()
            ->addSeries(Series::from([5, 30]))
            ->render();
        preg_match_all('/<rect class="series-([01])"[^>]*y="([^"]+)"[^>]*height="([^"]+)"/', $svg, $m);
        // Expect 4 bars total. Series 0: rows 0/1, series 1: rows 0/1, in series order.
        self::assertCount(4, $m[1]);
        // With labels the plot area is padded; the per-row slot height is
        // therefore the consistent delta between series-0's two y-values.
        $s0Y0 = (float) $m[2][0];
        $s0Y1 = (float) $m[2][1];
        $slotHeight = $s0Y1 - $s0Y0;
        self::assertGreaterThan(0.0, $slotHeight);
        // Series 1's first bar sits immediately below series 0's first bar.
        $barHeight = (float) $m[3][0];
        self::assertEqualsWithDelta($s0Y0 + $barHeight, (float) $m[2][2], 0.0001);
    }

    public function test_horizontal_stacked_bar_anchors_negative_to_left_of_zero(): void
    {
        // Stacked horizontal with one positive and one negative series:
        // series-0 (positive 10) extends from x(0) to x(10);
        // series-1 (negative -5) extends from x(-5) to x(0).
        // Without axes/grid, domain spans [-5, 10], plot is [2, 98].
        $svg = Chart::bar([10])->stacked()->horizontal()
            ->addSeries(Series::from([-5]))
            ->render();
        preg_match_all('/<rect class="series-([01])"[^>]*x="([^"]+)"[^>]*width="([^"]+)"/', $svg, $m);
        self::assertSame(['0', '1'], $m[1]);
        // Negative bar (series-1) sits left of the positive (series-0).
        self::assertLessThan((float) $m[2][0], (float) $m[2][1]);
        // x(positive) = x(negative) + width(negative) — they touch at zero.
        self::assertEqualsWithDelta(
            (float) $m[2][1] + (float) $m[3][1],
            (float) $m[2][0],
            0.0001,
        );
    }

    public function test_line_default_description_empty_uses_no_data_phrase(): void
    {
        $svg = Chart::line([])->render();
        self::assertStringContainsString('Line chart (no data).', $svg);
    }

    public function test_line_default_description_includes_range_and_point_count(): void
    {
        $svg = Chart::line([1, 2, 3])->render();
        self::assertStringContainsString('Line chart with 1 series of 3 points. Range: 1 to 3.', $svg);
    }

    public function test_line_default_description_singular_for_one_point(): void
    {
        $svg = Chart::line([42])->render();
        self::assertStringContainsString('Line chart with 1 series of 1 point. Range: 42 to 42.', $svg);
    }

    public function test_line_trend_description_uses_series_n_fallback(): void
    {
        // When a series has no name and a trend is requested, the
        // description must call it "Series N" with N=index+1.
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::from([4, 5, 6])->withTrendLine(true))
            ->render();
        self::assertStringContainsString('Series 2 trend: slope 1, R² 1', $svg);
    }

    public function test_line_log_scale_error_message_names_axis_and_value(): void
    {
        $chart = Chart::line([-2, 10])->logScale();

        try {
            $chart->render();
            self::fail('Expected InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(
                'Log scale on the left Y axis requires strictly positive data; '
                . 'minimum value seen is -2.',
                $e->getMessage(),
            );
        }
    }

    public function test_bar_legend_uses_series_n_fallback_when_name_missing(): void
    {
        // Multi-series bar where neither series has a name. The legend must
        // fall back to "Series 1", "Series 2" (1-indexed). Mutations to the
        // `$i + 1` increment or the "Series " concat would break this.
        $svg = Chart::bar([1, 2, 3])
            ->addSeries(Series::from([4, 5, 6]))
            ->legend()
            ->render();
        self::assertStringContainsString('Series 1', $svg);
        self::assertStringContainsString('Series 2', $svg);
    }

    public function test_pie_empty_renders_wrapper_only(): void
    {
        $svg = Chart::pie([])->render();
        self::assertStringContainsString('svgraph--pie', $svg);
        self::assertStringNotContainsString('<path', $svg);
    }

    public function test_pie_all_zero_values_renders_wrapper_only(): void
    {
        $svg = Chart::pie(['A' => 0, 'B' => 0])->render();
        self::assertStringContainsString('svgraph--pie', $svg);
        self::assertStringNotContainsString('<path', $svg);
    }

    public function test_pie_single_slice_renders_circle(): void
    {
        $svg = Chart::pie(['Only' => 100])->render();
        self::assertStringContainsString('<circle', $svg);
    }

    public function test_pie_single_slice_tooltip_anchored_at_circle_top(): void
    {
        // Single-slice, no-legend pie: cx=50, cy=50, r=48 in the 100x100 viewport.
        // Tooltip anchor is the top of the circle: left=cx, top=cy-r.
        $svg = Chart::pie(['Only' => 100])->render();
        self::assertStringContainsString('left:50%;top:2%;', $svg);
    }

    public function test_pie_single_slice_with_legend_tooltip_anchor_shifts(): void
    {
        // With a legend, cy=42 and r=38 so the circle top moves to (50, 4).
        $svg = Chart::pie(['Only' => 100])->legend()->render();
        self::assertStringContainsString('left:50%;top:4%;', $svg);
    }

    public function test_pie_single_slice_with_legend(): void
    {
        $svg = Chart::pie(['Only' => 100])->legend()->render();
        self::assertStringContainsString('<circle', $svg);
        self::assertStringContainsString('svgraph__labels', $svg);
        self::assertStringContainsString('Only', $svg);
    }

    public function test_pie_two_equal_slices_tooltips_anchored_at_3_and_9_oclock(): void
    {
        // Default startAngle=0 means the first slice starts at the 12 o'clock
        // ray. Two equal slices of sweep π each mid at 3 o'clock and 9 o'clock.
        // tipRadius = outerRadius * 0.6 = 48 * 0.6 = 28.8.
        $svg = Chart::pie(['A' => 1, 'B' => 1])->render();
        self::assertStringContainsString('left:78.8%;top:50%;', $svg);
        self::assertStringContainsString('left:21.2%;top:50%;', $svg);
    }

    public function test_donut_two_equal_slices_tooltip_radius_is_ring_midpoint(): void
    {
        // For donuts: tipRadius = (outerRadius + innerRadius) / 2.
        // thickness=0.5 → innerR=24, outerR=48 → tipRadius=36.
        // Two equal slices → tooltips at (50±36, 50).
        $svg = Chart::donut(['A' => 1, 'B' => 1])->thickness(0.5)->render();
        self::assertStringContainsString('left:86%;top:50%;', $svg);
        self::assertStringContainsString('left:14%;top:50%;', $svg);
    }

    public function test_pie_start_angle_rotates_tooltip_anchors(): void
    {
        // startAngle=90 (degrees) rotates the chart a quarter-turn clockwise:
        // slice 0 mid moves from 3 o'clock to 6 o'clock, slice 1 from 9 to 12.
        $svg = Chart::pie(['A' => 1, 'B' => 1])->startAngle(90)->render();
        self::assertStringContainsString('left:50%;top:78.8%;', $svg);
        self::assertStringContainsString('left:50%;top:21.2%;', $svg);
    }

    public function test_animated_single_slice_tooltip_matches_static(): void
    {
        // The animated single-slice fast-path uses the same anchor as the
        // static one: top of the circle (50, cy-r). Mutations on the
        // animated arithmetic on lines 272-273 must produce different output.
        $svg = Chart::pie(['Only' => 100])->animate()->render();
        self::assertStringContainsString('left:50%;top:2%;', $svg);
    }

    public function test_pie_legend_with_five_slices_uses_four_columns(): void
    {
        // `min(4, max(1, $count))` caps columns at 4. With 5 slices we get
        // 4 columns + 1 wrapped row. Columns are 25% wide, each label sits
        // at `col * 25 + 1` percent from the left.
        $svg = Chart::pie([
            'A' => 1, 'B' => 1, 'C' => 1, 'D' => 1, 'E' => 1,
        ])->legend()->render();
        // 4-across at top=86, plus 1 wrapped to top=92 at left=1.
        self::assertStringContainsString('left:1%;top:86%;', $svg);
        self::assertStringContainsString('left:26%;top:86%;', $svg);
        self::assertStringContainsString('left:51%;top:86%;', $svg);
        self::assertStringContainsString('left:76%;top:86%;', $svg);
        self::assertStringContainsString('left:1%;top:92%;', $svg);
    }

    public function test_pie_default_description_reports_total_and_count(): void
    {
        $svg = Chart::pie(['A' => 10, 'B' => 20, 'C' => 30])->render();
        self::assertStringContainsString('Pie chart with 3 slices totalling 60.', $svg);
    }

    public function test_pie_default_description_singular_for_one_slice(): void
    {
        $svg = Chart::pie(['A' => 10])->render();
        self::assertStringContainsString('Pie chart with 1 slice totalling 10.', $svg);
    }

    public function test_pie_default_description_no_data_phrase_when_empty(): void
    {
        $svg = Chart::pie([])->render();
        self::assertStringContainsString('Pie chart (no data).', $svg);
    }

    public function test_donut_default_title(): void
    {
        $svg = Chart::donut(['A' => 1])->thickness(0.5)->render();
        self::assertStringContainsString('>Donut chart<', $svg);
    }

    public function test_pie_skips_zero_value_slice(): void
    {
        $svg = Chart::pie(['A' => 50, 'B' => 0, 'C' => 50])->render();
        self::assertSame(2, substr_count($svg, '<path '));
    }

    public function test_pie_huge_gap_collapses_slice(): void
    {
        $svg = Chart::pie(['A' => 1, 'B' => 1, 'C' => 1])->gap(180)->render();
        self::assertStringContainsString('svgraph--pie', $svg);
    }

    public function test_pie_thickness_clamped_to_valid_range(): void
    {
        $svg = Chart::pie(['A' => 1, 'B' => 1])->thickness(2.0)->render();
        self::assertStringContainsString('<path', $svg);
    }

    public function test_pie_legend_wraps_to_multiple_rows_with_many_slices(): void
    {
        $svg = Chart::pie([
            'A' => 1, 'B' => 1, 'C' => 1, 'D' => 1, 'E' => 1, 'F' => 1, 'G' => 1, 'H' => 1,
        ])->legend()->render();
        self::assertStringContainsString('svgraph__labels', $svg);

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $label) {
            self::assertStringContainsString($label, $svg);
        }
    }

    public function test_pie_full_donut_ring_renders(): void
    {
        $svg = Chart::donut(['Only' => 100])->thickness(0.5)->render();
        self::assertStringContainsString('svgraph--donut', $svg);
        self::assertStringContainsString('<path', $svg);
    }

    public function test_progress_setters_change_render(): void
    {
        $base = Chart::progress(50, 100)->render();
        $configured = Chart::progress()
            ->value(75)
            ->target(150)
            ->color('#112233')
            ->trackColor('#445566')
            ->rounded(10)
            ->render();
        self::assertNotSame($base, $configured);
        self::assertStringContainsString('fill="#112233"', $configured);
        self::assertStringContainsString('fill="#445566"', $configured);
    }

    public function test_progress_show_value_with_custom_label(): void
    {
        $svg = Chart::progress(50, 100)->showValue(true, 'half done')->render();
        self::assertStringContainsString('half done', $svg);
        // The default "<percent>%" label should be replaced — no "50%" inside the label span.
        self::assertDoesNotMatchRegularExpression(
            '/svgraph__labels[^<]*<span[^>]*>50%</',
            $svg,
        );
    }

    public function test_progress_default_aspect_can_be_overridden(): void
    {
        $svg = (new ProgressChart(50, 100))->aspect(10.0)->render();
        self::assertStringContainsString('padding-bottom:10', $svg);
    }

    public function test_axis_label_format_thousands(): void
    {
        // Drives AbstractChart::formatNumber's >=1000 branch.
        $svg = Chart::bar(['A' => 1500, 'B' => 2500])->axes()->render();
        self::assertStringContainsString('2,500', $svg);
    }

    public function test_axis_label_format_decimal(): void
    {
        // Drives AbstractChart::formatNumber's decimal branch (with trim).
        $svg = Chart::bar(['A' => 0.25, 'B' => 0.75])->axes()->ticks(4)->render();
        self::assertMatchesRegularExpression('/0\.\d/', $svg);
    }

    #[DataProvider('formatNumberMatrix')]
    public function test_format_number_matrix(float $value, string $expected): void
    {
        $method = new ReflectionMethod(LineChart::class, 'formatNumber');
        self::assertSame($expected, $method->invoke(new LineChart(), $value));
    }

    /**
     * @return list<array{0: float, 1: string}>
     */
    public static function formatNumberMatrix(): array
    {
        return [
            // >=1000 branch: thousand separator, no decimals (rounds half-up).
            [12345.0, '12,345'],
            [1500.7, '1,501'],
            [-12345.0, '-12,345'],
            // Integer-valued floats below the threshold: bare int form.
            [3.0, '3'],
            [0.0, '0'],
            [-7.0, '-7'],
            // Non-integer floats below the threshold: 2 decimals, trailing zeros trimmed.
            [3.5, '3.5'],
            [3.05, '3.05'],
            // Third decimal must round (kills `, 2,` -> `, 3,`).
            [3.456, '3.46'],
            // Rounds down to zero — exercises the dangling-dot trim ("0." -> "0").
            [0.001, '0'],
            [-3.5, '-3.5'],
        ];
    }

    public function test_theme_with_invalid_text_color_falls_back(): void
    {
        // Drives Css::color null fallback in Wrapper::render().
        $invalidTheme = new Theme(
            palette: ['#3b82f6'],
            stroke: '#3b82f6',
            strokeWidth: 2.0,
            fill: '#3b82f6',
            textColor: 'red;background:url(x)',
            fontFamily: 'inherit',
            fontSize: '0.75rem',
            gridColor: '#e5e7eb',
            axisColor: '#9ca3af',
            trackColor: '#e5e7eb',
        );
        $svg = Chart::line([['A', 1], ['B', 2]])->axes()->theme($invalidTheme)->render();
        self::assertStringContainsString('color:currentColor', $svg);
    }

    public function test_theme_with_invalid_font_family_falls_back(): void
    {
        $invalidTheme = new Theme(
            palette: ['#3b82f6'],
            stroke: '#3b82f6',
            strokeWidth: 2.0,
            fill: '#3b82f6',
            textColor: '#000',
            fontFamily: 'Arial;background:red',
            fontSize: 'calc(100% - 1px)',
            gridColor: '#e5e7eb',
            axisColor: '#9ca3af',
            trackColor: '#e5e7eb',
        );
        $svg = Chart::line([['A', 1], ['B', 2]])->axes()->theme($invalidTheme)->render();
        self::assertStringContainsString('font-family:inherit', $svg);
        self::assertStringContainsString('font-size:0.75rem', $svg);
    }

    public function test_theme_with_empty_palette_uses_stroke_color(): void
    {
        $theme = Theme::default()->withPalette();
        self::assertSame($theme->stroke, $theme->colorAt(0));
        self::assertSame($theme->stroke, $theme->colorAt(99));
    }

    public function test_theme_color_at_wraps_around(): void
    {
        $theme = Theme::default()->withPalette('#aaa', '#bbb');
        self::assertSame('#aaa', $theme->colorAt(0));
        self::assertSame('#bbb', $theme->colorAt(1));
        self::assertSame('#aaa', $theme->colorAt(2));
        self::assertSame('#bbb', $theme->colorAt(3));
    }

    public function test_theme_tooltip_defaults(): void
    {
        $theme = Theme::default();
        self::assertSame('#1f2937', $theme->tooltipBackground);
        self::assertSame('#f9fafb', $theme->tooltipTextColor);
        self::assertSame('0.25rem', $theme->tooltipBorderRadius);
    }

    public function test_theme_with_tooltip_overrides_values(): void
    {
        $theme = Theme::default()->withTooltip('#000000', '#ffffff', '4px');
        self::assertSame('#000000', $theme->tooltipBackground);
        self::assertSame('#ffffff', $theme->tooltipTextColor);
        self::assertSame('4px', $theme->tooltipBorderRadius);
        // Other properties are unchanged.
        self::assertSame(Theme::default()->textColor, $theme->textColor);
    }

    public function test_with_palette_preserves_tooltip_properties(): void
    {
        $theme = Theme::default()
            ->withTooltip('#aabbcc', '#112233', '8px')
            ->withPalette('#ff0000');
        self::assertSame('#aabbcc', $theme->tooltipBackground);
        self::assertSame('#112233', $theme->tooltipTextColor);
        self::assertSame('8px', $theme->tooltipBorderRadius);
    }

    public function test_theme_with_invalid_tooltip_colors_fall_back_in_css(): void
    {
        $theme = Theme::default()->withTooltip(
            'red;background:url(x)',
            'blue;color:evil',
            'calc(1+1)',
        );
        $svg = Chart::bar(['A' => 1])->theme($theme)->render();
        // Css::color rejects injections; fallback defaults must be used.
        self::assertStringContainsString('--svgraph-tt-bg:#1f2937', $svg);
        self::assertStringContainsString('--svgraph-tt-fg:#f9fafb', $svg);
        self::assertStringContainsString('--svgraph-tt-r:0.25rem', $svg);
    }
}

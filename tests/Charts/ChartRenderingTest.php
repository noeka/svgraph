<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Link;
use Noeka\Svgraph\Data\Point;
use Noeka\Svgraph\Data\Slice;
use Noeka\Svgraph\Theme;
use PHPUnit\Framework\TestCase;

final class ChartRenderingTest extends TestCase
{
    public function test_line_chart_renders_path_and_wrapper(): void
    {
        $svg = Chart::line([10, 24, 18, 35])->render();

        self::assertStringContainsString('<div class="svgraph svgraph--line"', $svg);
        self::assertStringContainsString('preserveAspectRatio="none"', $svg);
        self::assertStringContainsString('vector-effect="non-scaling-stroke"', $svg);
        self::assertStringContainsString('<path', $svg);
        self::assertStringContainsString('padding-bottom:', $svg);
    }

    public function test_line_smooth_uses_cubic_beziers(): void
    {
        $straight = Chart::line([10, 50, 30, 70])->render();
        $smooth = Chart::line([10, 50, 30, 70])->smooth()->render();

        self::assertDoesNotMatchRegularExpression('/d="M[^"]*C/', $straight);
        self::assertMatchesRegularExpression('/d="M[^"]*C/', $smooth);
    }

    public function test_line_fill_below_emits_area_path(): void
    {
        $svg = Chart::line([10, 50, 30, 70])->fillBelow('#8b5cf6', 0.25)->render();

        self::assertSame(2, substr_count($svg, '<path '));
        self::assertStringContainsString('fill-opacity="0.25"', $svg);
        self::assertStringContainsString('fill="#8b5cf6"', $svg);
    }

    public function test_line_points_emit_ellipse_markers(): void
    {
        $svg = Chart::line([10, 50, 30, 70])->points()->render();

        // Each point emits a visible ellipse marker plus a larger transparent hit target.
        self::assertSame(8, substr_count($svg, '<ellipse '));
    }

    public function test_line_axes_emit_axis_lines_and_tick_labels(): void
    {
        $svg = Chart::line([['Mon', 10], ['Tue', 24]])->axes()->render();

        self::assertSame(2, substr_count($svg, '<line '));
        self::assertStringContainsString('svgraph__labels', $svg);
        self::assertStringContainsString('Mon', $svg);
        self::assertStringContainsString('Tue', $svg);
    }

    public function test_line_grid_emits_horizontal_lines(): void
    {
        $svg = Chart::line([10, 50, 30, 70])->grid()->ticks(4)->render();

        self::assertGreaterThanOrEqual(2, substr_count($svg, '<line '));
    }

    public function test_sparkline_renders_with_fill(): void
    {
        $svg = Chart::sparkline([10, 12, 8, 18])->render();

        self::assertStringContainsString('svgraph--sparkline', $svg);
        self::assertStringContainsString('fill-opacity="0.15"', $svg);
    }

    public function test_sparkline_axes_opt_in_renders_axis_lines(): void
    {
        // Regression: previously SparklineChart::render() reset showAxes=false,
        // silently dropping any ->axes() call.
        $svg = Chart::sparkline([['Mon', 10], ['Tue', 24], ['Wed', 18]])->axes()->render();

        self::assertSame(2, substr_count($svg, '<line '));
        self::assertStringContainsString('svgraph__labels', $svg);
    }

    public function test_bar_chart_renders_rects(): void
    {
        $svg = Chart::bar(['Jan' => 10, 'Feb' => 20, 'Mar' => 5])->render();

        self::assertStringContainsString('svgraph--bar', $svg);
        self::assertStringContainsString('<rect', $svg);
        self::assertSame(3, substr_count($svg, '<rect '));
    }

    public function test_bar_rounded_emits_path_with_aspect_corrected_arc(): void
    {
        // Rounded bars switch from <rect> to <path> so we can selectively
        // round just the top edge. The arc's `ry` is scaled by the chart's
        // aspect ratio (default 2.0) so the corner renders as a circle —
        // not an ellipse — after `preserveAspectRatio="none"` stretching.
        $svg = Chart::bar(['Jan' => 10, 'Feb' => 20])->rounded(2.5)->render();

        self::assertStringNotContainsString('<rect class="series-0"', $svg);
        self::assertMatchesRegularExpression('/<path class="series-0"[^>]* d="[^"]*A2\.5,5 /', $svg);
        // Top-only rounding: path starts at the bottom-left, lifts to the
        // top-left arc, traverses the top, drops back down the right side.
        // There must be exactly two arc commands (the two top corners).
        preg_match_all('/<path class="series-0"[^>]* d="([^"]+)"/', $svg, $m);
        self::assertCount(2, $m[1]);
        self::assertSame(2, substr_count($m[1][0], 'A'));
    }

    public function test_bar_rainbow_uses_palette(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 1, 'C' => 1])->rainbow()->render();

        // Default palette starts with #3b82f6, #10b981, #f59e0b.
        self::assertStringContainsString('fill="#3b82f6"', $svg);
        self::assertStringContainsString('fill="#10b981"', $svg);
        self::assertStringContainsString('fill="#f59e0b"', $svg);
    }

    public function test_bar_handles_negative_values(): void
    {
        // Baseline at zero: positive bars hang from baseline downward,
        // negative bars extend upward (i.e. y > baseline). Renders without throwing.
        $svg = Chart::bar(['A' => 5, 'B' => -3, 'C' => 7])->render();

        self::assertSame(3, substr_count($svg, '<rect '));
    }

    public function test_bar_rounded_negative_value_rounds_bottom_edge(): void
    {
        // Negative bars hang below the baseline; their open end is the
        // bottom, so the bottom corners get rounded instead of the top.
        $svg = Chart::bar(['A' => 5, 'B' => -3])->rounded(1)->render();

        // Both bars render as paths (both have non-zero values).
        preg_match_all('/<path class="series-0"[^>]* d="([^"]+)"/', $svg, $paths);
        self::assertCount(2, $paths[1], 'expected both bars to be paths');

        // The positive bar's path opens at the bottom-left corner (sharp);
        // the negative bar's path opens at the top-left corner (sharp).
        // Distinguish by where the arcs sit: positive bar's arcs are near
        // the smaller y values, negative bar's arcs are near the larger.
        preg_match_all('/A1,2 0 0 1 ([\d.]+),([\d.]+)/', $paths[1][0], $posArcs);
        preg_match_all('/A1,2 0 0 1 ([\d.]+),([\d.]+)/', $paths[1][1], $negArcs);
        self::assertCount(2, $posArcs[2]);
        self::assertCount(2, $negArcs[2]);
        // Mean Y of arc endpoints: positive bar's arcs sit above (lower y)
        // its negative neighbour's arcs (higher y).
        $posMean = (((float) $posArcs[2][0]) + ((float) $posArcs[2][1])) / 2;
        $negMean = (((float) $negArcs[2][0]) + ((float) $negArcs[2][1])) / 2;
        self::assertLessThan($negMean, $posMean);
    }

    public function test_bar_stacked_rounds_only_outermost_segment(): void
    {
        // Stacked bars: only the topmost positive segment in each slot
        // gets a rounded top. Middle segments stay flat (rendered as
        // <rect>) so the joints between stacked segments are seamless.
        $svg = Chart::bar(['Q1' => 10])
            ->addSeries(Series::of('mid', ['Q1' => 5]))
            ->addSeries(Series::of('top', ['Q1' => 3]))
            ->stacked()
            ->rounded(1)
            ->render();

        // Bottom (series-0) and middle (series-1) segments are flat rects;
        // only the topmost segment (series-2) is a rounded path.
        self::assertStringContainsString('<rect class="series-0"', $svg);
        self::assertStringContainsString('<rect class="series-1"', $svg);
        self::assertStringContainsString('<path class="series-2"', $svg);
        self::assertStringNotContainsString('<path class="series-0"', $svg);
        self::assertStringNotContainsString('<path class="series-1"', $svg);
    }

    public function test_horizontal_bar_chart_emits_labels(): void
    {
        $svg = Chart::bar(['Stripe' => 1240, 'PayPal' => 432, 'Manual' => 89])
            ->horizontal()
            ->render();

        self::assertStringContainsString('svgraph__labels', $svg);
        self::assertStringContainsString('Stripe', $svg);
    }

    public function test_pie_chart_renders_paths_per_slice(): void
    {
        $svg = Chart::pie(['A' => 50, 'B' => 30, 'C' => 20])->render();

        self::assertStringContainsString('svgraph--pie', $svg);
        self::assertSame(3, substr_count($svg, '<path '));
    }

    public function test_pie_start_angle_rotates_slices(): void
    {
        $a = Chart::pie(['A' => 1, 'B' => 1])->render();
        $b = Chart::pie(['A' => 1, 'B' => 1])->startAngle(90)->render();

        self::assertNotSame($a, $b);
    }

    public function test_pie_gap_separates_slices(): void
    {
        $tight = Chart::pie(['A' => 1, 'B' => 1, 'C' => 1])->render();
        $padded = Chart::pie(['A' => 1, 'B' => 1, 'C' => 1])->gap(5)->render();

        self::assertNotSame($tight, $padded);
        self::assertSame(3, substr_count($padded, '<path '));
    }

    public function test_donut_chart_uses_donut_variant(): void
    {
        $svg = Chart::donut(['A' => 1, 'B' => 1])->thickness(0.5)->render();

        self::assertStringContainsString('svgraph--donut', $svg);
    }

    public function test_pie_with_legend_renders_labels(): void
    {
        $svg = Chart::pie(['Stripe' => 100, 'PayPal' => 50])->legend()->render();

        self::assertStringContainsString('svgraph__labels', $svg);
        // Anchor labels inside the legend container so a future change that
        // moves them elsewhere doesn't pass trivially.
        self::assertMatchesRegularExpression(
            '/svgraph__labels.*Stripe.*PayPal/s',
            $svg,
        );
    }

    public function test_pie_legend_color_with_css_injection_is_sanitized(): void
    {
        // Regression: Slice colors flow into the legend swatch's `style` attribute,
        // which is parsed as CSS. Tag::escapeAttr alone doesn't prevent injecting
        // extra CSS rules (`;`, `:`, parens aren't HTML-special), so the swatch
        // background must be allowlist-sanitized via Css::color.
        $svg = Chart::pie([
            ['Stripe', 100, 'red;background:url(http://evil.example/x)'],
        ])->legend()->render();

        // Assert the swatch span style is clean and contains the safe fallback.
        // (The malicious string still appears inside an SVG `fill=` attribute,
        // which is harmless: escapeAttr prevents attribute breakout and SVG
        // ignores invalid paint values.)
        self::assertMatchesRegularExpression(
            '/<span style="display:inline-block[^"]*background:currentColor[^"]*"/',
            $svg,
        );
        self::assertDoesNotMatchRegularExpression(
            '/<span style="display:inline-block[^"]*url\(/',
            $svg,
        );
    }

    public function test_progress_clamps_to_target(): void
    {
        $svg = Chart::progress(150, 100)->render();

        self::assertStringContainsString('svgraph--progress', $svg);
        self::assertSame(2, substr_count($svg, '<rect '));
        // Both rects must be width="100": the track (full) and the clamped fill.
        // A naive (un-clamped) implementation would emit width="150" for the fill.
        self::assertStringNotContainsString('width="150"', $svg);
        self::assertSame(2, substr_count($svg, 'width="100"'));
    }

    public function test_progress_zero_target_emits_only_track(): void
    {
        $svg = Chart::progress(50, 0)->render();

        self::assertStringContainsString('svgraph--progress', $svg);
        // Only the track rect; no fill rect when fraction is zero.
        self::assertSame(1, substr_count($svg, '<rect '));
    }

    public function test_progress_show_value_emits_percentage_label(): void
    {
        $svg = Chart::progress(75, 100)->showValue()->render();

        self::assertStringContainsString('svgraph__labels', $svg);
        self::assertStringContainsString('75%', $svg);
    }

    public function test_theme_dark_changes_label_text_color(): void
    {
        $svg = Chart::line([['Mon', 10], ['Tue', 24]])
            ->axes()
            ->theme(Theme::dark())
            ->render();

        // Dark theme textColor is #e5e7eb; default theme is #374151.
        self::assertStringContainsString('#e5e7eb', $svg);
    }

    public function test_theme_with_palette_overrides_rainbow_bars(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 1])
            ->rainbow()
            ->theme(Theme::default()->withPalette('#123456', '#abcdef'))
            ->render();

        self::assertStringContainsString('fill="#123456"', $svg);
        self::assertStringContainsString('fill="#abcdef"', $svg);
    }

    public function test_user_class_appears_on_wrapper(): void
    {
        $svg = Chart::line([1, 2, 3])->cssClass('my-trend')->render();
        self::assertStringContainsString('svgraph svgraph--line my-trend', $svg);
    }

    public function test_output_contains_no_script_or_event_handlers(): void
    {
        $svg = Chart::line([
            ['<script>alert(1)</script>', 10],
            ['" onmouseover="x', 20],
        ])->axes()->render();

        self::assertStringNotContainsString('<script', $svg);
        self::assertSame(0, preg_match('/<[a-zA-Z][^>]*\son[a-z]+\s*=/i', $svg));
        self::assertStringContainsString('&lt;script&gt;', $svg);
    }

    public function test_empty_data_still_returns_wrapper(): void
    {
        $svg = Chart::line([])->render();
        self::assertStringContainsString('svgraph--line', $svg);
        self::assertStringNotContainsString('<path', $svg);
    }

    public function test_bar_rect_has_title_tooltip_with_label_and_value(): void
    {
        $svg = Chart::bar(['Jan' => 10, 'Feb' => 2500])->render();

        self::assertStringContainsString('<title>Jan: 10</title>', $svg);
        self::assertStringContainsString('<title>Feb: 2,500</title>', $svg);
    }

    public function test_bar_rect_without_label_shows_value_only(): void
    {
        $svg = Chart::bar([42, 7])->render();

        self::assertStringContainsString('<title>42</title>', $svg);
        self::assertStringContainsString('<title>7</title>', $svg);
    }

    public function test_horizontal_bar_has_title_tooltip(): void
    {
        $svg = Chart::bar(['Stripe' => 1240, 'PayPal' => 432])->horizontal()->render();

        self::assertStringContainsString('<title>Stripe: 1,240</title>', $svg);
        self::assertStringContainsString('<title>PayPal: 432</title>', $svg);
    }

    public function test_pie_slice_has_title_tooltip(): void
    {
        $svg = Chart::pie(['Alpha' => 50, 'Beta' => 30, 'Gamma' => 20])->render();

        self::assertStringContainsString('<title>Alpha: 50</title>', $svg);
        self::assertStringContainsString('<title>Beta: 30</title>', $svg);
        self::assertStringContainsString('<title>Gamma: 20</title>', $svg);
    }

    public function test_pie_single_slice_circle_has_title_tooltip(): void
    {
        $svg = Chart::pie(['Only' => 100])->render();

        self::assertStringContainsString('<circle ', $svg);
        self::assertStringContainsString('<title>Only: 100</title>', $svg);
    }

    public function test_donut_slice_has_title_tooltip(): void
    {
        $svg = Chart::donut(['A' => 3, 'B' => 1])->render();

        self::assertStringContainsString('<title>A: 3</title>', $svg);
        self::assertStringContainsString('<title>B: 1</title>', $svg);
    }

    public function test_line_point_has_title_tooltip(): void
    {
        $svg = Chart::line([['Mon', 10], ['Tue', 24], ['Wed', 18]])->points()->render();

        self::assertStringContainsString('<title>Mon: 10</title>', $svg);
        self::assertStringContainsString('<title>Tue: 24</title>', $svg);
        self::assertStringContainsString('<title>Wed: 18</title>', $svg);
    }

    public function test_line_point_without_label_shows_value_only(): void
    {
        $svg = Chart::line([5, 15, 10])->points()->render();

        self::assertStringContainsString('<title>5</title>', $svg);
        self::assertStringContainsString('<title>15</title>', $svg);
        self::assertStringContainsString('<title>10</title>', $svg);
    }

    public function test_progress_fill_has_title_tooltip(): void
    {
        $svg = Chart::progress(75, 100)->render();

        self::assertStringContainsString('<title>75 / 100</title>', $svg);
    }

    public function test_tooltip_text_is_html_escaped(): void
    {
        $svg = Chart::bar(['<b>Foo</b>' => 10])->render();

        self::assertStringContainsString('<title>&lt;b&gt;Foo&lt;/b&gt;: 10</title>', $svg);
        self::assertStringNotContainsString('<title><b>', $svg);
    }

    // ── CSS-hover tooltip tests (issue #5) ────────────────────────────────────

    public function test_bar_chart_emits_css_tooltip_divs(): void
    {
        $svg = Chart::bar(['Jan' => 10, 'Feb' => 20])->render();

        self::assertStringContainsString('class="svgraph-tooltip"', $svg);
        self::assertSame(2, substr_count($svg, 'class="svgraph-tooltip"'));
        self::assertStringContainsString('data-for=', $svg);
    }

    public function test_horizontal_bar_emits_css_tooltip_divs(): void
    {
        $svg = Chart::bar(['A' => 5, 'B' => 10])->horizontal()->render();

        self::assertSame(2, substr_count($svg, 'class="svgraph-tooltip"'));
    }

    public function test_line_points_emit_css_tooltip_divs(): void
    {
        $svg = Chart::line([10, 20, 30])->points()->render();

        self::assertSame(3, substr_count($svg, 'class="svgraph-tooltip"'));
    }

    public function test_line_without_points_emits_no_css_tooltips(): void
    {
        $svg = Chart::line([10, 20, 30])->render();

        self::assertStringNotContainsString('class="svgraph-tooltip"', $svg);
    }

    public function test_pie_emits_css_tooltip_divs(): void
    {
        $svg = Chart::pie(['A' => 50, 'B' => 30, 'C' => 20])->render();

        self::assertSame(3, substr_count($svg, 'class="svgraph-tooltip"'));
    }

    public function test_pie_single_slice_emits_css_tooltip_div(): void
    {
        $svg = Chart::pie(['Only' => 100])->render();

        self::assertSame(1, substr_count($svg, 'class="svgraph-tooltip"'));
    }

    public function test_donut_emits_css_tooltip_divs(): void
    {
        $svg = Chart::donut(['A' => 3, 'B' => 1])->render();

        self::assertSame(2, substr_count($svg, 'class="svgraph-tooltip"'));
    }

    public function test_progress_fill_emits_css_tooltip_div(): void
    {
        $svg = Chart::progress(75, 100)->render();

        self::assertSame(1, substr_count($svg, 'class="svgraph-tooltip"'));
    }

    public function test_progress_zero_fraction_emits_no_css_tooltip(): void
    {
        $svg = Chart::progress(0, 100)->render();

        self::assertStringNotContainsString('class="svgraph-tooltip"', $svg);
    }

    public function test_css_tooltip_style_block_uses_at_supports_has(): void
    {
        $svg = Chart::bar(['A' => 1])->render();

        self::assertStringContainsString('@supports selector(:has(a))', $svg);
        self::assertStringContainsString(':hover', $svg);
        self::assertStringContainsString(':focus-visible', $svg);
    }

    public function test_tooltip_data_for_matches_svg_element_id(): void
    {
        $svg = Chart::bar(['X' => 5])->render();

        // Element IDs follow the format svgraph-{n}-s{j}-pt-{i}.
        self::assertMatchesRegularExpression('/id="svgraph-\d+-s0-pt-0"/', $svg);
        self::assertMatchesRegularExpression('/data-for="svgraph-\d+-s0-pt-0"/', $svg);
        self::assertMatchesRegularExpression('/#svgraph-\d+-s0-pt-0:hover/', $svg);
    }

    public function test_data_elements_have_tabindex(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])->render();

        self::assertSame(2, substr_count($svg, 'tabindex="0"'));
    }

    public function test_line_points_hit_targets_have_tabindex(): void
    {
        $svg = Chart::line([5, 10, 15])->points()->render();

        self::assertSame(3, substr_count($svg, 'tabindex="0"'));
    }

    public function test_pie_slices_have_tabindex(): void
    {
        $svg = Chart::pie(['A' => 1, 'B' => 1])->render();

        self::assertSame(2, substr_count($svg, 'tabindex="0"'));
    }

    public function test_css_tooltip_div_contains_escaped_text(): void
    {
        $svg = Chart::bar(['<b>Q1</b>' => 100])->render();

        // The CSS tooltip div must contain escaped HTML, not raw tags.
        self::assertMatchesRegularExpression(
            '/class="svgraph-tooltip"[^>]*>[^<]*&lt;b&gt;Q1&lt;\/b&gt;/',
            $svg,
        );
        self::assertDoesNotMatchRegularExpression(
            '/class="svgraph-tooltip"[^>]*><b>/',
            $svg,
        );
    }

    public function test_wrapper_emits_css_custom_properties_for_tooltip_theme(): void
    {
        $svg = Chart::bar(['A' => 1])
            ->theme(Theme::default()->withTooltip('#112233', '#eeffaa', '0.5rem'))
            ->render();

        self::assertStringContainsString('--svgraph-tt-bg:#112233', $svg);
        self::assertStringContainsString('--svgraph-tt-fg:#eeffaa', $svg);
        self::assertStringContainsString('--svgraph-tt-r:0.5rem', $svg);
    }

    public function test_css_tooltip_style_uses_var_custom_properties(): void
    {
        $svg = Chart::bar(['A' => 1])->render();

        self::assertStringContainsString('var(--svgraph-tt-bg', $svg);
        self::assertStringContainsString('var(--svgraph-tt-fg', $svg);
        self::assertStringContainsString('var(--svgraph-tt-r', $svg);
    }

    public function test_empty_bar_chart_emits_no_css_tooltips(): void
    {
        $svg = Chart::bar([])->render();

        self::assertStringNotContainsString('class="svgraph-tooltip"', $svg);
        self::assertStringNotContainsString('<style>', $svg);
    }

    // ── Hover/focus highlight tests (issue #6) ────────────────────────────────

    public function test_bar_rects_have_series_class(): void
    {
        $svg = Chart::bar(['Jan' => 10, 'Feb' => 20, 'Mar' => 5])->render();

        // All bars in a single-colour chart belong to series-0.
        self::assertSame(3, substr_count($svg, 'class="series-0"'));
    }

    public function test_rainbow_bars_get_per_bar_series_class(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2, 'C' => 3])->rainbow()->render();

        self::assertStringContainsString('class="series-0"', $svg);
        self::assertStringContainsString('class="series-1"', $svg);
        self::assertStringContainsString('class="series-2"', $svg);
    }

    public function test_pie_slices_have_per_slice_series_class(): void
    {
        $svg = Chart::pie(['A' => 50, 'B' => 30, 'C' => 20])->render();

        self::assertStringContainsString('class="series-0"', $svg);
        self::assertStringContainsString('class="series-1"', $svg);
        self::assertStringContainsString('class="series-2"', $svg);
    }

    public function test_pie_single_slice_has_series_0_class(): void
    {
        $svg = Chart::pie(['Only' => 100])->render();

        self::assertStringContainsString('class="series-0"', $svg);
    }

    public function test_line_marker_groups_have_series_class(): void
    {
        $svg = Chart::line([10, 20, 30])->points()->render();

        // 1 line path + 3 marker groups, all carrying series-0.
        self::assertSame(4, substr_count($svg, 'class="series-0"'));
        // Ellipses must be nested inside <g> elements.
        self::assertMatchesRegularExpression('/<g class="series-0">.*<ellipse/s', $svg);
    }

    public function test_bar_chart_emits_hover_style_rules(): void
    {
        $svg = Chart::bar(['A' => 1])->render();

        self::assertStringContainsString('brightness(var(--svgraph-hover-brightness', $svg);
        self::assertStringContainsString('--svgraph-hover-stroke-width', $svg);
    }

    public function test_hover_css_custom_properties_on_wrapper(): void
    {
        $svg = Chart::bar(['A' => 1])->render();

        self::assertStringContainsString('--svgraph-hover-brightness:', $svg);
        self::assertStringContainsString('--svgraph-hover-stroke-width:', $svg);
    }

    public function test_with_hover_overrides_custom_properties(): void
    {
        $svg = Chart::bar(['A' => 1])
            ->theme(Theme::default()->withHover('1.5', '2', '5px'))
            ->render();

        self::assertStringContainsString('--svgraph-hover-brightness:1.5', $svg);
        self::assertStringContainsString('--svgraph-hover-stroke-width:2', $svg);
        self::assertStringContainsString('--svgraph-pie-pop-distance:5px', $svg); // custom property still emitted on wrapper
    }

    public function test_line_without_points_emits_no_hover_style(): void
    {
        $svg = Chart::line([10, 20, 30])->render();

        // No series elements → no hover/focus rules in the <style> block.
        // (The block itself still exists to carry the sr-only data table CSS.)
        self::assertStringNotContainsString('hover-brightness', $svg);
        self::assertStringNotContainsString(':hover', $svg);
        self::assertStringNotContainsString(':focus-visible', $svg);
    }

    public function test_horizontal_bar_rects_have_series_class(): void
    {
        $svg = Chart::bar(['A' => 5, 'B' => 10])->horizontal()->render();

        self::assertSame(2, substr_count($svg, 'class="series-0"'));
    }

    public function test_donut_slices_have_series_class(): void
    {
        $svg = Chart::donut(['X' => 3, 'Y' => 1])->render();

        self::assertStringContainsString('class="series-0"', $svg);
        self::assertStringContainsString('class="series-1"', $svg);
    }

    // ── Click-through link tests (issue #7) ──────────────────────────────────

    public function test_bar_linked_point_wraps_rect_in_anchor(): void
    {
        $svg = Chart::bar([
            new Point(10, 'Jan', new Link('https://example.com/jan')),
            new Point(20, 'Feb'),
        ])->render();

        self::assertMatchesRegularExpression(
            '/<a[^>]+href="https:\/\/example\.com\/jan"[^>]*>.*?<rect[^>]+class="series-0"/',
            $svg,
        );
        self::assertStringContainsString('class="svgraph-linked"', $svg);
        // Non-linked bar must NOT be wrapped in <a>.
        self::assertSame(1, substr_count($svg, '<a '));
    }

    public function test_bar_linked_anchor_carries_id_and_non_linked_rect_carries_id(): void
    {
        $svg = Chart::bar([
            new Point(10, 'Jan', new Link('https://example.com')),
            new Point(20, 'Feb'),
        ])->render();

        // Linked: ID on <a>, no tabindex on rect.
        self::assertMatchesRegularExpression('/<a[^>]+id="svgraph-\d+-s0-pt-0"/', $svg);
        // Non-linked: ID on rect itself.
        self::assertMatchesRegularExpression('/<rect[^>]+id="svgraph-\d+-s0-pt-1"/', $svg);
    }

    public function test_bar_linked_anchor_with_blank_target_gets_noopener_rel(): void
    {
        $svg = Chart::bar([
            new Point(5, 'X', new Link('https://example.com', '_blank')),
        ])->render();

        self::assertStringContainsString('target="_blank"', $svg);
        self::assertStringContainsString('rel="noopener noreferrer"', $svg);
    }

    public function test_bar_linked_anchor_explicit_rel_is_honoured(): void
    {
        $svg = Chart::bar([
            new Point(5, 'X', new Link('https://example.com', '_self', 'noopener')),
        ])->render();

        self::assertStringContainsString('rel="noopener"', $svg);
    }

    public function test_bar_url_is_xml_escaped_in_href(): void
    {
        $svg = Chart::bar([
            new Point(5, 'X', new Link('https://example.com/q?a=1&b=2')),
        ])->render();

        self::assertStringContainsString('href="https://example.com/q?a=1&amp;b=2"', $svg);
    }

    public function test_pie_linked_slice_wraps_path_in_anchor(): void
    {
        $svg = Chart::pie([
            new Slice('Alpha', 50, null, new Link('https://example.com/alpha')),
            new Slice('Beta', 50),
        ])->render();

        self::assertMatchesRegularExpression(
            '/<a[^>]+href="https:\/\/example\.com\/alpha"[^>]*>.*?<path[^>]+class="series-0"/',
            $svg,
        );
        self::assertSame(1, substr_count($svg, '<a '));
    }

    public function test_pie_slice_from_tuple_with_link_wraps_in_anchor(): void
    {
        $link = new Link('https://example.com/stripe');
        $svg = Chart::pie([
            ['Stripe', 80, null, $link],
            ['PayPal', 20],
        ])->render();

        self::assertStringContainsString('href="https://example.com/stripe"', $svg);
        self::assertSame(1, substr_count($svg, '<a '));
    }

    public function test_line_linked_point_wraps_group_in_anchor(): void
    {
        $svg = Chart::line([
            new Point(10, 'Mon', new Link('https://example.com/mon')),
            new Point(20, 'Tue'),
        ])->points()->render();

        self::assertMatchesRegularExpression(
            '/<a[^>]+href="https:\/\/example\.com\/mon"[^>]*>.*?<g[^>]+class="series-0"/',
            $svg,
        );
        self::assertSame(1, substr_count($svg, '<a '));
    }

    public function test_linked_point_from_series_tuple(): void
    {
        $link = new Link('https://example.com/mon');
        $svg = Chart::bar([
            ['Mon', 10, $link],
            ['Tue', 20],
        ])->render();

        self::assertStringContainsString('href="https://example.com/mon"', $svg);
        self::assertSame(1, substr_count($svg, '<a '));
    }

    public function test_linked_elements_emit_cursor_pointer_css(): void
    {
        $svg = Chart::bar([
            new Point(5, 'X', new Link('https://example.com')),
        ])->render();

        self::assertStringContainsString('a.svgraph-linked{cursor:pointer;}', $svg);
    }

    public function test_linked_elements_emit_focus_visible_css(): void
    {
        $svg = Chart::bar([
            new Point(5, 'X', new Link('https://example.com')),
        ])->render();

        self::assertStringContainsString('a.svgraph-linked:focus-visible', $svg);
    }

    // ── Crosshair / focus-column tests (issue #9) ────────────────────────────

    public function test_line_without_crosshair_emits_no_hit_columns(): void
    {
        $svg = Chart::line([10, 20, 30])->render();

        self::assertStringNotContainsString('svgraph-x-hit', $svg);
        self::assertStringNotContainsString('svgraph-crosshair', $svg);
    }

    public function test_line_crosshair_emits_one_hit_rect_per_column(): void
    {
        $svg = Chart::line([10, 20, 30, 40])->crosshair()->render();

        self::assertSame(4, substr_count($svg, 'class="svgraph-x-hit"'));
    }

    public function test_line_crosshair_emits_one_guide_line_per_column(): void
    {
        $svg = Chart::line([10, 20, 30, 40])->crosshair()->render();

        self::assertSame(4, substr_count($svg, 'class="svgraph-crosshair"'));
    }

    public function test_line_crosshair_hit_rects_carry_data_x(): void
    {
        $svg = Chart::line([10, 20, 30])->crosshair()->render();

        self::assertMatchesRegularExpression('/<rect [^>]*class="svgraph-x-hit"[^>]*data-x="0"/', $svg);
        self::assertMatchesRegularExpression('/<rect [^>]*class="svgraph-x-hit"[^>]*data-x="1"/', $svg);
        self::assertMatchesRegularExpression('/<rect [^>]*class="svgraph-x-hit"[^>]*data-x="2"/', $svg);
    }

    public function test_line_crosshair_marker_groups_carry_data_x(): void
    {
        $svg = Chart::line([10, 20, 30])->crosshair()->render();

        // Three marker groups (one per column) and tooltips, each tagged with its x-index.
        self::assertSame(3, preg_match_all('/<g [^>]*data-x="\d+"/', $svg));
    }

    public function test_line_crosshair_tooltips_carry_data_x(): void
    {
        $svg = Chart::line([['Mon', 1], ['Tue', 2]])->crosshair()->render();

        self::assertMatchesRegularExpression(
            '/<div [^>]*class="svgraph-tooltip"[^>]*data-x="0"/',
            $svg,
        );
        self::assertMatchesRegularExpression(
            '/<div [^>]*class="svgraph-tooltip"[^>]*data-x="1"/',
            $svg,
        );
    }

    public function test_line_crosshair_emits_per_column_has_selectors(): void
    {
        $svg = Chart::line([1, 2, 3])->crosshair()->render();

        // Per-column rules drive the crosshair line, marker emphasis, and tooltip activation.
        self::assertStringContainsString(':has([data-x="0"]:hover)', $svg);
        self::assertStringContainsString(':has([data-x="1"]:hover)', $svg);
        self::assertStringContainsString(':has([data-x="2"]:hover)', $svg);
        self::assertStringContainsString('@supports selector(:has(a))', $svg);
    }

    public function test_line_crosshair_emits_focus_within_selectors(): void
    {
        $svg = Chart::line([1, 2, 3])->crosshair()->render();

        // Keyboard activation: focusing a marker also opens its column.
        self::assertStringContainsString(':has([data-x="0"]:focus-within)', $svg);
    }

    public function test_line_crosshair_without_points_marks_visual_markers_as_ghosts(): void
    {
        // With crosshair on but points off, markers must exist in the DOM (so the
        // column hover can reveal them) but the visible ellipse must start invisible.
        $svg = Chart::line([10, 20, 30])->crosshair()->render();

        // First child of each marker <g> is the visual ellipse — it must carry opacity="0".
        self::assertSame(
            3,
            preg_match_all('/<g [^>]*data-x="\d+"[^>]*><ellipse [^>]*opacity="0"/', $svg),
        );
    }

    public function test_line_crosshair_with_points_does_not_ghost_markers(): void
    {
        // When points() is also enabled, markers stay visible by default.
        $svg = Chart::line([10, 20, 30])->points()->crosshair()->render();

        self::assertSame(
            0,
            preg_match_all('/<g [^>]*data-x="\d+"[^>]*><ellipse [^>]*opacity="0"/', $svg),
        );
    }

    public function test_line_crosshair_returns_self_for_chaining(): void
    {
        $chart = Chart::line([1, 2, 3]);
        self::assertSame($chart, $chart->crosshair());
    }

    public function test_line_crosshair_off_disables_emission(): void
    {
        $svg = Chart::line([1, 2, 3])->crosshair(true)->crosshair(false)->render();

        self::assertStringNotContainsString('svgraph-x-hit', $svg);
        self::assertStringNotContainsString('svgraph-crosshair', $svg);
    }

    public function test_line_crosshair_works_for_multi_series(): void
    {
        $svg = Chart::line(['Jan' => 12, 'Feb' => 27, 'Mar' => 18])
            ->addSeries(Series::of('Costs', ['Jan' => 6, 'Feb' => 14, 'Mar' => 9]))
            ->crosshair()
            ->render();

        // Both series get a marker group at every column → 6 marker groups, but
        // only 3 hit rects (columns are shared across series).
        self::assertSame(3, substr_count($svg, 'class="svgraph-x-hit"'));
        self::assertSame(6, preg_match_all('/<g [^>]*data-x="\d+"/', $svg));
    }

    public function test_line_crosshair_empty_data_emits_nothing(): void
    {
        $svg = Chart::line([])->crosshair()->render();

        self::assertStringNotContainsString('svgraph-x-hit', $svg);
        self::assertStringNotContainsString('svgraph-crosshair', $svg);
    }
}

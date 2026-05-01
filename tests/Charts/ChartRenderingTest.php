<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Chart;
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

        self::assertSame(4, substr_count($svg, '<ellipse '));
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

    public function test_bar_rounded_sets_corner_radius(): void
    {
        $svg = Chart::bar(['Jan' => 10, 'Feb' => 20])->rounded(2.5)->render();

        self::assertStringContainsString('rx="2.5"', $svg);
        self::assertStringContainsString('ry="2.5"', $svg);
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
}

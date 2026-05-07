<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Charts\BarChart;
use Noeka\Svgraph\Charts\LineChart;
use Noeka\Svgraph\Charts\PieChart;
use Noeka\Svgraph\Charts\ProgressChart;
use Noeka\Svgraph\Theme;
use PHPUnit\Framework\TestCase;

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

    public function test_pie_single_slice_with_legend(): void
    {
        $svg = Chart::pie(['Only' => 100])->legend()->render();
        self::assertStringContainsString('<circle', $svg);
        self::assertStringContainsString('svgraph__labels', $svg);
        self::assertStringContainsString('Only', $svg);
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

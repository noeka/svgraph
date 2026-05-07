<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the chart-level a11y posture: role/labelledby/describedby on
 * the root SVG, screen-reader-only data table emission, and the title()/
 * description() overrides on every chart type.
 */
final class AccessibilityTest extends TestCase
{
    public function test_line_chart_root_svg_is_labelled_not_hidden(): void
    {
        $svg = Chart::line([10, 24, 18, 35])->render();

        self::assertStringNotContainsString('aria-hidden="true"', $svg);
        self::assertStringContainsString('role="img"', $svg);
        self::assertMatchesRegularExpression('/aria-labelledby="svgraph-\d+-title"/', $svg);
        self::assertMatchesRegularExpression('/aria-describedby="svgraph-\d+-desc"/', $svg);
    }

    public function test_root_svg_carries_title_and_desc_children(): void
    {
        $svg = Chart::line([10, 24, 18, 35])->render();

        self::assertMatchesRegularExpression('/<title id="svgraph-\d+-title">Line chart<\/title>/', $svg);
        self::assertMatchesRegularExpression('/<desc id="svgraph-\d+-desc">Line chart with 1 series of 4 points\. Range: 10 to 35\.<\/desc>/', $svg);
    }

    public function test_title_and_description_overrides_default(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->title('Quarterly revenue')
            ->description('Revenue (in $k) for Q1–Q3 2026.')
            ->render();

        self::assertMatchesRegularExpression('/<title id="svgraph-\d+-title">Quarterly revenue<\/title>/', $svg);
        self::assertMatchesRegularExpression('/<desc id="svgraph-\d+-desc">Revenue \(in \$k\) for Q1–Q3 2026\.<\/desc>/', $svg);
    }

    public function test_sr_only_data_table_emitted_for_line_chart(): void
    {
        $svg = Chart::line(['Mon' => 12, 'Tue' => 27, 'Wed' => 18])->render();

        self::assertStringContainsString('<table class="svgraph-sr-only">', $svg);
        self::assertStringContainsString('<th scope="col">Label</th>', $svg);
        self::assertStringContainsString('<th scope="col">Series 1</th>', $svg);
        self::assertStringContainsString('<th scope="row">Mon</th><td>12</td>', $svg);
        self::assertStringContainsString('<th scope="row">Tue</th><td>27</td>', $svg);
        self::assertStringContainsString('<th scope="row">Wed</th><td>18</td>', $svg);
    }

    public function test_sr_only_data_table_lists_every_series(): void
    {
        $svg = Chart::line(['Q1' => 10, 'Q2' => 20])
            ->addSeries(Series::of('Costs', ['Q1' => 5, 'Q2' => 8]))
            ->render();

        self::assertStringContainsString('<th scope="col">Series 1</th><th scope="col">Costs</th>', $svg);
        self::assertStringContainsString('<th scope="row">Q1</th><td>10</td><td>5</td>', $svg);
        self::assertStringContainsString('<th scope="row">Q2</th><td>20</td><td>8</td>', $svg);
    }

    public function test_sr_only_table_carries_hiding_css(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])->render();

        // Visually hidden but kept in the accessibility tree.
        self::assertStringContainsString('.svgraph-sr-only{position:absolute;width:1px;height:1px', $svg);
    }

    public function test_pie_data_table_lists_every_slice(): void
    {
        $svg = Chart::pie([['Stripe', 1240], ['PayPal', 432]])->render();

        self::assertStringContainsString('<title id="svgraph-1-title">Pie chart</title>', preg_replace('/svgraph-\d+/', 'svgraph-1', $svg) ?? '');
        self::assertStringContainsString('<th scope="col">Slice</th><th scope="col">Value</th>', $svg);
        self::assertStringContainsString('<th scope="row">Stripe</th><td>1,240</td>', $svg);
        self::assertStringContainsString('<th scope="row">PayPal</th><td>432</td>', $svg);
    }

    public function test_donut_announces_as_donut_not_pie(): void
    {
        $svg = Chart::donut([['A', 25], ['B', 75]])->render();

        self::assertStringContainsString('>Donut chart</title>', $svg);
    }

    public function test_horizontal_bar_announces_orientation(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])->horizontal()->render();

        self::assertStringContainsString('>Horizontal bar chart</title>', $svg);
    }

    public function test_progress_chart_describes_value_and_target(): void
    {
        $svg = Chart::progress(72.0, 100.0)->render();

        self::assertStringContainsString('>Progress chart</title>', $svg);
        self::assertStringContainsString('Progress: 72 of 100 (72%).', $svg);
        self::assertStringContainsString('<th scope="row">Value</th><td>72</td>', $svg);
        self::assertStringContainsString('<th scope="row">Target</th><td>100</td>', $svg);
    }

    public function test_sparkline_announces_as_sparkline(): void
    {
        $svg = Chart::sparkline([10, 12, 8, 18])->render();

        self::assertStringContainsString('>Sparkline</title>', $svg);
    }

    public function test_empty_chart_still_labelled(): void
    {
        $svg = Chart::bar([])->render();

        self::assertStringContainsString('role="img"', $svg);
        self::assertStringContainsString('Bar chart (no data).', $svg);
        // Empty data → no table emitted.
        self::assertStringNotContainsString('<table', $svg);
    }

    public function test_animations_remain_wrapped_in_reduced_motion_query(): void
    {
        $svg = Chart::line([1, 2, 3])->animate()->render();

        self::assertStringContainsString('@media (prefers-reduced-motion:no-preference)', $svg);
        self::assertStringContainsString('@media (prefers-reduced-motion:reduce)', $svg);
    }

    public function test_data_points_remain_keyboard_focusable(): void
    {
        $svg = Chart::line([10, 20, 30])->points()->render();

        // Hit-target ellipses carry tabindex="0" so keyboard users can tab through every point.
        self::assertGreaterThanOrEqual(3, substr_count($svg, 'tabindex="0"'));
    }
}

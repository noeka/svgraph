<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Chart;
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

    public function test_sparkline_renders_with_fill(): void
    {
        $svg = Chart::sparkline([10, 12, 8, 18])->render();

        self::assertStringContainsString('svgraph--sparkline', $svg);
        self::assertStringContainsString('fill-opacity="0.15"', $svg);
    }

    public function test_bar_chart_renders_rects(): void
    {
        $svg = Chart::bar(['Jan' => 10, 'Feb' => 20, 'Mar' => 5])->render();

        self::assertStringContainsString('svgraph--bar', $svg);
        self::assertStringContainsString('<rect', $svg);
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

    public function test_donut_chart_uses_donut_variant(): void
    {
        $svg = Chart::donut(['A' => 1, 'B' => 1])->thickness(0.5)->render();

        self::assertStringContainsString('svgraph--donut', $svg);
    }

    public function test_pie_with_legend_renders_labels(): void
    {
        $svg = Chart::pie(['Stripe' => 100, 'PayPal' => 50])->legend()->render();

        self::assertStringContainsString('svgraph__labels', $svg);
        self::assertStringContainsString('Stripe', $svg);
        self::assertStringContainsString('PayPal', $svg);
    }

    public function test_progress_clamps_to_target(): void
    {
        $svg = Chart::progress(150, 100)->render();

        self::assertStringContainsString('svgraph--progress', $svg);
        self::assertSame(2, substr_count($svg, '<rect '));
    }

    public function test_progress_zero_target_does_not_explode(): void
    {
        $svg = Chart::progress(50, 0)->render();
        self::assertStringContainsString('svgraph--progress', $svg);
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

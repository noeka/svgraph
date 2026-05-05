<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Snapshots;

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Charts\AbstractChart;
use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Theme;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

/**
 * Byte-level snapshots of representative chart shapes.
 *
 * Targets the Wrapper-generated CSS paths (hover, tooltip, animation,
 * legend, crosshair, reduced-motion fallback) where mutation testing
 * showed many escaping mutants because per-attribute substring tests
 * don't constrain string-concatenation order.
 *
 * Chart IDs are derived from a static counter on AbstractChart, so the
 * counter is reset before each test to keep snapshots stable across
 * runs and test orderings.
 */
final class ChartSnapshotTest extends TestCase
{
    use MatchesSnapshots;

    protected function setUp(): void
    {
        $ref = new \ReflectionClass(AbstractChart::class);
        $prop = $ref->getProperty('nextId');
        $prop->setValue(null, 0);
    }

    public function test_line_minimal(): void
    {
        $svg = Chart::line([10, 24, 18, 35])->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_line_smooth_fill_and_points(): void
    {
        $svg = Chart::line([['Mon', 10], ['Tue', 24], ['Wed', 18], ['Thu', 35]])
            ->smooth()
            ->fillBelow('#8b5cf6', 0.25)
            ->points()
            ->axes()
            ->grid()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_line_animated(): void
    {
        $svg = Chart::line([10, 24, 18, 35])
            ->animate()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_line_with_crosshair_multi_series(): void
    {
        $svg = Chart::line(['Jan' => 12, 'Feb' => 27, 'Mar' => 18])
            ->addSeries(Series::of('Costs', ['Jan' => 6, 'Feb' => 14, 'Mar' => 9], '#ef4444'))
            ->crosshair()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_line_with_legend_multi_series(): void
    {
        $svg = Chart::line(['Jan' => 12, 'Feb' => 27, 'Mar' => 18])
            ->addSeries(Series::of('Costs', ['Jan' => 6, 'Feb' => 14, 'Mar' => 9]))
            ->legend()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_sparkline_animated(): void
    {
        $svg = Chart::sparkline([10, 12, 8, 18])
            ->animate()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_bar_vertical_grouped_animated(): void
    {
        $svg = Chart::bar(['Q1' => 30, 'Q2' => 45, 'Q3' => 22])
            ->addSeries(Series::of('Costs', ['Q1' => 10, 'Q2' => 20, 'Q3' => 14]))
            ->grouped()
            ->animate()
            ->axes()
            ->grid()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_bar_horizontal_animated(): void
    {
        $svg = Chart::bar([['A', 10], ['B', 20], ['C', 30]])
            ->horizontal()
            ->animate()
            ->axes()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_bar_stacked_with_negatives(): void
    {
        $svg = Chart::bar(['Q1' => 30, 'Q2' => -10, 'Q3' => 22])
            ->addSeries(Series::of('Costs', ['Q1' => 10, 'Q2' => 5, 'Q3' => -8]))
            ->stacked()
            ->axes()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_bar_empty(): void
    {
        $svg = Chart::bar([])->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_pie_with_legend_animated(): void
    {
        $svg = Chart::pie([['Apples', 30], ['Oranges', 20], ['Pears', 50]])
            ->legend()
            ->animate()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_donut_animated(): void
    {
        $svg = Chart::donut([['A', 25], ['B', 25], ['C', 50]])
            ->thickness(0.5)
            ->animate()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_progress(): void
    {
        $svg = Chart::progress(72.0, 100.0)
            ->color('#10b981')
            ->showValue()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }

    public function test_line_dark_theme(): void
    {
        $svg = Chart::line([10, 24, 18, 35])
            ->theme(Theme::dark())
            ->axes()
            ->grid()
            ->render();
        $this->assertMatchesSnapshot($svg);
    }
}

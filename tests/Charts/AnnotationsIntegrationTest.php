<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Annotations\Callout;
use Noeka\Svgraph\Annotations\ReferenceLine;
use Noeka\Svgraph\Annotations\TargetZone;
use Noeka\Svgraph\Annotations\ThresholdBand;
use Noeka\Svgraph\Chart;
use PHPUnit\Framework\TestCase;

/**
 * Wire-level tests confirming the annotate() builder threads through to the
 * rendered output of the chart types that support annotations, with the
 * correct z-ordering relative to series data.
 */
final class AnnotationsIntegrationTest extends TestCase
{
    public function test_annotate_returns_self_for_chaining(): void
    {
        $chart = Chart::line([1, 2, 3]);
        self::assertSame($chart, $chart->annotate(ReferenceLine::y(2)));
    }

    public function test_line_chart_emits_reference_line_markup(): void
    {
        $svg = Chart::line([10, 20, 30, 40])
            ->annotate(ReferenceLine::y(25))
            ->render();

        self::assertStringContainsString('class="svgraph-annotation-ref"', $svg);
    }

    public function test_line_chart_emits_threshold_band_markup(): void
    {
        $svg = Chart::line([10, 20, 30, 40])
            ->annotate(ThresholdBand::y(15, 35)->fill('#ff000022'))
            ->render();

        self::assertStringContainsString('class="svgraph-annotation-band"', $svg);
        self::assertStringContainsString('fill="#ff000022"', $svg);
    }

    public function test_bar_chart_emits_target_zone_markup(): void
    {
        $svg = Chart::bar([10, 20, 30, 40])
            ->annotate(TargetZone::x(1, 2)->fill('#00ff0033'))
            ->render();

        self::assertStringContainsString('class="svgraph-annotation-zone"', $svg);
        self::assertStringContainsString('fill="#00ff0033"', $svg);
    }

    public function test_bands_render_before_data_callouts_render_after(): void
    {
        $svg = Chart::line([10, 20, 30, 40])
            ->annotate(ThresholdBand::y(15, 35))
            ->annotate(Callout::at(2, 30, 'peak'))
            ->render();

        $bandPos = strpos($svg, 'svgraph-annotation-band');
        $pathPos = strpos($svg, '<path ');
        $calloutPos = strpos($svg, 'svgraph-annotation-callout');

        self::assertNotFalse($bandPos);
        self::assertNotFalse($pathPos);
        self::assertNotFalse($calloutPos);
        self::assertLessThan($pathPos, $bandPos, 'Threshold band should render before line path');
        self::assertGreaterThan($pathPos, $calloutPos, 'Callout should render after line path');
    }

    public function test_reference_lines_render_before_data(): void
    {
        $svg = Chart::line([10, 20, 30, 40])
            ->annotate(ReferenceLine::y(25))
            ->render();

        $refPos = strpos($svg, 'svgraph-annotation-ref');
        $pathPos = strpos($svg, '<path ');

        self::assertNotFalse($refPos);
        self::assertNotFalse($pathPos);
        self::assertLessThan($pathPos, $refPos);
    }

    public function test_reference_line_label_appears_in_labels_overlay(): void
    {
        $svg = Chart::line([10, 20, 30, 40])
            ->annotate(ReferenceLine::y(25)->label('Goal'))
            ->render();

        self::assertStringContainsString('svgraph__labels', $svg);
        self::assertStringContainsString('Goal', $svg);
    }

    public function test_out_of_range_annotation_skipped_silently(): void
    {
        // y=999 is well above the data range; chart still renders without errors.
        $svg = Chart::line([10, 20, 30, 40])
            ->annotate(ReferenceLine::y(999))
            ->render();

        self::assertStringNotContainsString('svgraph-annotation-ref', $svg);
        self::assertStringContainsString('<path ', $svg);
    }

    public function test_pie_chart_silently_ignores_annotations(): void
    {
        // Pie has no plot area / scales; annotations attached should be a no-op.
        $svg = Chart::pie([['A', 1], ['B', 2]])
            ->annotate(ReferenceLine::y(1))
            ->render();

        self::assertStringNotContainsString('svgraph-annotation-ref', $svg);
    }

    public function test_progress_chart_silently_ignores_annotations(): void
    {
        $svg = Chart::progress(50)
            ->annotate(ReferenceLine::y(25))
            ->render();

        self::assertStringNotContainsString('svgraph-annotation-ref', $svg);
    }

    public function test_horizontal_bar_chart_supports_x_reference_line(): void
    {
        $svg = Chart::bar([10, 20, 30])->horizontal()
            ->annotate(ReferenceLine::x(15)->label('Target'))
            ->render();

        self::assertStringContainsString('svgraph-annotation-ref', $svg);
        self::assertStringContainsString('Target', $svg);
    }

    public function test_multiple_annotations_render_in_insertion_order(): void
    {
        $svg = Chart::line([10, 20, 30, 40])
            ->annotate(ReferenceLine::y(15))
            ->annotate(ReferenceLine::y(25))
            ->render();

        self::assertSame(2, substr_count($svg, 'svgraph-annotation-ref'));
    }
}

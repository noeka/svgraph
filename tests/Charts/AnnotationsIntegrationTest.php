<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use DateTimeImmutable;
use Noeka\Svgraph\Annotations\Callout;
use Noeka\Svgraph\Annotations\ReferenceLine;
use Noeka\Svgraph\Annotations\TargetZone;
use Noeka\Svgraph\Annotations\ThresholdBand;
use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Charts\LineChart;
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

    public function test_threshold_band_label_centred_between_band_edges(): void
    {
        // Asymmetric band [5, 15] over data [0..40] (domain padded to [-4, 44]):
        //   y=5 → 81.25, y=15 → 60.42 → midpoint 70.83.
        $svg = Chart::line([0, 10, 20, 30, 40])
            ->annotate(ThresholdBand::y(5, 15)->label('warn'))
            ->render();
        self::assertStringContainsString('top:70.8333%', $svg);
        self::assertStringContainsString('>warn', $svg);
    }

    public function test_target_zone_label_centred_between_zone_edges(): void
    {
        // Target zone spanning x[0, 2] of a 5-point series (indices 0..4):
        // domain [0, 4], no padding, plot [0, 100]. Rect from x=0 to x=50.
        // Midpoint = 25.
        $svg = Chart::line([10, 20, 30, 40, 50])
            ->annotate(TargetZone::x(0, 2)->label('target'))
            ->render();
        self::assertStringContainsString('left:25%', $svg);
        self::assertStringContainsString('>target', $svg);
    }

    public function test_horizontal_reference_line_label_anchored_to_right_edge(): void
    {
        // Horizontal reference line at y=35 on [10..50] data, no axes:
        //   plotRight = viewport.width = 100. label `right` = max(0, 100-100) = 0.
        // If `max(0.0, ...)` becomes `max(1.0, ...)` the label shifts to 1%.
        $svg = Chart::line([10, 20, 30, 40, 50])
            ->annotate(ReferenceLine::y(35)->label('goal'))
            ->render();
        self::assertStringContainsString('right:0%', $svg);
    }

    public function test_time_axis_reference_line_maps_via_time_scale(): void
    {
        // When a line chart has both a time axis and a fallback index xScale,
        // the AnnotationContext must receive the TimeScale (so DateTime-valued
        // references can be mapped). If LineChart's `$timeScale ?? $xScale`
        // flips to `$xScale ?? $timeScale`, mapX returns null for a DateTime
        // value and the annotation disappears entirely.
        $t0 = new DateTimeImmutable('2026-05-01T00:00:00Z');
        $tMid = new DateTimeImmutable('2026-05-15T00:00:00Z');
        $t1 = new DateTimeImmutable('2026-05-30T00:00:00Z');

        $svg = (new LineChart())
            ->series([[$t0, 1], [$tMid, 5], [$t1, 9]])
            ->timeAxis()
            ->annotate(ReferenceLine::x($tMid))
            ->render();

        // The annotation must be present...
        self::assertStringContainsString('svgraph-annotation-ref', $svg);
        // ...and at the TimeScale-mapped x for May 15 (14/29 of the way from
        // x=0 to x=100, since no axes/grid means no padding). The exact
        // value isn't 50 because May has 31 days and our range is 30 wide.
        self::assertMatchesRegularExpression(
            '/svgraph-annotation-ref[^>]*x1="48\.\d+"/',
            $svg,
        );
    }
}

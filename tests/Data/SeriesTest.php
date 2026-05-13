<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Data;

use Noeka\Svgraph\Data\Link;
use Noeka\Svgraph\Data\Point;
use Noeka\Svgraph\Data\Series;
use PHPUnit\Framework\TestCase;

final class SeriesTest extends TestCase
{
    public function test_from_numeric_array(): void
    {
        $series = Series::from([10, 24, 18]);
        self::assertCount(3, $series);
        self::assertSame([10.0, 24.0, 18.0], $series->values());
        self::assertSame([null, null, null], $series->labels());
    }

    public function test_from_associative_map(): void
    {
        $series = Series::from(['Mon' => 10, 'Tue' => 24]);
        self::assertSame([10.0, 24.0], $series->values());
        self::assertSame(['Mon', 'Tue'], $series->labels());
    }

    public function test_from_label_value_tuples(): void
    {
        $series = Series::from([['Mon', 10], ['Tue', 24]]);
        self::assertSame([10.0, 24.0], $series->values());
        self::assertSame(['Mon', 'Tue'], $series->labels());
    }

    public function test_from_point_objects_passes_through(): void
    {
        $p1 = new Point(5.0, 'A');
        $p2 = new Point(10.0, 'B');
        $series = Series::from([$p1, $p2]);
        self::assertSame([$p1, $p2], $series->points);
    }

    public function test_min_max_sum(): void
    {
        $series = Series::from([3, 1, 4, 1, 5, 9]);
        self::assertSame(1.0, $series->min());
        self::assertSame(9.0, $series->max());
        self::assertSame(23.0, $series->sum());
    }

    public function test_empty_series_returns_zero_for_aggregates(): void
    {
        $series = Series::from([]);
        self::assertSame(0.0, $series->min());
        self::assertSame(0.0, $series->max());
        self::assertSame(0.0, $series->sum());
        self::assertTrue($series->isEmpty());
        self::assertCount(0, $series);
    }

    public function test_has_labels_true_when_labels_present(): void
    {
        self::assertTrue(Series::from(['Jan' => 10, 'Feb' => 20])->hasLabels());
    }

    public function test_has_labels_false_for_unlabelled_series(): void
    {
        self::assertFalse(Series::from([10, 20, 30])->hasLabels());
    }

    public function test_from_drops_non_finite_values(): void
    {
        $series = Series::from([10, NAN, 20, INF, -INF, 30]);
        self::assertSame([10.0, 20.0, 30.0], $series->values());
    }

    public function test_from_drops_non_finite_in_tuples_and_assoc(): void
    {
        $tuples = Series::from([['A', 1], ['B', NAN], ['C', 3]]);
        self::assertSame([1.0, 3.0], $tuples->values());
        self::assertSame(['A', 'C'], $tuples->labels());

        $assoc = Series::from(['A' => 1, 'B' => INF, 'C' => 3]);
        self::assertSame([1.0, 3.0], $assoc->values());
        self::assertSame(['A', 'C'], $assoc->labels());
    }

    public function test_from_drops_non_finite_point_objects(): void
    {
        $series = Series::from([new Point(5.0, 'A'), new Point(NAN, 'B'), new Point(7.0, 'C')]);
        self::assertSame([5.0, 7.0], $series->values());
        self::assertSame(['A', 'C'], $series->labels());
    }

    public function test_from_coerces_numeric_strings(): void
    {
        $series = Series::from(['10', '20.5', '30']);
        self::assertSame([10.0, 20.5, 30.0], $series->values());
    }

    public function test_from_drops_non_numeric_scalar_values(): void
    {
        $series = Series::from([10, 'abc', 20, true, 30, null]);
        self::assertSame([10.0, 20.0, 30.0], $series->values());
    }

    public function test_from_drops_non_scalar_tuple_values(): void
    {
        $series = Series::from([
            ['A', 1],
            ['B', new \stdClass()],
            ['C', 3],
        ]);
        self::assertSame([1.0, 3.0], $series->values());
        self::assertSame(['A', 'C'], $series->labels());
    }

    public function test_from_handles_non_scalar_tuple_label(): void
    {
        $series = Series::from([
            [['nested'], 5],
            [null, 7],
        ]);
        self::assertSame([5.0, 7.0], $series->values());
        self::assertSame([null, null], $series->labels());
    }

    public function test_from_coerces_int_label_in_tuple_to_string(): void
    {
        $series = Series::from([[2024, 10], [2025, 20]]);
        self::assertSame([10.0, 20.0], $series->values());
        self::assertSame(['2024', '2025'], $series->labels());
    }

    public function test_from_label_value_low_high_tuple(): void
    {
        $series = Series::from([['Mon', 10, 5, 15], ['Tue', 20, 16, 24]]);

        self::assertSame([10.0, 20.0], $series->values());
        self::assertSame(5.0, $series->points[0]->low);
        self::assertSame(15.0, $series->points[0]->high);
        self::assertSame(16.0, $series->points[1]->low);
        self::assertSame(24.0, $series->points[1]->high);
    }

    public function test_from_drops_non_finite_range_bounds(): void
    {
        // Non-finite low/high silently drops the range (both go null) so the
        // point still plots — just without an error overlay.
        $series = Series::from([['A', 10, NAN, 15], ['B', 20, 5, INF]]);

        self::assertNull($series->points[0]->low);
        self::assertNull($series->points[0]->high);
        self::assertNull($series->points[1]->low);
        self::assertNull($series->points[1]->high);
    }

    public function test_from_label_value_link_low_high_tuple(): void
    {
        $link = new Link('/x');
        $series = Series::from([['A', 10, $link, 5, 15]]);

        self::assertSame($link, $series->points[0]->link);
        self::assertSame(5.0, $series->points[0]->low);
        self::assertSame(15.0, $series->points[0]->high);
    }

    public function test_bounds_extend_to_include_range(): void
    {
        // boundsMin/boundsMax always include any range data so the chart's
        // axis fits an error overlay even when the user hasn't toggled it.
        $series = Series::from([['A', 10, 2, 18], ['B', 12, 4, 25]]);
        self::assertSame(10.0, $series->min());
        self::assertSame(12.0, $series->max());
        self::assertSame(2.0, $series->boundsMin);
        self::assertSame(25.0, $series->boundsMax);
    }

    public function test_bounds_equal_min_max_without_range_data(): void
    {
        $series = Series::from([10, 20, 30]);
        self::assertSame($series->min(), $series->boundsMin);
        self::assertSame($series->max(), $series->boundsMax);
    }

    public function test_has_range_data_reports_presence_of_low_high(): void
    {
        self::assertFalse(Series::from([1, 2, 3])->hasRangeData());
        self::assertTrue(Series::from([['A', 10, 5, 15]])->hasRangeData());
    }
}

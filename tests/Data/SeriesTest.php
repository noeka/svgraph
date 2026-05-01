<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Data;

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
}

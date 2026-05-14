<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Data;

use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Data\SeriesCollection;
use PHPUnit\Framework\TestCase;

final class SeriesCollectionTest extends TestCase
{
    public function test_no_items_is_empty(): void
    {
        $collection = new SeriesCollection();
        self::assertTrue($collection->isEmpty());
    }

    public function test_only_empty_series_is_empty(): void
    {
        $collection = (new SeriesCollection())->with(Series::from([]));
        self::assertTrue($collection->isEmpty());
    }

    public function test_non_empty_series_is_not_empty(): void
    {
        $collection = (new SeriesCollection())->with(Series::from([1, 2]));
        self::assertFalse($collection->isEmpty());
    }

    public function test_max_length_is_zero_when_empty(): void
    {
        $collection = new SeriesCollection();
        self::assertSame(0, $collection->maxLength());
    }

    public function test_max_length_returns_longest_series(): void
    {
        $collection = (new SeriesCollection())
            ->with(Series::from([1, 2, 3, 4, 5]))
            ->with(Series::from([1, 2]));
        self::assertSame(5, $collection->maxLength());
    }

    public function test_value_min_falls_back_to_zero_when_all_series_empty(): void
    {
        $collection = (new SeriesCollection())
            ->with(Series::from([]))
            ->with(Series::from([]));
        self::assertSame(0.0, $collection->valueMin());
    }

    public function test_value_max_falls_back_to_zero_when_all_series_empty(): void
    {
        $collection = (new SeriesCollection())
            ->with(Series::from([]))
            ->with(Series::from([]));
        self::assertSame(0.0, $collection->valueMax());
    }

    public function test_stacked_max_returns_zero_with_only_negative_values(): void
    {
        // No positive contribution at any column → cumulative max stays at 0.
        $collection = (new SeriesCollection())
            ->with(Series::from([-1, -2, -3]))
            ->with(Series::from([-4, -5, -6]));
        self::assertSame(0.0, $collection->stackedMax());
    }

    public function test_stacked_max_treats_missing_positions_as_zero(): void
    {
        // The longer series defines the column count. At positions where a
        // shorter series has no value, that series must contribute 0 (not 1).
        $collection = (new SeriesCollection())
            ->with(Series::from([5, 5, 5, 5, 5]))
            ->with(Series::from([10, 10]));
        // Column 0: 5+10=15, Column 1: 5+10=15, Columns 2-4: 5 each. Max=15.
        self::assertSame(15.0, $collection->stackedMax());
    }

    public function test_stacked_min_returns_zero_with_only_positive_values(): void
    {
        $collection = (new SeriesCollection())
            ->with(Series::from([1, 2, 3]))
            ->with(Series::from([4, 5, 6]));
        self::assertSame(0.0, $collection->stackedMin());
    }

    public function test_common_labels_ignores_unlabeled_longer_series(): void
    {
        // The longer series has no labels and must be skipped (via `continue`),
        // not replace the labelled series' labels with a list of nulls.
        $collection = (new SeriesCollection())
            ->with(Series::from(['Jan' => 1, 'Feb' => 2, 'Mar' => 3]))
            ->with(Series::from([10, 20, 30, 40, 50]));
        self::assertSame(['Jan', 'Feb', 'Mar'], $collection->commonLabels());
    }

    public function test_common_labels_keeps_first_when_lengths_tie(): void
    {
        // Same-length labelled series: the first one's labels are returned
        // (strict `>`, not `>=`).
        $first = Series::from(['A' => 1, 'B' => 2]);
        $second = Series::from(['X' => 10, 'Y' => 20]);
        $collection = (new SeriesCollection())->with($first)->with($second);
        self::assertSame(['A', 'B'], $collection->commonLabels());
    }
}

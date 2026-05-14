<?php

declare(strict_types=1);

namespace Noeka\Svgraph;

use Noeka\Svgraph\Charts\BarChart;
use Noeka\Svgraph\Charts\DonutChart;
use Noeka\Svgraph\Charts\LineChart;
use Noeka\Svgraph\Charts\PieChart;
use Noeka\Svgraph\Charts\ProgressChart;
use Noeka\Svgraph\Charts\SparklineChart;

final class Chart
{
    /** @param iterable<mixed>|null $data */
    public static function line(?iterable $data = null): LineChart
    {
        $chart = new LineChart();

        if ($data !== null) {
            $chart->data($data);
        }

        return $chart;
    }

    /** @param iterable<mixed>|null $data */
    public static function sparkline(?iterable $data = null): SparklineChart
    {
        $chart = new SparklineChart();

        if ($data !== null) {
            $chart->data($data);
        }

        return $chart;
    }

    /** @param iterable<mixed>|null $data */
    public static function bar(?iterable $data = null): BarChart
    {
        $chart = new BarChart();

        if ($data !== null) {
            $chart->data($data);
        }

        return $chart;
    }

    /** @param iterable<mixed>|null $data */
    public static function pie(?iterable $data = null): PieChart
    {
        $chart = new PieChart();

        if ($data !== null) {
            $chart->data($data);
        }

        return $chart;
    }

    /** @param iterable<mixed>|null $data */
    public static function donut(?iterable $data = null): DonutChart
    {
        $chart = new DonutChart();

        if ($data !== null) {
            $chart->data($data);
        }

        return $chart;
    }

    public static function progress(float $value = 0.0, float $target = 100.0): ProgressChart
    {
        return new ProgressChart($value, $target);
    }
}

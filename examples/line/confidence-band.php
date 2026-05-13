<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;

return Chart::line()
    ->addSeries(
        Series::of('Forecast', [
            ['Jan', 12, 10, 14],
            ['Feb', 19, 15, 23],
            ['Mar', 26, 20, 32],
            ['Apr', 33, 25, 41],
            ['May', 41, 30, 52],
            ['Jun', 48, 34, 62],
            ['Jul', 56, 38, 74],
        ], '#8b5cf6')->withConfidenceBand(),
    )
    ->axes()->grid()->smooth()->points();

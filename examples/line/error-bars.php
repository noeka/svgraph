<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;

return Chart::line()
    ->addSeries(
        Series::of('Measurement', [
            ['W1', 12, 9, 15],
            ['W2', 18, 14, 22],
            ['W3', 24, 19, 29],
            ['W4', 22, 18, 26],
            ['W5', 30, 25, 35],
            ['W6', 27, 22, 32],
            ['W7', 35, 29, 41],
        ], '#3b82f6')->withErrorBars(),
    )
    ->axes()->grid()->points();

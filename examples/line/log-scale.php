<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::line([
    ['1B', 1],
    ['10B', 10],
    ['100B', 100],
    ['1KB', 1_000],
    ['10KB', 10_000],
    ['100KB', 100_000],
    ['1MB', 1_000_000],
])
    ->logScale()
    ->axes()->grid()->points()
    ->stroke('#8b5cf6');

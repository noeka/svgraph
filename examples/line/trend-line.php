<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;

return Chart::line()
    ->addSeries(
        Series::of('Revenue', [
            'Jan' => 12, 'Feb' => 19, 'Mar' => 18,
            'Apr' => 27, 'May' => 24, 'Jun' => 33,
            'Jul' => 31, 'Aug' => 42, 'Sep' => 45,
        ], '#3b82f6')->withTrendLine(),
    )
    ->axes()->grid()->points();

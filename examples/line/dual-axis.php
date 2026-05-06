<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;

// Revenue (left axis, USD) vs. conversion rate (right axis, percent).
// Independent scales let the small percentage values share the plot
// area with the large dollar amounts without one collapsing into the
// other.
return Chart::line(['Jan' => 12_000, 'Feb' => 18_500, 'Mar' => 21_300, 'Apr' => 26_800, 'May' => 32_100])
    ->addSeries(
        Series::of('Conversion %', ['Jan' => 1.4, 'Feb' => 1.8, 'Mar' => 2.1, 'Apr' => 2.6, 'May' => 3.1], '#ef4444')
            ->onAxis('right'),
    )
    ->axes()->grid()->points()->smooth()
    ->secondaryAxis()
    ->stroke('#3b82f6');

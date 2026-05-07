<?php

declare(strict_types=1);

use Noeka\Svgraph\Annotations\ReferenceLine;
use Noeka\Svgraph\Chart;

// A "Goal" reference line at y=40 helps the eye check which days hit target.
return Chart::line([
    ['Mon', 12], ['Tue', 27], ['Wed', 18],
    ['Thu', 41], ['Fri', 33], ['Sat', 52], ['Sun', 38],
])
    ->axes()->grid()->points()->stroke('#3b82f6')
    ->annotate(ReferenceLine::y(40)->label('Goal')->color('#ef4444'));

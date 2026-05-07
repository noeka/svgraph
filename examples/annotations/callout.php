<?php

declare(strict_types=1);

use Noeka\Svgraph\Annotations\Callout;
use Noeka\Svgraph\Chart;

// Point at the peak with a callout. The leader-line offset is given in
// viewport units (the SVG's 100x100 logical box); negative values point
// up/left.
return Chart::line([
    ['Mon', 12], ['Tue', 27], ['Wed', 18],
    ['Thu', 41], ['Fri', 33], ['Sat', 52], ['Sun', 38],
])
    ->axes()->grid()->smooth()->points()->stroke('#3b82f6')
    ->annotate(Callout::at(5, 52, 'Record high')->offset(-10, -8)->color('#ef4444'));

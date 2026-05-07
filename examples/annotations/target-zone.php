<?php

declare(strict_types=1);

use Noeka\Svgraph\Annotations\TargetZone;
use Noeka\Svgraph\Chart;

// Highlight a deployment window across columns 3..5.
return Chart::line([
    ['Mon', 12], ['Tue', 27], ['Wed', 18],
    ['Thu', 41], ['Fri', 33], ['Sat', 52], ['Sun', 38],
])
    ->axes()->grid()->smooth()->stroke('#3b82f6')
    ->annotate(TargetZone::x(3.0, 5.0)->fill('#f59e0b22')->label('Deploy'));

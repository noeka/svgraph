<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::line([
    ['Mon', 12], ['Tue', 27], ['Wed', 18],
    ['Thu', 41], ['Fri', 33], ['Sat', 52],
])->axes()->grid()->smooth()->points()->stroke('#3b82f6');

<?php

declare(strict_types=1);

use Noeka\Svgraph\Annotations\ThresholdBand;
use Noeka\Svgraph\Chart;

// Highlight a "healthy range" between 20 and 40.
return Chart::line([
    ['Mon', 12], ['Tue', 27], ['Wed', 18],
    ['Thu', 41], ['Fri', 33], ['Sat', 52], ['Sun', 38],
])
    ->axes()->grid()->smooth()->points()->stroke('#3b82f6')
    ->annotate(ThresholdBand::y(20, 40)->fill('#10b98122')->label('Healthy'));

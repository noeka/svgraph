<?php

declare(strict_types=1);

use Noeka\Svgraph\Annotations\Callout;
use Noeka\Svgraph\Annotations\ReferenceLine;
use Noeka\Svgraph\Annotations\TargetZone;
use Noeka\Svgraph\Annotations\ThresholdBand;
use Noeka\Svgraph\Chart;

// Hero example combining every annotation type on a single chart.
// Bands and reference lines render behind the data; the callout sits
// on top so its leader line stays legible.
return Chart::line([
    ['Mon', 12], ['Tue', 27], ['Wed', 18],
    ['Thu', 41], ['Fri', 33], ['Sat', 52], ['Sun', 38],
])
    ->axes()->grid()->smooth()->points()->stroke('#3b82f6')
    ->annotate(ThresholdBand::y(20, 40)->fill('#10b98122')->label('Healthy'))
    ->annotate(TargetZone::x(3.0, 5.0)->fill('#f59e0b18'))
    ->annotate(ReferenceLine::y(40)->label('Goal')->color('#ef4444'))
    ->annotate(Callout::at(5, 52, 'Record')->offset(-10, -6)->color('#ef4444'));

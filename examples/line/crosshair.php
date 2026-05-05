<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;

return Chart::line(['Mon' => 12, 'Tue' => 27, 'Wed' => 18, 'Thu' => 41, 'Fri' => 33, 'Sat' => 52, 'Sun' => 38])
    ->addSeries(Series::of('Costs', ['Mon' => 6, 'Tue' => 14, 'Wed' => 9, 'Thu' => 22, 'Fri' => 18, 'Sat' => 30, 'Sun' => 21], '#ef4444'))
    ->axes()->grid()->points()->crosshair();

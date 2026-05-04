<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;

return Chart::line(['Jan' => 12, 'Feb' => 27, 'Mar' => 18, 'Apr' => 33, 'May' => 41])
    ->addSeries(Series::of('Costs', ['Jan' => 6, 'Feb' => 14, 'Mar' => 9, 'Apr' => 18, 'May' => 22], '#ef4444'))
    ->axes()->grid()->points()->smooth();

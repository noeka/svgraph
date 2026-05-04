<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::bar([
    'Mon' => 22, 'Tue' => 41, 'Wed' => 33, 'Thu' => 28,
    'Fri' => 47, 'Sat' => 38, 'Sun' => 19,
])->axes()->grid()->rounded(2)->color('#10b981');

<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::pie([
    'Direct'  => 540,
    'Search'  => 320,
    'Social'  => 210,
    'Email'   => 130,
])->startAngle(-90)->legend();

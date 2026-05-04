<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::sparkline([14, 22, 18, 27, 31, 24, 33, 41, 36, 48])
    ->smooth()
    ->stroke('#10b981')
    ->fillBelow('#10b981', 0.25);

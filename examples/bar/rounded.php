<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::bar(['Jan' => 120, 'Feb' => 180, 'Mar' => 90, 'Apr' => 210])
    ->axes()->rounded(4)->color('#f59e0b')->gap(0.35);

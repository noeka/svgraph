<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::bar(['Jan' => 120, 'Feb' => 180, 'Mar' => 90, 'Apr' => 210, 'May' => 165])
    ->axes()->grid()->rounded(2)->color('#10b981');

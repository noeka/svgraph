<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::progress(value: 4200, target: 5000)
    ->color('#10b981')
    ->trackColor('#dcfce7')
    ->rounded(50)
    ->showValue();

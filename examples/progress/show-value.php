<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::progress(value: 12, target: 20)
    ->color('#3b82f6')
    ->showValue(label: '12 of 20 done');

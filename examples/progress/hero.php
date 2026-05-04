<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::progress(value: 7400, target: 10000)
    ->color('#3b82f6')
    ->showValue();

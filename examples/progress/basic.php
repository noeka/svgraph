<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::progress(value: 7400, target: 10000)
    ->color('#f59e0b')
    ->showValue();

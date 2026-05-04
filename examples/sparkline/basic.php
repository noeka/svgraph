<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::sparkline([240, 312, 280, 410, 495, 462, 530])
    ->stroke('#3b82f6')
    ->fillBelow('#3b82f6', 0.18);

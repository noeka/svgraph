<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::line([
    ['W1', 22], ['W2', 38], ['W3', 31], ['W4', 47],
    ['W5', 53], ['W6', 49], ['W7', 64], ['W8', 71],
])->axes()->grid()->smooth()->fillBelow('#3b82f6', 0.18)->stroke('#3b82f6');

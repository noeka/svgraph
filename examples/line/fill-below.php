<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::line([
    ['Jan', 120], ['Feb', 145], ['Mar', 132],
    ['Apr', 178], ['May', 196], ['Jun', 224],
])->axes()->grid()->smooth()->fillBelow('#8b5cf6', 0.2)->stroke('#8b5cf6');

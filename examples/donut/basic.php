<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::donut([
    'Stripe' => 1240,
    'PayPal' => 432,
    'Bank'   => 312,
    'Crypto' => 184,
])->legend();

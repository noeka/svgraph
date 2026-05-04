<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::donut([
    'Stripe' => 1240,
    'PayPal' => 432,
    'Bank'   => 312,
    'Crypto' => 184,
])->thickness(0.6)->gap(1.5)->legend();

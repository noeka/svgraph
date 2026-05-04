<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::bar([
    'Stripe'  => 1240,
    'PayPal'  => 432,
    'Bank'    => 312,
    'Crypto'  => 184,
    'Other'   => 96,
])->horizontal()->axes()->rounded(1)->color('#6366f1');

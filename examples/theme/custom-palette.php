<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Theme;

return Chart::pie([
    'Direct'  => 540,
    'Search'  => 320,
    'Social'  => 210,
    'Email'   => 130,
])->legend()->theme(
    Theme::default()->withPalette('#6366f1', '#f43f5e', '#0ea5e9', '#84cc16'),
);

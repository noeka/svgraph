<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;

return Chart::bar(['Q1' => 32, 'Q2' => 41, 'Q3' => 28, 'Q4' => 47])
    ->addSeries(Series::of('Costs', ['Q1' => 18, 'Q2' => 22, 'Q3' => 15, 'Q4' => 26]))
    ->addSeries(Series::of('Tax',   ['Q1' => 6,  'Q2' => 8,  'Q3' => 5,  'Q4' => 9]))
    ->stacked()
    ->axes()->grid()->rounded(1.5);

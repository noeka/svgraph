<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

final class DonutChart extends PieChart
{
    public function __construct()
    {
        parent::__construct();
        $this->variantClass = 'donut';
        $this->thickness = 0.4;
    }
}

<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

/**
 * Compact line chart variant intended for inline use behind a metric value.
 * Strips axes/grid/labels by default and uses a flatter aspect ratio.
 */
final class SparklineChart extends LineChart
{
    public function __construct()
    {
        parent::__construct();
        $this->variantClass = 'sparkline';
        $this->aspectRatio = 4.0;
        $this->fillEnabled = true;
        $this->fillOpacity = 0.15;
    }
}

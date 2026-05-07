<?php

declare(strict_types=1);

use Noeka\Svgraph\Annotations\ReferenceLine;
use Noeka\Svgraph\Chart;

// Reference lines work on bar charts too — useful for "average" or
// "target" markers above a categorical x axis.
return Chart::bar(['Jan' => 120, 'Feb' => 180, 'Mar' => 90, 'Apr' => 210, 'May' => 165])
    ->axes()->grid()->rounded(2)->color('#3b82f6')
    ->annotate(ReferenceLine::y(150)->label('Average')->color('#ef4444'));

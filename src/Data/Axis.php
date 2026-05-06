<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Data;

/**
 * Y-axis assignment for a series in a dual-axis chart. Defaults to
 * `Axis::Left`; series carrying `Axis::Right` plot against the chart's
 * secondary axis (when the chart has one enabled).
 */
enum Axis: string
{
    case Left = 'left';
    case Right = 'right';
}

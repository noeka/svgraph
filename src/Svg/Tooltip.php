<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Svg;

/**
 * A single CSS-hover tooltip to be rendered inside the chart wrapper.
 *
 * `leftPct` and `topPct` are percentages of the wrapper div's width/height,
 * matching the SVG viewBox coordinate space (viewBox is always 0 0 w h with
 * w=100, h=100 for most charts). The rendered div is offset via
 * `transform:translate(-50%,-100%)` so the tooltip's bottom-centre sits on
 * the anchor point.
 */
final class Tooltip
{
    /**
     * @param string $id      The `id` attribute value on the corresponding SVG element.
     *                        Format: `svgraph-{chartId}-s{j}-pt-{i}` for series-based charts,
     *                        `svgraph-{chartId}-pt-{i}` for slice/progress charts.
     * @param string $text    Already-HTML-escaped tooltip text to display.
     * @param float  $leftPct Left anchor as a percentage of the wrapper width.
     * @param float  $topPct  Top anchor as a percentage of the wrapper height.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $text,
        public readonly float $leftPct,
        public readonly float $topPct,
    ) {}
}

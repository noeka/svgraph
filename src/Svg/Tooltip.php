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
final readonly class Tooltip
{
    /**
     * @param string   $id      The `id` attribute value on the corresponding SVG element.
     *                          Format: `svgraph-{chartId}-s{j}-pt-{i}` for series-based charts,
     *                          `svgraph-{chartId}-pt-{i}` for slice/progress charts.
     * @param string   $text    Already-HTML-escaped tooltip text to display.
     * @param float    $leftPct Left anchor as a percentage of the wrapper width.
     * @param float    $topPct  Top anchor as a percentage of the wrapper height.
     * @param int|null $dataX   Optional shared x-column index, for line-chart crosshair
     *                          activation. When set, the rendered div carries `data-x="{n}"`
     *                          so a single column hover can reveal every series' tooltip.
     */
    public function __construct(
        public string $id,
        public string $text,
        public float $leftPct,
        public float $topPct,
        public ?int $dataX = null,
    ) {}
}

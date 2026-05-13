<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Data;

/**
 * Per-series rendering mode for point ranges supplied via `Point::$low` /
 * `Point::$high`. Modes are mutually exclusive — pick one per series via
 * `Series::withErrorBars()` or `Series::withConfidenceBand()`.
 */
enum ErrorDisplay
{
    /** No range overlay; `low`/`high` data is only surfaced in tooltips and the SR data table. */
    case None;

    /** I-bar per point: vertical line from `low` to `high` with horizontal end caps. */
    case Bars;

    /** Continuous shaded band between the polyline of `low`s and the polyline of `high`s. */
    case Band;
}

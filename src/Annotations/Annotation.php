<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Annotations;

use Noeka\Svgraph\Svg\Label;

/**
 * Base class for chart overlays — reference lines, threshold bands, target
 * zones, and callouts. Concrete annotations return SVG markup from
 * `render()` and may also contribute HTML labels via `labels()`. HTML labels
 * are positioned outside the stretched SVG so the text stays readable at
 * any chart aspect ratio.
 *
 * Annotations whose anchors fall entirely outside the visible domain return
 * an empty render and an empty labels list rather than throwing — this keeps
 * dashboards stable as data shifts under fixed-domain reference values.
 */
abstract class Annotation
{
    public function layer(): AnnotationLayer
    {
        return AnnotationLayer::BehindData;
    }

    /**
     * SVG fragment placed inside the chart's <svg> element. Returns an empty
     * string when the annotation's anchor falls outside the visible domain.
     */
    abstract public function render(AnnotationContext $context): string;

    /**
     * HTML labels rendered via the wrapper's labels overlay. Returns an
     * empty list when the annotation has no text to render.
     *
     * @return list<Label>
     */
    public function labels(AnnotationContext $context): array
    {
        return [];
    }
}

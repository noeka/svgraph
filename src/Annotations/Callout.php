<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Annotations;

use Noeka\Svgraph\Geometry\Scale;
use DateTimeInterface;
use Noeka\Svgraph\Data\Axis;
use Noeka\Svgraph\Svg\Label;
use Noeka\Svgraph\Svg\Tag;

/**
 * A text + leader-line callout pointing at a specific (x, y) coordinate.
 *
 * Renders as a small SVG dot at the anchor, a leader line out to the label
 * position, and an HTML label so the text stays sharp at any aspect ratio.
 * Callouts sit in the `OverData` z-layer so leader lines and dots are not
 * obscured by data marks.
 */
final class Callout extends Annotation
{
    private float $offsetX = 6.0;
    private float $offsetY = -6.0;
    private ?string $color = null;
    private Axis $axis = Axis::Left;

    private function __construct(
        private readonly float|DateTimeInterface $x,
        private readonly float $y,
        private readonly string $text,
    ) {}

    public static function at(float|DateTimeInterface $x, float $y, string $text): self
    {
        return new self($x, $y, $text);
    }

    /**
     * Offset of the label end of the leader line from the anchor, in viewport
     * units (the SVG's 100×100 logical box). Negative values point left/up.
     */
    public function offset(float $dx, float $dy): self
    {
        $this->offsetX = $dx;
        $this->offsetY = $dy;
        return $this;
    }

    public function color(string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function onAxis(Axis|string $axis): self
    {
        $this->axis = $axis instanceof Axis ? $axis : Axis::from($axis);
        return $this;
    }

    #[\Override]
    public function layer(): AnnotationLayer
    {
        return AnnotationLayer::OverData;
    }

    public function render(AnnotationContext $context): string
    {
        $anchor = $this->anchor($context);
        if ($anchor === null) {
            return '';
        }
        [$ax, $ay] = $anchor;
        [$tx, $ty] = $this->labelPoint($context, $ax, $ay);
        $color = $this->color ?? $context->theme->axisColor;

        $line = Tag::void('line', [
            'class' => 'svgraph-annotation-callout',
            'x1' => Tag::formatFloat($ax),
            'y1' => Tag::formatFloat($ay),
            'x2' => Tag::formatFloat($tx),
            'y2' => Tag::formatFloat($ty),
            'stroke' => $color,
            'stroke-width' => '1',
            'vector-effect' => 'non-scaling-stroke',
        ]);
        $dot = Tag::void('circle', [
            'class' => 'svgraph-annotation-callout',
            'cx' => Tag::formatFloat($ax),
            'cy' => Tag::formatFloat($ay),
            'r' => '0.6',
            'fill' => $color,
        ]);
        return $line . $dot;
    }

    #[\Override]
    public function labels(AnnotationContext $context): array
    {
        $anchor = $this->anchor($context);
        if ($anchor === null) {
            return [];
        }
        [$ax, $ay] = $anchor;
        [$tx, $ty] = $this->labelPoint($context, $ax, $ay);
        $color = $this->color ?? $context->theme->axisColor;
        $align = $this->offsetX < 0.0 ? 'end' : 'start';
        $vAlign = $this->offsetY < 0.0 ? 'bottom' : 'top';
        return [new Label(
            text: $this->text,
            left: $tx,
            top: $ty,
            align: $align,
            verticalAlign: $vAlign,
            color: $color,
        )];
    }

    /**
     * Anchor in viewport coordinates, or null when (x,y) falls outside the
     * visible domain on either axis.
     *
     * @return array{0: float, 1: float}|null
     */
    private function anchor(AnnotationContext $context): ?array
    {
        $scale = $context->yScaleFor($this->axis);
        if (!$scale instanceof Scale) {
            return null;
        }
        if (!$context->yInDomain($this->y, $this->axis)) {
            return null;
        }
        if (!$context->xInDomain($this->x)) {
            return null;
        }
        $x = $context->mapX($this->x);
        if ($x === null) {
            return null;
        }
        return [$x, $scale->map($this->y)];
    }

    /**
     * Leader-line endpoint, clamped to the plot area so the label stays inside
     * even when offsets push it past the chart edge.
     *
     * @return array{0: float, 1: float}
     */
    private function labelPoint(AnnotationContext $context, float $ax, float $ay): array
    {
        $viewport = $context->viewport;
        $tx = max($viewport->plotLeft(), min($viewport->plotRight(), $ax + $this->offsetX));
        $ty = max($viewport->plotTop(), min($viewport->plotBottom(), $ay + $this->offsetY));
        return [$tx, $ty];
    }
}

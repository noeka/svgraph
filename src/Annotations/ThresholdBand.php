<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Annotations;

use Noeka\Svgraph\Data\Axis;
use Noeka\Svgraph\Geometry\Scale;
use Noeka\Svgraph\Svg\Label;
use Noeka\Svgraph\Svg\Tag;

/**
 * A shaded horizontal band between two Y values — "healthy range",
 * tolerance window, control band.
 *
 * The band is silently clipped to the visible domain: if both bounds are on
 * the same side of the visible range the band is dropped, otherwise the
 * out-of-range edge is clamped to the plot edge so the visible portion
 * still renders.
 */
final class ThresholdBand extends Annotation
{
    private string $fillColor = 'rgba(120,120,120,0.15)';
    private ?string $labelText = null;
    private Axis $axis = Axis::Left;

    private function __construct(
        private readonly float $from,
        private readonly float $to,
    ) {}

    public static function y(float $from, float $to): self
    {
        return new self($from, $to);
    }

    public function fill(string $color): self
    {
        $this->fillColor = $color;
        return $this;
    }

    public function label(string $text): self
    {
        $this->labelText = $text;
        return $this;
    }

    public function onAxis(Axis|string $axis): self
    {
        $this->axis = $axis instanceof Axis ? $axis : Axis::from($axis);
        return $this;
    }

    public function render(AnnotationContext $context): string
    {
        $coords = $this->coordinates($context);
        if ($coords === null) {
            return '';
        }
        $viewport = $context->viewport;
        return (string) Tag::void('rect', [
            'class' => 'svgraph-annotation-band',
            'x' => Tag::formatFloat($viewport->plotLeft()),
            'y' => Tag::formatFloat($coords[0]),
            'width' => Tag::formatFloat($viewport->plotWidth()),
            'height' => Tag::formatFloat($coords[1] - $coords[0]),
            'fill' => $this->fillColor,
        ]);
    }

    #[\Override]
    public function labels(AnnotationContext $context): array
    {
        if ($this->labelText === null || $this->labelText === '') {
            return [];
        }
        $coords = $this->coordinates($context);
        if ($coords === null) {
            return [];
        }
        return [new Label(
            text: $this->labelText,
            left: $context->viewport->plotLeft() + 1,
            top: ($coords[0] + $coords[1]) / 2,
            align: 'start',
            verticalAlign: 'middle',
        )];
    }

    /**
     * Top/bottom y in viewport coords after clamping to visible domain. Null
     * if the band is entirely outside the visible range.
     *
     * @return array{0: float, 1: float}|null
     */
    private function coordinates(AnnotationContext $context): ?array
    {
        $scale = $context->yScaleFor($this->axis);
        if (!$scale instanceof Scale) {
            return null;
        }
        $domainMin = min($scale->domainMin, $scale->domainMax);
        $domainMax = max($scale->domainMin, $scale->domainMax);
        $low = min($this->from, $this->to);
        $high = max($this->from, $this->to);
        if ($high < $domainMin || $low > $domainMax) {
            return null;
        }
        $low = max($low, $domainMin);
        $high = min($high, $domainMax);
        $yLow = $scale->map($low);
        $yHigh = $scale->map($high);
        return [min($yLow, $yHigh), max($yLow, $yHigh)];
    }
}

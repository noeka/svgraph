<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Annotations;

use Noeka\Svgraph\Geometry\Scale;
use DateTimeInterface;
use Noeka\Svgraph\Geometry\TimeScale;
use Noeka\Svgraph\Svg\Label;
use Noeka\Svgraph\Svg\Tag;

/**
 * A shaded vertical band between two X values — deployment window,
 * incident, holiday, campaign run.
 *
 * Endpoints accept either floats (column index / generic linear x) or
 * `DateTimeInterface` values (when the chart is on a time axis). Bands
 * partially outside the visible domain are clipped to the plot edges; bands
 * entirely outside are dropped silently.
 */
final class TargetZone extends Annotation
{
    private string $fillColor = 'rgba(120,120,120,0.15)';
    private ?string $labelText = null;

    private function __construct(
        private readonly float|DateTimeInterface $from,
        private readonly float|DateTimeInterface $to,
    ) {}

    public static function x(float|DateTimeInterface $from, float|DateTimeInterface $to): self
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

    public function render(AnnotationContext $context): string
    {
        $coords = $this->coordinates($context);
        if ($coords === null) {
            return '';
        }
        $viewport = $context->viewport;
        return (string) Tag::void('rect', [
            'class' => 'svgraph-annotation-zone',
            'x' => Tag::formatFloat($coords[0]),
            'y' => Tag::formatFloat($viewport->plotTop()),
            'width' => Tag::formatFloat($coords[1] - $coords[0]),
            'height' => Tag::formatFloat($viewport->plotHeight()),
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
            left: ($coords[0] + $coords[1]) / 2,
            top: $context->viewport->plotTop() + 1,
            align: 'center',
            verticalAlign: 'top',
        )];
    }

    /**
     * Left/right x in viewport coords after clamping to visible domain. Null
     * if the zone is entirely outside the visible range.
     *
     * @return array{0: float, 1: float}|null
     */
    private function coordinates(AnnotationContext $context): ?array
    {
        $xScale = $context->xScale;
        if (!$xScale instanceof Scale) {
            return null;
        }
        $fromV = $this->numeric($this->from, $xScale instanceof TimeScale);
        $toV = $this->numeric($this->to, $xScale instanceof TimeScale);
        if ($fromV === null || $toV === null) {
            return null;
        }
        $domainMin = min($xScale->domainMin, $xScale->domainMax);
        $domainMax = max($xScale->domainMin, $xScale->domainMax);
        $low = min($fromV, $toV);
        $high = max($fromV, $toV);
        if ($high < $domainMin || $low > $domainMax) {
            return null;
        }
        $low = max($low, $domainMin);
        $high = min($high, $domainMax);
        $xLow = $xScale->map($low);
        $xHigh = $xScale->map($high);
        return [min($xLow, $xHigh), max($xLow, $xHigh)];
    }

    /**
     * Convert from/to into the numeric value the underlying x scale uses.
     * Returns null when a `DateTimeInterface` is paired with a non-time scale,
     * or when raw floats are paired with a time scale (mismatched input shape).
     */
    private function numeric(float|DateTimeInterface $value, bool $isTimeScale): ?float
    {
        if ($value instanceof DateTimeInterface) {
            return $isTimeScale ? (float) $value->format('U.u') : null;
        }
        return $isTimeScale ? null : $value;
    }
}

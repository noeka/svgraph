<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Annotations;

use DateTimeInterface;
use Noeka\Svgraph\Data\Axis;
use Noeka\Svgraph\Geometry\Scale;
use Noeka\Svgraph\Geometry\TimeScale;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Theme;

/**
 * Geometry handed to an annotation at render time. Holds the viewport plus
 * the chart's scales so annotations can map values into the same coordinate
 * space as the data marks.
 *
 * Charts without a meaningful x/y axis (pie, donut, progress) skip the
 * annotation pass entirely, so annotation classes can assume scales are
 * present whenever they are invoked.
 */
final readonly class AnnotationContext
{
    public function __construct(
        public Viewport $viewport,
        public Theme $theme,
        public ?Scale $xScale = null,
        public ?Scale $yScale = null,
        public ?Scale $rightYScale = null,
    ) {}

    public function yScaleFor(Axis $axis): ?Scale
    {
        return $axis === Axis::Right
            ? ($this->rightYScale ?? $this->yScale)
            : $this->yScale;
    }

    /**
     * Map an x value (column index, raw numeric, or `DateTimeInterface`) to a
     * viewport x coordinate. Returns null when the chart has no x scale, or
     * when a `DateTimeInterface` is given to a non-time scale.
     */
    public function mapX(float|DateTimeInterface $value): ?float
    {
        if (!$this->xScale instanceof Scale) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            if (!$this->xScale instanceof TimeScale) {
                return null;
            }
            return $this->xScale->mapDate($value);
        }
        return $this->xScale->map($value);
    }

    public function xInDomain(float|DateTimeInterface $value): bool
    {
        if (!$this->xScale instanceof Scale) {
            return false;
        }
        if ($value instanceof DateTimeInterface) {
            if (!$this->xScale instanceof TimeScale) {
                return false;
            }
            $v = (float) $value->format('U.u');
        } else {
            $v = $value;
        }
        return $this->between($v, $this->xScale->domainMin, $this->xScale->domainMax);
    }

    public function yInDomain(float $value, Axis $axis = Axis::Left): bool
    {
        $scale = $this->yScaleFor($axis);
        if (!$scale instanceof Scale) {
            return false;
        }
        return $this->between($value, $scale->domainMin, $scale->domainMax);
    }

    private function between(float $value, float $a, float $b): bool
    {
        $min = min($a, $b);
        $max = max($a, $b);
        return $value >= $min && $value <= $max;
    }
}

<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Geometry;

/**
 * Linear scale mapping a numeric domain [min, max] onto a coordinate range
 * [start, end]. Used for both axes. When invert=true, the domain min maps to
 * the range end (typical for y-axes since SVG y grows downward).
 */
final readonly class Scale
{
    public function __construct(
        public float $domainMin,
        public float $domainMax,
        public float $rangeStart,
        public float $rangeEnd,
        public bool $invert = false,
    ) {}

    /**
     * Build a scale, widening a degenerate (zero-width) domain by 1.0 so
     * `map()` doesn't divide by zero. Callers that want explicit control
     * should construct the scale directly.
     */
    public static function linear(float $domainMin, float $domainMax, float $rangeStart, float $rangeEnd, bool $invert = false): self
    {
        if ($domainMin === $domainMax) {
            $domainMax = $domainMin + 1.0;
        }
        return new self($domainMin, $domainMax, $rangeStart, $rangeEnd, $invert);
    }

    public function map(float $value): float
    {
        $t = ($value - $this->domainMin) / ($this->domainMax - $this->domainMin);
        if ($this->invert) {
            return $this->rangeEnd - $t * ($this->rangeEnd - $this->rangeStart);
        }
        return $this->rangeStart + $t * ($this->rangeEnd - $this->rangeStart);
    }

    /**
     * Compute a "nice" set of tick values for the current domain.
     *
     * @return list<float>
     */
    public function ticks(int $count = 5): array
    {
        if ($count < 2) {
            $count = 2;
        }
        $range = $this->domainMax - $this->domainMin;
        if ($range <= 0.0) {
            return [$this->domainMin];
        }

        $rawStep = $range / ($count - 1);
        $magnitude = 10 ** floor(log10($rawStep));
        $normalized = $rawStep / $magnitude;
        $step = match (true) {
            $normalized < 1.5 => 1 * $magnitude,
            $normalized < 3   => 2 * $magnitude,
            $normalized < 7   => 5 * $magnitude,
            default           => 10 * $magnitude,
        };

        $start = floor($this->domainMin / $step) * $step;
        $end = ceil($this->domainMax / $step) * $step;
        $ticks = [];
        for ($v = $start; $v <= $end + $step / 2; $v += $step) {
            if ($v < $this->domainMin - $step / 2 || $v > $this->domainMax + $step / 2) {
                continue;
            }
            $ticks[] = round($v, 10);
        }
        return $ticks;
    }
}

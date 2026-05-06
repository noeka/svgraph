<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Geometry;

use InvalidArgumentException;

/**
 * Logarithmic scale mapping a strictly-positive numeric domain onto a
 * coordinate range. Useful for orders-of-magnitude data (revenue across
 * decades, file sizes, request latency, …) where a linear axis would
 * collapse smaller values to a single pixel band.
 *
 * The domain endpoints and every value passed to `map()` must be > 0. Both
 * the constructor and `map()` raise `InvalidArgumentException` on
 * non-positive input — there is no "silent fallback to linear", since that
 * would mask data errors and produce a chart that lies about its axis.
 *
 * `ticks()` returns powers of the configured base (default 10) that fall
 * inside the domain, e.g. domain [1, 1000] base 10 → [1, 10, 100, 1000].
 */
final readonly class LogScale extends Scale
{
    public function __construct(
        float $domainMin,
        float $domainMax,
        float $rangeStart,
        float $rangeEnd,
        bool $invert = false,
        public float $base = 10.0,
    ) {
        if ($domainMin <= 0.0 || $domainMax <= 0.0) {
            throw new InvalidArgumentException(
                'LogScale requires a strictly positive domain; got [' . $domainMin . ', ' . $domainMax . '].',
            );
        }
        if ($base <= 1.0) {
            throw new InvalidArgumentException(
                'LogScale base must be greater than 1; got ' . $base . '.',
            );
        }
        parent::__construct($domainMin, $domainMax, $rangeStart, $rangeEnd, $invert);
    }

    /**
     * Build a log scale, widening a degenerate (zero-width) domain by one
     * decade so `map()` doesn't divide by zero. Mirrors `Scale::linear()`
     * for ergonomic parity at the call site.
     */
    public static function log(
        float $domainMin,
        float $domainMax,
        float $rangeStart,
        float $rangeEnd,
        bool $invert = false,
        float $base = 10.0,
    ): self {
        if ($domainMin === $domainMax) {
            $domainMax = $domainMin * $base;
        }
        return new self($domainMin, $domainMax, $rangeStart, $rangeEnd, $invert, $base);
    }

    #[\Override]
    public function map(float $value): float
    {
        if ($value <= 0.0) {
            throw new InvalidArgumentException(
                'LogScale cannot map non-positive value ' . $value . '.',
            );
        }
        $logMin = log($this->domainMin, $this->base);
        $logMax = log($this->domainMax, $this->base);
        $logVal = log($value, $this->base);
        $t = ($logVal - $logMin) / ($logMax - $logMin);
        if ($this->invert) {
            return $this->rangeEnd - $t * ($this->rangeEnd - $this->rangeStart);
        }
        return $this->rangeStart + $t * ($this->rangeEnd - $this->rangeStart);
    }

    /**
     * Powers of `base` lying inside the current domain. The `$count`
     * parameter is accepted for interface parity with `Scale::ticks()` —
     * tick density on a log axis is determined by the domain width, not by
     * the requested count.
     *
     * @return list<float>
     */
    #[\Override]
    public function ticks(int $count = 5): array
    {
        $low = (int) floor(log($this->domainMin, $this->base));
        $high = (int) ceil(log($this->domainMax, $this->base));
        $ticks = [];
        for ($p = $low; $p <= $high; $p++) {
            $v = $this->base ** $p;
            if ($v < $this->domainMin) {
                continue;
            }
            if ($v > $this->domainMax) {
                continue;
            }
            $ticks[] = $v;
        }
        return $ticks;
    }
}

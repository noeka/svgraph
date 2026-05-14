<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Data;

use DateTimeImmutable;

final readonly class Point
{
    /**
     * Optional `$low`/`$high` carry an uncertainty range (e.g. confidence
     * interval, min/max observed) used by `Series::withErrorBars()` and
     * `Series::withConfidenceBand()` to draw an I-bar or filled band around
     * the value. Either both must be set or both must be null.
     */
    public function __construct(
        public float $value,
        public ?string $label = null,
        public ?Link $link = null,
        public ?DateTimeImmutable $time = null,
        public ?float $low = null,
        public ?float $high = null,
    ) {}

    /** True when both `low` and `high` are finite numbers. */
    public function hasRange(): bool
    {
        return $this->low !== null && $this->high !== null;
    }

    /** Smaller of `low` / `high` (or `null` when no range is set). */
    public function rangeMin(): ?float
    {
        if (!$this->hasRange()) {
            return null;
        }

        return min((float) $this->low, (float) $this->high);
    }

    /** Larger of `low` / `high` (or `null` when no range is set). */
    public function rangeMax(): ?float
    {
        if (!$this->hasRange()) {
            return null;
        }

        return max((float) $this->low, (float) $this->high);
    }
}

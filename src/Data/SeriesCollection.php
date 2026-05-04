<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Data;

/**
 * Ordered list of Series. Lets multi-series charts share Y-scale calculations
 * (combined min/max, stacked cumulative max) and pull a common label axis
 * without each chart re-deriving the same numbers.
 *
 * Immutable: `with(Series)` returns a new collection.
 */
final readonly class SeriesCollection implements \Countable
{
    /** @var list<Series> */
    public array $items;

    /**
     * @param list<Series> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function with(Series $series): self
    {
        return new self([...$this->items, $series]);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        // A collection is empty for rendering when every series is empty.
        if ($this->items === []) {
            return true;
        }
        foreach ($this->items as $s) {
            if (!$s->isEmpty()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Length of the longest series (number of x positions).
     */
    public function maxLength(): int
    {
        $max = 0;
        foreach ($this->items as $s) {
            $n = count($s);
            if ($n > $max) {
                $max = $n;
            }
        }
        return $max;
    }

    /**
     * Smallest value across every series, or 0 if all are empty.
     */
    public function valueMin(): float
    {
        $min = INF;
        foreach ($this->items as $s) {
            if ($s->isEmpty()) {
                continue;
            }
            if ($s->min < $min) {
                $min = $s->min;
            }
        }
        return is_finite($min) ? $min : 0.0;
    }

    /**
     * Largest value across every series, or 0 if all are empty.
     */
    public function valueMax(): float
    {
        $max = -INF;
        foreach ($this->items as $s) {
            if ($s->isEmpty()) {
                continue;
            }
            if ($s->max > $max) {
                $max = $s->max;
            }
        }
        return is_finite($max) ? $max : 0.0;
    }

    /**
     * Largest cumulative sum at any single x-position, treating only
     * positive values as stacking upwards. Used to size the Y axis for
     * stacked bar charts.
     */
    public function stackedMax(): float
    {
        $length = $this->maxLength();
        if ($length === 0) {
            return 0.0;
        }
        $max = 0.0;
        for ($i = 0; $i < $length; $i++) {
            $sum = 0.0;
            foreach ($this->items as $s) {
                $v = $s->values[$i] ?? 0.0;
                if ($v > 0.0) {
                    $sum += $v;
                }
            }
            if ($sum > $max) {
                $max = $sum;
            }
        }
        return $max;
    }

    /**
     * Smallest cumulative sum at any single x-position, treating only
     * negative values as stacking downwards. Returns 0 when no negatives.
     */
    public function stackedMin(): float
    {
        $length = $this->maxLength();
        if ($length === 0) {
            return 0.0;
        }
        $min = 0.0;
        for ($i = 0; $i < $length; $i++) {
            $sum = 0.0;
            foreach ($this->items as $s) {
                $v = $s->values[$i] ?? 0.0;
                if ($v < 0.0) {
                    $sum += $v;
                }
            }
            if ($sum < $min) {
                $min = $sum;
            }
        }
        return $min;
    }

    /**
     * Pick a representative x-axis label list. Returns the labels of the
     * longest series so a chart that omits labels on shorter series still
     * shows them along the axis.
     *
     * @return list<string|null>
     */
    public function commonLabels(): array
    {
        $best = [];
        $bestLen = -1;
        foreach ($this->items as $s) {
            if (!$s->hasLabels()) {
                continue;
            }
            $len = count($s);
            if ($len > $bestLen) {
                $best = $s->labels();
                $bestLen = $len;
            }
        }
        return $best;
    }

    public function hasLabels(): bool
    {
        foreach ($this->items as $s) {
            if ($s->hasLabels()) {
                return true;
            }
        }
        return false;
    }
}

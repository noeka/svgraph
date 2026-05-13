<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Data;

use Noeka\Svgraph\Analytics\Regression;

/**
 * One data series. Carries optional metadata (`name`, `color`) so multi-series
 * charts can label and theme each series independently.
 *
 * Aggregates (values/min/max/sum) are computed once at construction so the
 * render path can read them without re-walking `points`.
 */
final readonly class Series implements \Countable
{
    /** @var list<float> */
    public array $values;

    public float $min;
    public float $max;
    public float $sum;

    /**
     * Combined data bounds — extends `min`/`max` to include `Point::$low` and
     * `Point::$high` so the chart's Y axis fits any error overlay even when
     * the user hasn't toggled `withErrorBars()` / `withConfidenceBand()`.
     * Equal to `min`/`max` when no point carries a range.
     */
    public float $boundsMin;
    public float $boundsMax;

    /**
     * @param list<Point> $points
     */
    public function __construct(
        public array $points,
        public string $name = '',
        public ?string $color = null,
        public Axis $axis = Axis::Left,
        public bool $showTrend = false,
        public ErrorDisplay $errorDisplay = ErrorDisplay::None,
    ) {
        $values = [];
        $min = INF;
        $max = -INF;
        $boundsMin = INF;
        $boundsMax = -INF;
        $sum = 0.0;
        foreach ($this->points as $p) {
            $v = $p->value;
            $values[] = $v;
            if ($v < $min) {
                $min = $v;
            }
            if ($v > $max) {
                $max = $v;
            }
            if ($v < $boundsMin) {
                $boundsMin = $v;
            }
            if ($v > $boundsMax) {
                $boundsMax = $v;
            }
            $rangeMin = $p->rangeMin();
            $rangeMax = $p->rangeMax();
            if ($rangeMin !== null && $rangeMin < $boundsMin) {
                $boundsMin = $rangeMin;
            }
            if ($rangeMax !== null && $rangeMax > $boundsMax) {
                $boundsMax = $rangeMax;
            }
            $sum += $v;
        }
        $this->values = $values;
        $this->min = $values === [] ? 0.0 : $min;
        $this->max = $values === [] ? 0.0 : $max;
        $this->boundsMin = $values === [] ? 0.0 : $boundsMin;
        $this->boundsMax = $values === [] ? 0.0 : $boundsMax;
        $this->sum = $sum;
    }

    /**
     * Normalise the input shapes accepted by Series::from() and attach a name
     * and optional color. Convenience for fluent builders:
     *
     *   $chart->addSeries(Series::of('Revenue', $data, '#3b82f6'));
     *
     * @param iterable<mixed> $data
     */
    public static function of(string $name, iterable $data, ?string $color = null): self
    {
        return new self(self::normalise($data), $name, $color);
    }

    /**
     * Accept several input shapes and normalize to a Series.
     *
     * - [10, 24, 18]                       → unlabelled points
     * - [['Mon', 10], ['Tue', 24]]         → label + value tuples
     * - ['Mon' => 10, 'Tue' => 24]         → label => value map
     * - [[DateTimeImmutable, 10], …]       → time-keyed tuples (drives TimeScale)
     * - [Point, Point]                     → already Points
     *
     * Non-finite values (NaN, ±Infinity) are silently dropped — they would
     * otherwise propagate through Scale calculations and produce a chart
     * full of zeros.
     *
     * @param iterable<mixed> $input
     */
    public static function from(iterable $input): self
    {
        return new self(self::normalise($input));
    }

    /**
     * @param iterable<mixed> $input
     * @return list<Point>
     */
    private static function normalise(iterable $input): array
    {
        $points = [];
        foreach ($input as $key => $value) {
            if ($value instanceof Point) {
                if (is_finite($value->value)) {
                    $points[] = $value;
                }
                continue;
            }
            if (is_array($value) && count($value) >= 2) {
                $arr = array_values($value);
                $val = self::toFloat($arr[1]);
                if (!is_finite($val)) {
                    continue;
                }
                $link = isset($arr[2]) && $arr[2] instanceof Link ? $arr[2] : null;
                $rangeStart = $link instanceof Link ? 3 : 2;
                $low = self::toRangeFloat($arr[$rangeStart] ?? null);
                $high = self::toRangeFloat($arr[$rangeStart + 1] ?? null);
                if ($low === null || $high === null) {
                    $low = null;
                    $high = null;
                }
                $time = self::toTime($arr[0]);
                $label = $time instanceof \DateTimeImmutable ? null : self::toLabel($arr[0]);
                $points[] = new Point($val, $label, $link, $time, $low, $high);
                continue;
            }
            $val = self::toFloat($value);
            if (!is_finite($val)) {
                continue;
            }
            $points[] = new Point($val, is_string($key) ? $key : null);
        }
        return $points;
    }

    private static function toTime(mixed $v): ?\DateTimeImmutable
    {
        if ($v instanceof \DateTimeImmutable) {
            return $v;
        }
        if ($v instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($v);
        }
        return null;
    }

    private static function toFloat(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : NAN;
    }

    /**
     * Tolerant float coercion for optional range slots: returns null for
     * missing or non-finite values rather than NAN, so range slots can be
     * absent without aborting tuple parsing.
     */
    private static function toRangeFloat(mixed $v): ?float
    {
        if ($v === null || !is_numeric($v)) {
            return null;
        }
        $f = (float) $v;
        return is_finite($f) ? $f : null;
    }

    private static function toLabel(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        return is_scalar($v) ? (string) $v : null;
    }

    public function withName(string $name): self
    {
        return new self($this->points, $name, $this->color, $this->axis, $this->showTrend, $this->errorDisplay);
    }

    public function withColor(?string $color): self
    {
        return new self($this->points, $this->name, $color, $this->axis, $this->showTrend, $this->errorDisplay);
    }

    /**
     * Assign this series to the chart's left or right Y axis. Accepts an
     * `Axis` case or its string equivalent (`'left'`, `'right'`) for
     * caller convenience. Has no visual effect unless the chart has a
     * secondary axis enabled (see `LineChart::secondaryAxis()`).
     */
    public function onAxis(Axis|string $axis): self
    {
        $resolved = $axis instanceof Axis ? $axis : Axis::from($axis);
        return new self($this->points, $this->name, $this->color, $resolved, $this->showTrend, $this->errorDisplay);
    }

    /**
     * Toggle linear regression trend overlay rendering for this series.
     *
     * The overlay is a separate dashed half-opacity line drawn on top of
     * the raw data, clipped to the data's x-range (not extrapolated). Has
     * no effect on chart types that don't render trend lines (e.g. pie).
     *
     * The trend's slope, intercept and R² are always available via
     * `trendStats()` regardless of this flag.
     */
    public function withTrendLine(bool $on = true): self
    {
        return new self($this->points, $this->name, $this->color, $this->axis, $on, $this->errorDisplay);
    }

    /**
     * Overlay an I-bar at each point: a vertical line from `Point::$low` to
     * `Point::$high` with horizontal caps. Mutually exclusive with
     * `withConfidenceBand()` — the last call wins.
     *
     * Points without a range (no `low`/`high`) silently skip emission, so a
     * mixed series renders bars only where the data carries them. Has no
     * visual effect when no point in the series carries a range.
     */
    public function withErrorBars(bool $on = true): self
    {
        $mode = $on ? ErrorDisplay::Bars : ErrorDisplay::None;
        return new self($this->points, $this->name, $this->color, $this->axis, $this->showTrend, $mode);
    }

    /**
     * Overlay a filled band between the polyline of lows and the polyline of
     * highs. Mutually exclusive with `withErrorBars()` — the last call wins.
     *
     * The band uses the resolved series color at the theme's
     * `confidenceBandOpacity`. Points without a range are excluded from the
     * polyline rather than drawn at zero (the band still renders if at least
     * two contiguous points carry a range).
     */
    public function withConfidenceBand(bool $on = true): self
    {
        $mode = $on ? ErrorDisplay::Band : ErrorDisplay::None;
        return new self($this->points, $this->name, $this->color, $this->axis, $this->showTrend, $mode);
    }

    /** True when any point in the series carries a finite `low`/`high` range. */
    public function hasRangeData(): bool
    {
        foreach ($this->points as $p) {
            if ($p->hasRange()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Linear regression statistics for this series using point indices
     * (0, 1, …, n-1) as x and series values as y.
     *
     * Returns `null` when the series has fewer than two finite points; the
     * caller (chart or user code) decides how to surface that — typically
     * by skipping the trend overlay.
     *
     * @return array{slope: float, intercept: float, r2: float}|null
     */
    public function trendStats(): ?array
    {
        $count = count($this->values);
        if ($count < 2) {
            return null;
        }
        $pairs = [];
        foreach ($this->values as $i => $v) {
            $pairs[] = [(float) $i, $v];
        }
        return Regression::linear($pairs);
    }

    public function count(): int
    {
        return count($this->points);
    }

    public function isEmpty(): bool
    {
        return $this->points === [];
    }

    public function min(): float
    {
        return $this->min;
    }

    public function max(): float
    {
        return $this->max;
    }

    public function sum(): float
    {
        return $this->sum;
    }

    /** @return list<float> */
    public function values(): array
    {
        return $this->values;
    }

    /** @return list<string|null> */
    public function labels(): array
    {
        return array_map(static fn(Point $p): ?string => $p->label, $this->points);
    }

    public function hasLabels(): bool
    {
        foreach ($this->points as $p) {
            if ($p->label !== null && $p->label !== '') {
                return true;
            }
        }
        return false;
    }
}

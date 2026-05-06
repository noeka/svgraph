<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Data;

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
     * @param list<Point> $points
     */
    public function __construct(
        public array $points,
        public string $name = '',
        public ?string $color = null,
    ) {
        $values = [];
        $min = INF;
        $max = -INF;
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
            $sum += $v;
        }
        $this->values = $values;
        $this->min = $values === [] ? 0.0 : $min;
        $this->max = $values === [] ? 0.0 : $max;
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
                $time = self::toTime($arr[0]);
                $label = $time instanceof \DateTimeImmutable ? null : self::toLabel($arr[0]);
                $points[] = new Point($val, $label, $link, $time);
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

    private static function toLabel(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        return is_scalar($v) ? (string) $v : null;
    }

    public function withName(string $name): self
    {
        return new self($this->points, $name, $this->color);
    }

    public function withColor(?string $color): self
    {
        return new self($this->points, $this->name, $color);
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

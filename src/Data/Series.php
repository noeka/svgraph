<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Data;

final readonly class Series implements \Countable
{
    /** @var list<Point> */
    public array $points;

    /**
     * @param list<Point> $points
     */
    public function __construct(array $points)
    {
        $this->points = $points;
    }

    /**
     * Accept several input shapes and normalize to a Series.
     *
     * - [10, 24, 18]                       → unlabelled points
     * - [['Mon', 10], ['Tue', 24]]         → label + value tuples
     * - ['Mon' => 10, 'Tue' => 24]         → label => value map
     * - [Point, Point]                     → already Points
     *
     * @param iterable<mixed> $input
     */
    public static function from(iterable $input): self
    {
        $points = [];
        foreach ($input as $key => $value) {
            if ($value instanceof Point) {
                $points[] = $value;
                continue;
            }
            if (is_array($value) && count($value) === 2) {
                [$label, $val] = array_values($value);
                $points[] = new Point((float) $val, $label === null ? null : (string) $label);
                continue;
            }
            if (is_int($key)) {
                $points[] = new Point((float) $value);
            } else {
                $points[] = new Point((float) $value, (string) $key);
            }
        }
        return new self($points);
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
        $values = array_map(static fn(Point $p) => $p->value, $this->points);
        return $values !== [] ? min($values) : 0.0;
    }

    public function max(): float
    {
        $values = array_map(static fn(Point $p) => $p->value, $this->points);
        return $values !== [] ? max($values) : 0.0;
    }

    public function sum(): float
    {
        return array_sum(array_map(static fn(Point $p) => $p->value, $this->points));
    }

    /** @return list<float> */
    public function values(): array
    {
        return array_map(static fn(Point $p) => $p->value, $this->points);
    }

    /** @return list<string|null> */
    public function labels(): array
    {
        return array_map(static fn(Point $p) => $p->label, $this->points);
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

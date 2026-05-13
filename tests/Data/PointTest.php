<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Data;

use Noeka\Svgraph\Data\Point;
use PHPUnit\Framework\TestCase;

final class PointTest extends TestCase
{
    public function test_construct_with_value_only(): void
    {
        $p = new Point(3.14);
        self::assertSame(3.14, $p->value);
        self::assertNull($p->label);
    }

    public function test_construct_with_value_and_label(): void
    {
        $p = new Point(42.0, 'answer');
        self::assertSame(42.0, $p->value);
        self::assertSame('answer', $p->label);
    }

    public function test_range_helpers_return_null_when_unset(): void
    {
        $p = new Point(10.0);
        self::assertFalse($p->hasRange());
        self::assertNull($p->rangeMin());
        self::assertNull($p->rangeMax());
    }

    public function test_range_helpers_when_both_set(): void
    {
        $p = new Point(10.0, low: 5.0, high: 15.0);
        self::assertTrue($p->hasRange());
        self::assertSame(5.0, $p->rangeMin());
        self::assertSame(15.0, $p->rangeMax());
    }

    public function test_range_helpers_normalise_swapped_low_high(): void
    {
        // Caller-supplied low/high can land in either order; the helpers
        // normalise so consumers don't have to handle the swap.
        $p = new Point(10.0, low: 15.0, high: 5.0);
        self::assertTrue($p->hasRange());
        self::assertSame(5.0, $p->rangeMin());
        self::assertSame(15.0, $p->rangeMax());
    }

    public function test_range_inactive_when_only_one_bound_set(): void
    {
        self::assertFalse((new Point(10.0, low: 5.0))->hasRange());
        self::assertFalse((new Point(10.0, high: 15.0))->hasRange());
    }
}

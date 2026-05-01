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
}

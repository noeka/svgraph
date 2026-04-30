<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Data;

use Noeka\Svgraph\Data\Slice;
use PHPUnit\Framework\TestCase;

final class SliceTest extends TestCase
{
    public function test_from_associative_map(): void
    {
        $slices = Slice::listFrom(['Stripe' => 1240, 'PayPal' => 432]);
        self::assertCount(2, $slices);
        self::assertSame('Stripe', $slices[0]->label);
        self::assertSame(1240.0, $slices[0]->value);
        self::assertNull($slices[0]->color);
        self::assertSame('PayPal', $slices[1]->label);
    }

    public function test_from_tuples_with_optional_color(): void
    {
        $slices = Slice::listFrom([
            ['Stripe', 1240],
            ['PayPal', 432, '#10b981'],
        ]);
        self::assertNull($slices[0]->color);
        self::assertSame('#10b981', $slices[1]->color);
    }

    public function test_from_slice_objects_passes_through(): void
    {
        $s1 = new Slice('A', 100.0, '#ff0000');
        $s2 = new Slice('B', 200.0);
        $result = Slice::listFrom([$s1, $s2]);
        self::assertSame($s1, $result[0]);
        self::assertSame($s2, $result[1]);
    }
}

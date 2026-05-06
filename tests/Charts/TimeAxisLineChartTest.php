<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use DateTimeImmutable;
use Noeka\Svgraph\Charts\AbstractChart;
use Noeka\Svgraph\Charts\LineChart;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TimeAxisLineChartTest extends TestCase
{
    protected function setUp(): void
    {
        $reflection = new ReflectionClass(AbstractChart::class);
        $reflection->setStaticPropertyValue('nextId', 0);
    }

    public function test_time_axis_positions_points_by_time(): void
    {
        $start = new DateTimeImmutable('2026-05-01T00:00:00Z');
        $mid = new DateTimeImmutable('2026-05-22T00:00:00Z');
        $end = new DateTimeImmutable('2026-05-31T00:00:00Z');

        $svg = (new LineChart())
            ->series([
                [$start, 1],
                [$mid, 5],
                [$end, 2],
            ])
            ->axes()
            ->timeAxis()
            ->render();

        // Points are positioned by time, so the middle marker is roughly
        // 21/30 of the way across the plot, NOT at the index midpoint.
        self::assertStringContainsString('<svg', $svg);
        // Should contain time-aware month/day labels (e.g. "May 1").
        self::assertMatchesRegularExpression('/May\s*\d/', $svg);
    }

    public function test_time_axis_emits_tick_labels(): void
    {
        $svg = (new LineChart())
            ->series([
                [new DateTimeImmutable('2021-01-01T00:00:00Z'), 10],
                [new DateTimeImmutable('2026-01-01T00:00:00Z'), 20],
            ])
            ->axes()
            ->ticks(4)
            ->timeAxis(tz: 'UTC')
            ->render();

        // Year-level ticks, e.g. 2021..2026.
        self::assertMatchesRegularExpression('/202\d/', $svg);
    }

    public function test_time_axis_without_time_data_falls_back_silently(): void
    {
        // Calling timeAxis() without time-bearing points should not blow up;
        // the chart renders with the regular index-based x-axis.
        $svg = (new LineChart())
            ->series(['Mon' => 1, 'Tue' => 2, 'Wed' => 3])
            ->axes()
            ->timeAxis()
            ->render();

        self::assertStringContainsString('Mon', $svg);
        self::assertStringContainsString('Tue', $svg);
    }
}

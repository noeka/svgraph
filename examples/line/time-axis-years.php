<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

return Chart::line([
    [new DateTimeImmutable('2021-01-01T00:00:00Z'),  74],
    [new DateTimeImmutable('2022-01-01T00:00:00Z'),  92],
    [new DateTimeImmutable('2023-01-01T00:00:00Z'), 121],
    [new DateTimeImmutable('2024-01-01T00:00:00Z'), 158],
    [new DateTimeImmutable('2025-01-01T00:00:00Z'), 184],
    [new DateTimeImmutable('2026-01-01T00:00:00Z'), 213],
])
    ->axes()
    ->grid()
    ->points()
    ->smooth()
    ->ticks(6)
    ->timeAxis(tz: 'UTC')
    ->stroke('#10b981')
    ->fillBelow('#10b981', 0.15);

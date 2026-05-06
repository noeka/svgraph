<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

$base = new DateTimeImmutable('2026-01-01T00:00:00Z');
$points = [];
$value = 100.0;
for ($i = 0; $i < 30; $i++) {
    $value += sin($i / 3) * 6 + ($i % 5 === 0 ? 4 : -1);
    $points[] = [$base->modify("+{$i} days"), round($value, 1)];
}

return Chart::line($points)
    ->axes()
    ->grid()
    ->timeAxis(tz: 'UTC')
    ->stroke('#3b82f6');

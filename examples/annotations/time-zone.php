<?php

declare(strict_types=1);

use Noeka\Svgraph\Annotations\TargetZone;
use Noeka\Svgraph\Chart;

// On a time axis, TargetZone::x() accepts DateTimeImmutable endpoints.
$base = new DateTimeImmutable('2026-01-01T00:00:00Z');
$points = [];
$value = 100.0;
for ($i = 0; $i < 30; $i++) {
    $value += sin($i / 3) * 6 + ($i % 5 === 0 ? 4 : -1);
    $points[] = [$base->modify("+{$i} days"), round($value, 1)];
}

return Chart::line($points)
    ->axes()->grid()->timeAxis(tz: 'UTC')->stroke('#3b82f6')
    ->annotate(
        TargetZone::x(
            new DateTimeImmutable('2026-01-08T00:00:00Z'),
            new DateTimeImmutable('2026-01-15T00:00:00Z'),
        )
            ->fill('#f59e0b22')
            ->label('Incident'),
    );

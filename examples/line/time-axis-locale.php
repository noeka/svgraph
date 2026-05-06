<?php

declare(strict_types=1);

use Noeka\Svgraph\Chart;

$base = new DateTimeImmutable('2026-05-01T09:00:00+02:00');
$points = [];
for ($i = 0; $i < 12; $i++) {
    $points[] = [
        $base->modify("+{$i} hours"),
        round(20 + 10 * sin($i / 2) + ($i * 0.7), 1),
    ];
}

return Chart::line($points)
    ->axes()
    ->grid()
    ->timeAxis(locale: 'fr_FR', tz: 'Europe/Paris')
    ->stroke('#8b5cf6');

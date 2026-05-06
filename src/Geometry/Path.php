<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Geometry;

use Noeka\Svgraph\Svg\Tag;

/**
 * Builds SVG path "d" attribute strings from coordinate lists.
 */
final class Path
{
    /**
     * Straight-line polyline.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    public static function line(array $points): string
    {
        if ($points === []) {
            return '';
        }
        $parts = [];
        foreach ($points as $i => [$x, $y]) {
            $parts[] = ($i === 0 ? 'M' : 'L') . Tag::formatFloat($x) . ',' . Tag::formatFloat($y);
        }
        return implode(' ', $parts);
    }

    /**
     * Smoothed line using cubic Bezier with simple tangent control points.
     * Tangents at local y-extrema are flattened so the curve doesn't bulge
     * past peaks and troughs in the data.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    public static function smoothLine(array $points, float $tension = 0.35): string
    {
        $count = count($points);
        if ($count === 0) {
            return '';
        }
        if ($count < 3) {
            return self::line($points);
        }

        $tangents = [];
        for ($i = 0; $i < $count; $i++) {
            $prev = $points[max(0, $i - 1)];
            $next = $points[min($count - 1, $i + 1)];
            $tx = $next[0] - $prev[0];
            $ty = $next[1] - $prev[1];

            if ($i > 0 && $i < $count - 1) {
                $here = $points[$i][1];
                $dPrev = $points[$i - 1][1] - $here;
                $dNext = $points[$i + 1][1] - $here;
                if ($dPrev * $dNext >= 0) {
                    $ty = 0.0;
                }
            }
            $tangents[$i] = [$tx, $ty];
        }

        $parts = ['M' . Tag::formatFloat($points[0][0]) . ',' . Tag::formatFloat($points[0][1])];
        for ($i = 0; $i < $count - 1; $i++) {
            $p1 = $points[$i];
            $p2 = $points[$i + 1];
            [$t1x, $t1y] = $tangents[$i];
            [$t2x, $t2y] = $tangents[$i + 1];

            $c1x = $p1[0] + $t1x * $tension;
            $c1y = $p1[1] + $t1y * $tension;
            $c2x = $p2[0] - $t2x * $tension;
            $c2y = $p2[1] - $t2y * $tension;

            $parts[] = 'C' . Tag::formatFloat($c1x) . ',' . Tag::formatFloat($c1y)
                . ' ' . Tag::formatFloat($c2x) . ',' . Tag::formatFloat($c2y)
                . ' ' . Tag::formatFloat($p2[0]) . ',' . Tag::formatFloat($p2[1]);
        }
        return implode(' ', $parts);
    }

    /**
     * Closed area path: line across the top, then drop to baseline and close.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    public static function area(array $points, float $baselineY, bool $smooth = false): string
    {
        if ($points === []) {
            return '';
        }
        $top = $smooth ? self::smoothLine($points) : self::line($points);
        $last = $points[count($points) - 1];
        $first = $points[0];
        return $top
            . ' L' . Tag::formatFloat($last[0]) . ',' . Tag::formatFloat($baselineY)
            . ' L' . Tag::formatFloat($first[0]) . ',' . Tag::formatFloat($baselineY)
            . ' Z';
    }

    /**
     * Pie/donut slice as a single path "d". Angles are in radians, measured
     * clockwise from the 12 o'clock position. innerRadius=0 produces a pie wedge;
     * non-zero produces a donut ring segment.
     */
    public static function arc(
        float $cx,
        float $cy,
        float $outerRadius,
        float $innerRadius,
        float $startAngle,
        float $endAngle,
    ): string {
        $sweep = $endAngle - $startAngle;
        // Full-circle case: emit two semicircles to avoid the degenerate
        // "start == end" arc that browsers render as nothing.
        if (abs($sweep) >= 2 * M_PI - 1e-6) {
            return self::fullRing($cx, $cy, $outerRadius, $innerRadius);
        }

        $largeArc = $sweep > M_PI ? 1 : 0;

        [$x1, $y1] = self::polar($cx, $cy, $outerRadius, $startAngle);
        [$x2, $y2] = self::polar($cx, $cy, $outerRadius, $endAngle);

        $f = static fn(float $v): string => Tag::formatFloat($v);

        if ($innerRadius <= 0.0) {
            return "M{$f($cx)},{$f($cy)} L{$f($x1)},{$f($y1)} "
                . "A{$f($outerRadius)},{$f($outerRadius)} 0 {$largeArc} 1 {$f($x2)},{$f($y2)} Z";
        }

        [$x3, $y3] = self::polar($cx, $cy, $innerRadius, $endAngle);
        [$x4, $y4] = self::polar($cx, $cy, $innerRadius, $startAngle);

        return "M{$f($x1)},{$f($y1)} "
            . "A{$f($outerRadius)},{$f($outerRadius)} 0 {$largeArc} 1 {$f($x2)},{$f($y2)} "
            . "L{$f($x3)},{$f($y3)} "
            . "A{$f($innerRadius)},{$f($innerRadius)} 0 {$largeArc} 0 {$f($x4)},{$f($y4)} Z";
    }

    private static function fullRing(float $cx, float $cy, float $outerRadius, float $innerRadius): string
    {
        $f = static fn(float $v): string => Tag::formatFloat($v);
        $top = $cy - $outerRadius;
        $bottom = $cy + $outerRadius;
        $outer = "M{$f($cx)},{$f($top)} "
            . "A{$f($outerRadius)},{$f($outerRadius)} 0 1 1 {$f($cx)},{$f($bottom)} "
            . "A{$f($outerRadius)},{$f($outerRadius)} 0 1 1 {$f($cx)},{$f($top)} Z";

        if ($innerRadius <= 0.0) {
            return $outer;
        }

        $iTop = $cy - $innerRadius;
        $iBottom = $cy + $innerRadius;
        $inner = "M{$f($cx)},{$f($iTop)} "
            . "A{$f($innerRadius)},{$f($innerRadius)} 0 1 0 {$f($cx)},{$f($iBottom)} "
            . "A{$f($innerRadius)},{$f($innerRadius)} 0 1 0 {$f($cx)},{$f($iTop)} Z";
        return $outer . ' ' . $inner;
    }

    /**
     * Convert a polar coordinate (clockwise from 12 o'clock) to cartesian.
     *
     * @return array{0: float, 1: float}
     */
    public static function polar(float $cx, float $cy, float $radius, float $angle): array
    {
        $x = $cx + $radius * sin($angle);
        $y = $cy - $radius * cos($angle);
        return [$x, $y];
    }
}

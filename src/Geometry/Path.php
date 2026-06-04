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
     * Smoothed line using monotone cubic Hermite interpolation (Fritsch-Carlson).
     *
     * Tangents are derived from the data slopes and clamped so the curve is
     * guaranteed monotone between samples: it never overshoots a data point and
     * rounds peaks/troughs tightly instead of flattening into wide plateaus.
     * This is the same scheme as D3's `curveMonotoneX`.
     *
     * Assumes x is monotone across the points (as line-chart series are). The
     * x-coordinates may run either direction, so the reversed polyline used by
     * `band()` is handled too. If two consecutive points share an x-coordinate
     * the data is not x-monotone and we fall back to a straight polyline.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    public static function smoothLine(array $points): string
    {
        $count = count($points);

        if ($count === 0) {
            return '';
        }

        if ($count < 3) {
            return self::line($points);
        }

        // Secant slopes and x-widths of each interval.
        $slopes = [];
        $widths = [];

        for ($k = 0; $k < $count - 1; $k++) {
            $h = $points[$k + 1][0] - $points[$k][0];

            if ($h === 0.0) {
                return self::line($points);
            }

            $widths[$k] = $h;
            $slopes[$k] = ($points[$k + 1][1] - $points[$k][1]) / $h;
        }

        // Tangents: endpoints take the adjacent secant; interior points take the
        // mean secant, or zero at a local extremum (sign change / flat).
        $tangents = [$slopes[0]];

        for ($i = 1; $i < $count - 1; $i++) {
            $tangents[$i] = $slopes[$i - 1] * $slopes[$i] <= 0.0
                ? 0.0
                : ($slopes[$i - 1] + $slopes[$i]) / 2.0;
        }

        $tangents[$count - 1] = $slopes[$count - 2];

        // Fritsch-Carlson clamp: keep each (alpha, beta) inside the radius-3
        // circle so the segment stays monotone and cannot overshoot.
        for ($k = 0; $k < $count - 1; $k++) {
            if ($slopes[$k] === 0.0) {
                $tangents[$k] = 0.0;
                $tangents[$k + 1] = 0.0;

                continue;
            }

            $alpha = $tangents[$k] / $slopes[$k];
            $beta = $tangents[$k + 1] / $slopes[$k];
            $sum = $alpha * $alpha + $beta * $beta;

            if ($sum > 9.0) {
                $tau = 3.0 / sqrt($sum);
                $tangents[$k] = $tau * $alpha * $slopes[$k];
                $tangents[$k + 1] = $tau * $beta * $slopes[$k];
            }
        }

        $parts = ['M' . Tag::formatFloat($points[0][0]) . ',' . Tag::formatFloat($points[0][1])];

        for ($k = 0; $k < $count - 1; $k++) {
            $p1 = $points[$k];
            $p2 = $points[$k + 1];
            $third = $widths[$k] / 3.0;

            $c1x = $p1[0] + $third;
            $c1y = $p1[1] + $tangents[$k] * $third;
            $c2x = $p2[0] - $third;
            $c2y = $p2[1] - $tangents[$k + 1] * $third;

            $parts[] = 'C' . Tag::formatFloat($c1x) . ',' . Tag::formatFloat($c1y)
                . ' ' . Tag::formatFloat($c2x) . ',' . Tag::formatFloat($c2y)
                . ' ' . Tag::formatFloat($p2[0]) . ',' . Tag::formatFloat($p2[1]);
        }

        return implode(' ', $parts);
    }

    /**
     * Closed band path: forward polyline through `$lowPoints`, joined to a
     * reversed polyline through `$highPoints`. Both polylines may be smoothed
     * with the same cubic interpolation as `line()`/`smoothLine()`.
     *
     * Used by line charts to draw confidence intervals around a series. The
     * two arrays must have the same length and share x-coordinates per index
     * for the band to align with the underlying value polyline.
     *
     * @param list<array{0: float, 1: float}> $lowPoints
     * @param list<array{0: float, 1: float}> $highPoints
     */
    public static function band(array $lowPoints, array $highPoints, bool $smooth = false): string
    {
        if ($lowPoints === [] || $highPoints === []) {
            return '';
        }

        $reversedHighs = array_reverse($highPoints);

        $forward = $smooth ? self::smoothLine($lowPoints) : self::line($lowPoints);
        $backward = $smooth ? self::smoothLine($reversedHighs) : self::line($reversedHighs);

        // Replace the second polyline's leading "M" with "L" so the path joins
        // rather than starting a new sub-path.
        $backwardJoined = 'L' . substr($backward, 1);

        return $forward . ' ' . $backwardJoined . ' Z';
    }

    /**
     * Concatenated I-bar paths for a list of error bars. Each bar is a
     * vertical stroke from `lowY` to `highY` at `x`, plus a horizontal cap
     * of total width `2 * halfCap` at each end.
     *
     * Returns the empty string when `$bars` is empty so callers can skip the
     * `<path>` emission entirely.
     *
     * @param list<array{0: float, 1: float, 2: float}> $bars  Each [x, lowY, highY].
     */
    public static function errorBars(array $bars, float $halfCap): string
    {
        if ($bars === []) {
            return '';
        }

        $f = static fn(float $v): string => Tag::formatFloat($v);

        $parts = [];

        foreach ($bars as [$x, $lowY, $highY]) {
            $left = $x - $halfCap;
            $right = $x + $halfCap;

            $parts[] = "M{$f($x)},{$f($lowY)} L{$f($x)},{$f($highY)}";
            $parts[] = "M{$f($left)},{$f($lowY)} L{$f($right)},{$f($lowY)}";
            $parts[] = "M{$f($left)},{$f($highY)} L{$f($right)},{$f($highY)}";
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

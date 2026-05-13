<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Analytics;

use InvalidArgumentException;

/**
 * Statistical helpers used by chart trend overlays.
 *
 * Pure math — no rendering, no svg, no theme. Designed so callers can also
 * use it standalone (e.g. to surface slope or R² in their own UI).
 */
final class Regression
{
    /**
     * Ordinary least-squares linear regression over a list of (x, y) points.
     *
     * Returns the slope/intercept of the best-fit line and the coefficient of
     * determination (R²). The computation uses the mean-centred form
     * (`Σ(x-x̄)(y-ȳ) / Σ(x-x̄)²`) which is more numerically stable than the
     * raw sums variant on large inputs.
     *
     * For a perfectly constant `y` (zero variance) the model fits exactly,
     * so R² is reported as `1.0` rather than `NaN` from a 0/0 division.
     *
     * @param list<array{0: float|int, 1: float|int}> $points
     *
     * @return array{slope: float, intercept: float, r2: float}
     *
     * @throws InvalidArgumentException When fewer than 2 points are supplied
     *                                  or every x value is identical (no
     *                                  unique line passes through a vertical
     *                                  set of points).
     */
    public static function linear(array $points): array
    {
        $n = count($points);
        if ($n < 2) {
            throw new InvalidArgumentException('Linear regression requires at least 2 points.');
        }

        $sumX = 0.0;
        $sumY = 0.0;
        foreach ($points as $p) {
            $sumX += (float) $p[0];
            $sumY += (float) $p[1];
        }
        $meanX = $sumX / $n;
        $meanY = $sumY / $n;

        $ssXX = 0.0;
        $ssXY = 0.0;
        $ssYY = 0.0;
        foreach ($points as $p) {
            $dx = (float) $p[0] - $meanX;
            $dy = (float) $p[1] - $meanY;
            $ssXX += $dx * $dx;
            $ssXY += $dx * $dy;
            $ssYY += $dy * $dy;
        }

        if ($ssXX === 0.0) {
            throw new InvalidArgumentException(
                'Linear regression requires at least two distinct x values.',
            );
        }

        $slope = $ssXY / $ssXX;
        $intercept = $meanY - $slope * $meanX;
        // Constant y: best-fit line is horizontal and passes through every
        // point exactly, so the model fully explains the (zero) variance.
        $r2 = $ssYY === 0.0 ? 1.0 : ($ssXY * $ssXY) / ($ssXX * $ssYY);

        return ['slope' => $slope, 'intercept' => $intercept, 'r2' => $r2];
    }
}

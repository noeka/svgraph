<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Analytics;

use InvalidArgumentException;
use Noeka\Svgraph\Analytics\Regression;
use PHPUnit\Framework\TestCase;

final class RegressionTest extends TestCase
{
    public function test_matches_known_dataset_within_tolerance(): void
    {
        // x = [1..5], y = [2, 4, 5, 4, 5] — slope 0.6, intercept 2.2, r² 0.6.
        // Verified independently against NumPy `numpy.polyfit` and Excel SLOPE/INTERCEPT/RSQ.
        $stats = Regression::linear([
            [1.0, 2.0],
            [2.0, 4.0],
            [3.0, 5.0],
            [4.0, 4.0],
            [5.0, 5.0],
        ]);

        self::assertEqualsWithDelta(0.6, $stats['slope'], 1e-9);
        self::assertEqualsWithDelta(2.2, $stats['intercept'], 1e-9);
        self::assertEqualsWithDelta(0.6, $stats['r2'], 1e-9);
    }

    public function test_perfect_positive_correlation_returns_r2_of_one(): void
    {
        $stats = Regression::linear([
            [0.0, 0.0],
            [1.0, 2.0],
            [2.0, 4.0],
            [3.0, 6.0],
        ]);

        self::assertEqualsWithDelta(2.0, $stats['slope'], 1e-12);
        self::assertEqualsWithDelta(0.0, $stats['intercept'], 1e-12);
        self::assertEqualsWithDelta(1.0, $stats['r2'], 1e-12);
    }

    public function test_perfect_negative_correlation_returns_r2_of_one(): void
    {
        $stats = Regression::linear([
            [0.0, 10.0],
            [1.0, 7.0],
            [2.0, 4.0],
            [3.0, 1.0],
        ]);

        self::assertEqualsWithDelta(-3.0, $stats['slope'], 1e-12);
        self::assertEqualsWithDelta(10.0, $stats['intercept'], 1e-12);
        self::assertEqualsWithDelta(1.0, $stats['r2'], 1e-12);
    }

    public function test_constant_y_yields_flat_line_and_perfect_fit(): void
    {
        // Best-fit line is y = 7; every residual is zero, so the model fits exactly.
        $stats = Regression::linear([
            [0.0, 7.0],
            [1.0, 7.0],
            [2.0, 7.0],
        ]);

        self::assertSame(0.0, $stats['slope']);
        self::assertSame(7.0, $stats['intercept']);
        self::assertSame(1.0, $stats['r2']);
    }

    public function test_two_points_define_an_exact_line(): void
    {
        $stats = Regression::linear([
            [0.0, 1.0],
            [2.0, 5.0],
        ]);

        self::assertEqualsWithDelta(2.0, $stats['slope'], 1e-12);
        self::assertEqualsWithDelta(1.0, $stats['intercept'], 1e-12);
        self::assertEqualsWithDelta(1.0, $stats['r2'], 1e-12);
    }

    public function test_throws_when_fewer_than_two_points(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Regression::linear([[1.0, 2.0]]);
    }

    public function test_throws_on_empty_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Regression::linear([]);
    }

    public function test_throws_when_all_x_values_identical(): void
    {
        // A vertical set of points has no unique least-squares line — the slope is undefined.
        $this->expectException(InvalidArgumentException::class);
        Regression::linear([
            [3.0, 1.0],
            [3.0, 4.0],
            [3.0, 9.0],
        ]);
    }

    public function test_accepts_int_coordinates(): void
    {
        $stats = Regression::linear([
            [0, 0],
            [1, 2],
            [2, 4],
        ]);

        self::assertEqualsWithDelta(2.0, $stats['slope'], 1e-12);
        self::assertEqualsWithDelta(0.0, $stats['intercept'], 1e-12);
        self::assertEqualsWithDelta(1.0, $stats['r2'], 1e-12);
    }

    public function test_partial_correlation_matches_reference_within_tolerance(): void
    {
        // x = [0..9], y = x + sin(x) — noisy linear trend, slope ≈ 1.
        // Reference values produced by the closed-form OLS formula
        // (`slope = Σ(x-x̄)(y-ȳ) / Σ(x-x̄)²`) which is equivalent to NumPy
        // `polyfit(x, y, 1)` and Excel SLOPE/INTERCEPT/RSQ on the same input.
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $points[] = [(float) $i, $i + sin((float) $i)];
        }
        $stats = Regression::linear($points);

        self::assertEqualsWithDelta(1.012236331886171, $stats['slope'], 1e-9);
        self::assertEqualsWithDelta(0.140457454722968, $stats['intercept'], 1e-9);
        self::assertEqualsWithDelta(0.951477613338595, $stats['r2'], 1e-9);
    }
}

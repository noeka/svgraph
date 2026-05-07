<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Annotations;

use DateTimeImmutable;
use DateTimeZone;
use Noeka\Svgraph\Annotations\AnnotationContext;
use Noeka\Svgraph\Annotations\AnnotationLayer;
use Noeka\Svgraph\Annotations\TargetZone;
use Noeka\Svgraph\Geometry\Scale;
use Noeka\Svgraph\Geometry\TimeScale;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Theme;
use PHPUnit\Framework\TestCase;

final class TargetZoneTest extends TestCase
{
    private function context(?Scale $xScale = null): AnnotationContext
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        return new AnnotationContext(
            viewport: $viewport,
            theme: Theme::default(),
            xScale: $xScale ?? Scale::linear(0, 10, $viewport->plotLeft(), $viewport->plotRight()),
        );
    }

    public function test_zone_renders_rect_spanning_plot_height(): void
    {
        // x in [0, 10] → x=2 maps to viewport x=20, x=6 maps to 60.
        $svg = TargetZone::x(2.0, 6.0)->render($this->context());

        self::assertStringContainsString('x="20"', $svg);
        self::assertStringContainsString('width="40"', $svg);
        self::assertStringContainsString('y="0"', $svg);
        self::assertStringContainsString('height="100"', $svg);
    }

    public function test_zone_accepts_swapped_endpoints(): void
    {
        $a = TargetZone::x(2.0, 6.0)->render($this->context());
        $b = TargetZone::x(6.0, 2.0)->render($this->context());
        self::assertSame($a, $b);
    }

    public function test_zone_clamps_to_visible_domain(): void
    {
        // Zone [5, 50] partially out of [0, 10] → right edge clamps to 10.
        $svg = TargetZone::x(5.0, 50.0)->render($this->context());

        // value 5 → 50, value 10 (clamped) → 100. Width 50.
        self::assertStringContainsString('x="50"', $svg);
        self::assertStringContainsString('width="50"', $svg);
    }

    public function test_zone_entirely_outside_skipped_silently(): void
    {
        $svg = TargetZone::x(50.0, 100.0)->render($this->context());
        self::assertSame('', $svg);
    }

    public function test_default_fill_color_applied(): void
    {
        $svg = TargetZone::x(2.0, 6.0)->render($this->context());
        self::assertStringContainsString('fill="rgba(120,120,120,0.15)"', $svg);
    }

    public function test_fill_setter_overrides_default(): void
    {
        $svg = TargetZone::x(2.0, 6.0)->fill('#3b82f622')->render($this->context());
        self::assertStringContainsString('fill="#3b82f622"', $svg);
    }

    public function test_label_emitted_only_when_set(): void
    {
        $ctx = $this->context();
        self::assertSame([], TargetZone::x(2.0, 6.0)->labels($ctx));

        $labels = TargetZone::x(2.0, 6.0)->label('Deploy')->labels($ctx);
        self::assertCount(1, $labels);
        self::assertSame('Deploy', $labels[0]->text);
    }

    public function test_layer_is_behind_data(): void
    {
        self::assertSame(AnnotationLayer::BehindData, TargetZone::x(2.0, 6.0)->layer());
    }

    public function test_works_with_time_scale(): void
    {
        $tz = new DateTimeZone('UTC');
        $start = new DateTimeImmutable('2026-01-01T00:00:00', $tz);
        $end = new DateTimeImmutable('2026-01-11T00:00:00', $tz); // 10 days
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        $time = new TimeScale($start, $end, $viewport->plotLeft(), $viewport->plotRight(), timezone: $tz);
        $ctx = new AnnotationContext($viewport, Theme::default(), xScale: $time);

        $from = new DateTimeImmutable('2026-01-03T00:00:00', $tz);
        $to = new DateTimeImmutable('2026-01-08T00:00:00', $tz);
        $svg = TargetZone::x($from, $to)->render($ctx);

        // 10-day domain; from is 2/10 in, to is 7/10 in → x=20, width=50.
        self::assertStringContainsString('x="20"', $svg);
        self::assertStringContainsString('width="50"', $svg);
    }

    public function test_time_value_outside_time_domain_skipped(): void
    {
        $tz = new DateTimeZone('UTC');
        $start = new DateTimeImmutable('2026-01-01T00:00:00', $tz);
        $end = new DateTimeImmutable('2026-01-11T00:00:00', $tz);
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        $time = new TimeScale($start, $end, $viewport->plotLeft(), $viewport->plotRight(), timezone: $tz);
        $ctx = new AnnotationContext($viewport, Theme::default(), xScale: $time);

        $from = new DateTimeImmutable('2025-06-01T00:00:00', $tz);
        $to = new DateTimeImmutable('2025-12-01T00:00:00', $tz);
        self::assertSame('', TargetZone::x($from, $to)->render($ctx));
    }

    public function test_float_endpoints_against_time_scale_skipped(): void
    {
        $tz = new DateTimeZone('UTC');
        $start = new DateTimeImmutable('2026-01-01T00:00:00', $tz);
        $end = new DateTimeImmutable('2026-01-11T00:00:00', $tz);
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        $time = new TimeScale($start, $end, $viewport->plotLeft(), $viewport->plotRight(), timezone: $tz);
        $ctx = new AnnotationContext($viewport, Theme::default(), xScale: $time);

        // Mismatched input shape: float endpoints against time scale → skip.
        self::assertSame('', TargetZone::x(2.0, 6.0)->render($ctx));
    }

    public function test_date_endpoints_against_linear_scale_skipped(): void
    {
        $tz = new DateTimeZone('UTC');
        $from = new DateTimeImmutable('2026-01-03T00:00:00', $tz);
        $to = new DateTimeImmutable('2026-01-08T00:00:00', $tz);

        // Linear scale (no time) + DateTimeInterface input → mismatched, skip.
        self::assertSame('', TargetZone::x($from, $to)->render($this->context()));
    }

    public function test_no_x_scale_returns_empty(): void
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        $ctx = new AnnotationContext($viewport, Theme::default());

        self::assertSame('', TargetZone::x(2.0, 6.0)->render($ctx));
    }
}

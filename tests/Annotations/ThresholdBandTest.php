<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Annotations;

use Noeka\Svgraph\Annotations\AnnotationContext;
use Noeka\Svgraph\Annotations\AnnotationLayer;
use Noeka\Svgraph\Annotations\ThresholdBand;
use Noeka\Svgraph\Geometry\LogScale;
use Noeka\Svgraph\Geometry\Scale;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Theme;
use PHPUnit\Framework\TestCase;

final class ThresholdBandTest extends TestCase
{
    private function context(?Scale $yScale = null): AnnotationContext
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);

        return new AnnotationContext(
            viewport: $viewport,
            theme: Theme::default(),
            yScale: $yScale ?? Scale::linear(0, 100, $viewport->plotTop(), $viewport->plotBottom(), invert: true),
        );
    }

    public function test_band_renders_rect_spanning_plot_width(): void
    {
        $svg = ThresholdBand::y(20, 80)->render($this->context());

        // y axis inverted: y=80 → viewport y=20, y=20 → viewport y=80.
        // Band rect: x=0, y=20, width=100, height=60.
        self::assertStringContainsString('x="0"', $svg);
        self::assertStringContainsString('y="20"', $svg);
        self::assertStringContainsString('width="100"', $svg);
        self::assertStringContainsString('height="60"', $svg);
    }

    public function test_band_accepts_swapped_endpoints(): void
    {
        // from > to should still produce the same rect.
        $a = ThresholdBand::y(20, 80)->render($this->context());
        $b = ThresholdBand::y(80, 20)->render($this->context());
        self::assertSame($a, $b);
    }

    public function test_band_clamps_to_visible_domain(): void
    {
        // Band [50, 200] partially out of [0,100] → top edge clamps to 100.
        $svg = ThresholdBand::y(50, 200)->render($this->context());

        // value 50 → viewport y=50, value 100 (clamped) → viewport y=0.
        // Band: y=0, height=50.
        self::assertStringContainsString('y="0"', $svg);
        self::assertStringContainsString('height="50"', $svg);
    }

    public function test_band_entirely_outside_skipped_silently(): void
    {
        $svg = ThresholdBand::y(150, 200)->render($this->context());
        self::assertSame('', $svg);

        $svg2 = ThresholdBand::y(-100, -50)->render($this->context());
        self::assertSame('', $svg2);
    }

    public function test_default_fill_color_applied(): void
    {
        $svg = ThresholdBand::y(20, 80)->render($this->context());
        self::assertStringContainsString('fill="rgba(120,120,120,0.15)"', $svg);
    }

    public function test_fill_setter_overrides_default(): void
    {
        $svg = ThresholdBand::y(20, 80)->fill('#0f02')->render($this->context());
        self::assertStringContainsString('fill="#0f02"', $svg);
    }

    public function test_label_emitted_only_when_set(): void
    {
        $ctx = $this->context();
        self::assertSame([], ThresholdBand::y(20, 80)->labels($ctx));

        $labels = ThresholdBand::y(20, 80)->label('Healthy')->labels($ctx);
        self::assertCount(1, $labels);
        self::assertSame('Healthy', $labels[0]->text);
    }

    public function test_layer_is_behind_data(): void
    {
        self::assertSame(AnnotationLayer::BehindData, ThresholdBand::y(20, 80)->layer());
    }

    public function test_works_against_log_scale(): void
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        $log = LogScale::log(1, 1000, $viewport->plotTop(), $viewport->plotBottom(), invert: true);
        $ctx = new AnnotationContext($viewport, Theme::default(), yScale: $log);

        // Band [10, 100] on log10 scale [1, 1000]:
        // log10(10)=1, log10(100)=2, log10(1)=0, log10(1000)=3 → t spans 1/3..2/3.
        // Inverted vertically: y=100 → viewport y=33.33, y=10 → viewport y=66.66.
        $svg = ThresholdBand::y(10, 100)->render($ctx);

        self::assertStringContainsString('y="33.3333"', $svg);
        self::assertStringContainsString('height="33.3333"', $svg);
    }

    public function test_no_y_scale_returns_empty(): void
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        $ctx = new AnnotationContext($viewport, Theme::default());

        self::assertSame('', ThresholdBand::y(20, 80)->render($ctx));
    }
}

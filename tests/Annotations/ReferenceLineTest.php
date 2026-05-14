<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Annotations;

use Noeka\Svgraph\Annotations\AnnotationContext;
use Noeka\Svgraph\Annotations\AnnotationLayer;
use Noeka\Svgraph\Annotations\ReferenceLine;
use Noeka\Svgraph\Data\Axis;
use Noeka\Svgraph\Geometry\Scale;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Theme;
use PHPUnit\Framework\TestCase;

final class ReferenceLineTest extends TestCase
{
    private function context(?Scale $rightYScale = null): AnnotationContext
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);

        return new AnnotationContext(
            viewport: $viewport,
            theme: Theme::default(),
            xScale: Scale::linear(0, 10, $viewport->plotLeft(), $viewport->plotRight()),
            yScale: Scale::linear(0, 100, $viewport->plotTop(), $viewport->plotBottom(), invert: true),
            rightYScale: $rightYScale,
        );
    }

    public function test_horizontal_line_maps_y_value_to_viewport_coord(): void
    {
        $ann = ReferenceLine::y(50);
        $svg = $ann->render($this->context());

        // y = 50 in [0,100] inverted to viewport top..bottom (0..100) → 50.
        self::assertStringContainsString('y1="50"', $svg);
        self::assertStringContainsString('y2="50"', $svg);
        self::assertStringContainsString('x1="0"', $svg);
        self::assertStringContainsString('x2="100"', $svg);
    }

    public function test_vertical_line_maps_x_value_to_viewport_coord(): void
    {
        $ann = ReferenceLine::x(2.5);
        $svg = $ann->render($this->context());

        // x = 2.5 in [0, 10] → 25 of viewport width 100.
        self::assertStringContainsString('x1="25"', $svg);
        self::assertStringContainsString('x2="25"', $svg);
        self::assertStringContainsString('y1="0"', $svg);
        self::assertStringContainsString('y2="100"', $svg);
    }

    public function test_dashed_by_default(): void
    {
        $svg = ReferenceLine::y(50)->render($this->context());
        self::assertStringContainsString('stroke-dasharray="4,3"', $svg);
    }

    public function test_solid_drops_dasharray(): void
    {
        $svg = ReferenceLine::y(50)->solid()->render($this->context());
        self::assertStringNotContainsString('stroke-dasharray', $svg);
    }

    public function test_color_overrides_default(): void
    {
        $svg = ReferenceLine::y(50)->color('#ff0000')->render($this->context());
        self::assertStringContainsString('stroke="#ff0000"', $svg);
    }

    public function test_stroke_width_setter(): void
    {
        $svg = ReferenceLine::y(50)->strokeWidth(2.5)->render($this->context());
        self::assertStringContainsString('stroke-width="2.5"', $svg);
    }

    public function test_y_value_above_domain_skipped_silently(): void
    {
        // Domain max is 100 — value 200 is out of range.
        $ann = ReferenceLine::y(200);
        self::assertSame('', $ann->render($this->context()));
        self::assertSame([], $ann->labels($this->context()));
    }

    public function test_y_value_below_domain_skipped_silently(): void
    {
        $ann = ReferenceLine::y(-5);
        self::assertSame('', $ann->render($this->context()));
    }

    public function test_x_value_outside_domain_skipped_silently(): void
    {
        // Domain max is 10.
        $ann = ReferenceLine::x(50.0);
        self::assertSame('', $ann->render($this->context()));
    }

    public function test_layer_is_behind_data_by_default(): void
    {
        self::assertSame(AnnotationLayer::BehindData, ReferenceLine::y(50)->layer());
        self::assertSame(AnnotationLayer::BehindData, ReferenceLine::x(5)->layer());
    }

    public function test_label_emitted_only_when_set(): void
    {
        $ctx = $this->context();
        self::assertSame([], ReferenceLine::y(50)->labels($ctx));

        $labels = ReferenceLine::y(50)->label('Goal')->labels($ctx);
        self::assertCount(1, $labels);
        self::assertSame('Goal', $labels[0]->text);
    }

    public function test_secondary_axis_uses_right_scale(): void
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        $right = Scale::linear(0, 1, $viewport->plotTop(), $viewport->plotBottom(), invert: true);
        $ctx = $this->context($right);

        // Domain [0,1] on right axis: value 0.5 → midpoint of viewport.
        $svg = ReferenceLine::y(0.5)->onAxis(Axis::Right)->render($ctx);
        self::assertStringContainsString('y1="50"', $svg);
    }

    public function test_secondary_axis_value_outside_right_domain_skipped(): void
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        $right = Scale::linear(0, 1, $viewport->plotTop(), $viewport->plotBottom(), invert: true);
        $ctx = $this->context($right);

        // Right domain only goes 0..1; value 5 is out of range there even if
        // it would fit on the left (0..100).
        self::assertSame('', ReferenceLine::y(5)->onAxis('right')->render($ctx));
    }

    public function test_horizontal_line_without_y_scale_returns_empty(): void
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        $ctx = new AnnotationContext($viewport, Theme::default());

        self::assertSame('', ReferenceLine::y(10)->render($ctx));
    }

    public function test_vertical_line_without_x_scale_returns_empty(): void
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        $ctx = new AnnotationContext($viewport, Theme::default());

        self::assertSame('', ReferenceLine::x(5)->render($ctx));
    }
}

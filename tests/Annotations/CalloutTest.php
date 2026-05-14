<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Annotations;

use Noeka\Svgraph\Annotations\AnnotationContext;
use Noeka\Svgraph\Annotations\AnnotationLayer;
use Noeka\Svgraph\Annotations\Callout;
use Noeka\Svgraph\Geometry\Scale;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Theme;
use PHPUnit\Framework\TestCase;

final class CalloutTest extends TestCase
{
    private function context(): AnnotationContext
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);

        return new AnnotationContext(
            viewport: $viewport,
            theme: Theme::default(),
            xScale: Scale::linear(0, 10, $viewport->plotLeft(), $viewport->plotRight()),
            yScale: Scale::linear(0, 100, $viewport->plotTop(), $viewport->plotBottom(), invert: true),
        );
    }

    public function test_layer_is_over_data(): void
    {
        self::assertSame(AnnotationLayer::OverData, Callout::at(5, 50, 'peak')->layer());
    }

    public function test_renders_anchor_dot_and_leader_line(): void
    {
        $svg = Callout::at(5, 50, 'peak')->render($this->context());

        // x=5 in [0,10] → 50; y=50 in [0,100] inverted → 50. Anchor at (50, 50).
        self::assertStringContainsString('cx="50"', $svg);
        self::assertStringContainsString('cy="50"', $svg);
        self::assertStringContainsString('<line ', $svg);
        self::assertStringContainsString('<circle ', $svg);
    }

    public function test_leader_line_uses_default_offset(): void
    {
        $svg = Callout::at(5, 50, 'peak')->render($this->context());

        // Default offset (6, -6) → leader endpoint at (56, 44).
        self::assertStringContainsString('x1="50"', $svg);
        self::assertStringContainsString('y1="50"', $svg);
        self::assertStringContainsString('x2="56"', $svg);
        self::assertStringContainsString('y2="44"', $svg);
    }

    public function test_offset_setter_changes_label_position(): void
    {
        $svg = Callout::at(5, 50, 'peak')->offset(-10, 5)->render($this->context());

        self::assertStringContainsString('x2="40"', $svg);
        self::assertStringContainsString('y2="55"', $svg);
    }

    public function test_leader_line_clamped_to_plot_area(): void
    {
        // Anchor near right edge with positive offset → label end clamps to plotRight.
        $svg = Callout::at(10, 50, 'edge')->offset(20, 0)->render($this->context());

        // anchor x = 100 (rightmost), offset +20 would push to 120 → clamped to 100.
        self::assertStringContainsString('x2="100"', $svg);
    }

    public function test_label_emitted_with_text(): void
    {
        $labels = Callout::at(5, 50, 'peak')->labels($this->context());
        self::assertCount(1, $labels);
        self::assertSame('peak', $labels[0]->text);
    }

    public function test_label_alignment_follows_offset_sign(): void
    {
        $ctx = $this->context();

        $rightDown = Callout::at(5, 50, 'a')->offset(6, 6)->labels($ctx);
        self::assertSame('start', $rightDown[0]->align);
        self::assertSame('top', $rightDown[0]->verticalAlign);

        $leftUp = Callout::at(5, 50, 'a')->offset(-6, -6)->labels($ctx);
        self::assertSame('end', $leftUp[0]->align);
        self::assertSame('bottom', $leftUp[0]->verticalAlign);
    }

    public function test_x_outside_domain_skipped(): void
    {
        $ann = Callout::at(50, 50, 'far');
        self::assertSame('', $ann->render($this->context()));
        self::assertSame([], $ann->labels($this->context()));
    }

    public function test_y_outside_domain_skipped(): void
    {
        $ann = Callout::at(5, 500, 'high');
        self::assertSame('', $ann->render($this->context()));
        self::assertSame([], $ann->labels($this->context()));
    }

    public function test_color_overrides_default_stroke_and_fill(): void
    {
        $svg = Callout::at(5, 50, 'p')->color('#ff00ff')->render($this->context());

        self::assertStringContainsString('stroke="#ff00ff"', $svg);
        self::assertStringContainsString('fill="#ff00ff"', $svg);
    }

    public function test_no_y_scale_returns_empty(): void
    {
        $viewport = new Viewport(100, 100, 0, 0, 0, 0);
        $ctx = new AnnotationContext(
            $viewport,
            Theme::default(),
            xScale: Scale::linear(0, 10, 0, 100),
        );
        self::assertSame('', Callout::at(5, 50, 'p')->render($ctx));
    }
}

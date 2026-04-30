<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Svg;

use Noeka\Svgraph\Svg\Label;
use PHPUnit\Framework\TestCase;

final class LabelTest extends TestCase
{
    public function test_renders_left_and_top_position(): void
    {
        $html = (new Label('Hello', left: 10.0, top: 25.0))->render();
        self::assertStringContainsString('left:10%', $html);
        self::assertStringContainsString('top:25%', $html);
        self::assertStringContainsString('Hello', $html);
    }

    public function test_renders_right_and_bottom_position(): void
    {
        $html = (new Label('X', right: 5.0, bottom: 10.0))->render();
        self::assertStringContainsString('right:5%', $html);
        self::assertStringContainsString('bottom:10%', $html);
    }

    public function test_center_align_adds_translate_x(): void
    {
        $html = (new Label('X', left: 50.0, top: 0.0, align: 'center'))->render();
        self::assertStringContainsString('transform:translate(-50%,0)', $html);
    }

    public function test_end_align_adds_translate_x(): void
    {
        $html = (new Label('X', left: 100.0, top: 0.0, align: 'end'))->render();
        self::assertStringContainsString('transform:translate(-100%,0)', $html);
    }

    public function test_middle_vertical_align_adds_translate_y(): void
    {
        $html = (new Label('X', left: 0.0, top: 50.0, verticalAlign: 'middle'))->render();
        self::assertStringContainsString('transform:translate(0,-50%)', $html);
    }

    public function test_center_and_middle_combined(): void
    {
        $html = (new Label('X', left: 50.0, top: 50.0, align: 'center', verticalAlign: 'middle'))->render();
        self::assertStringContainsString('transform:translate(-50%,-50%)', $html);
    }

    public function test_default_align_omits_transform(): void
    {
        $html = (new Label('X', left: 0.0, top: 0.0))->render();
        self::assertStringNotContainsString('transform:', $html);
    }

    public function test_text_is_escaped_by_default(): void
    {
        $html = (new Label('<b>bold</b>', left: 0.0, top: 0.0))->render();
        self::assertStringNotContainsString('<b>', $html);
        self::assertStringContainsString('&lt;b&gt;', $html);
    }

    public function test_raw_true_skips_escaping(): void
    {
        $html = (new Label('<b>bold</b>', left: 0.0, top: 0.0, raw: true))->render();
        self::assertStringContainsString('<b>bold</b>', $html);
    }

    public function test_color_appears_in_style(): void
    {
        $html = (new Label('X', left: 0.0, top: 0.0, color: '#ff0000'))->render();
        self::assertStringContainsString('color:#ff0000', $html);
    }
}

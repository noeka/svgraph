<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Svg;

use Noeka\Svgraph\Svg\Tag;
use PHPUnit\Framework\TestCase;

final class TagTest extends TestCase
{
    public function test_renders_open_close_pair(): void
    {
        $tag = Tag::make('g', ['class' => 'foo'])->append('hello');
        self::assertSame('<g class="foo">hello</g>', (string) $tag);
    }

    public function test_renders_self_closing(): void
    {
        $tag = Tag::void('rect', ['x' => 0, 'y' => 0, 'width' => 10, 'height' => 5]);
        self::assertSame('<rect x="0" y="0" width="10" height="5"/>', (string) $tag);
    }

    public function test_escapes_attribute_values(): void
    {
        $tag = Tag::make('text', ['data-x' => '"><script>alert(1)</script>']);
        self::assertStringNotContainsString('<script>', (string) $tag);
        self::assertStringContainsString('&quot;&gt;&lt;script&gt;', (string) $tag);
    }

    public function test_escapes_text_children(): void
    {
        $tag = Tag::make('text')->append('<script>x</script>');
        self::assertSame('<text>&lt;script&gt;x&lt;/script&gt;</text>', (string) $tag);
    }

    public function test_omits_null_and_false_attributes(): void
    {
        $tag = Tag::void('rect', ['x' => 0, 'fill' => null, 'stroke' => false]);
        self::assertSame('<rect x="0"/>', (string) $tag);
    }

    public function test_renders_true_as_bare_attribute(): void
    {
        $tag = Tag::make('option', ['selected' => true])->append('A');
        self::assertSame('<option selected>A</option>', (string) $tag);
    }

    public function test_drops_invalid_attribute_names(): void
    {
        $tag = Tag::void('rect', ['x' => 0, '><script' => 'bad']);
        self::assertSame('<rect x="0"/>', (string) $tag);
    }

    public function test_format_float_strips_trailing_zeros(): void
    {
        self::assertSame('1.5', Tag::formatFloat(1.5));
        self::assertSame('1', Tag::formatFloat(1.0));
        self::assertSame('1.2346', Tag::formatFloat(1.23456789));
        self::assertSame('0', Tag::formatFloat(0.0));
        self::assertSame('-3.5', Tag::formatFloat(-3.5));
    }

    public function test_append_raw_skips_escaping(): void
    {
        $tag = Tag::make('g')->appendRaw('<circle cx="0"/>');
        self::assertSame('<g><circle cx="0"/></g>', (string) $tag);
    }
}

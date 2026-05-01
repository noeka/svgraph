<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Data;

use Noeka\Svgraph\Data\Link;
use PHPUnit\Framework\TestCase;

final class LinkTest extends TestCase
{
    public function test_construct_with_href_only(): void
    {
        $link = new Link('https://example.com');
        self::assertSame('https://example.com', $link->href);
        self::assertNull($link->target);
        self::assertSame('', $link->rel);
    }

    public function test_blank_target_defaults_rel_to_noopener_noreferrer(): void
    {
        $link = new Link('https://example.com', '_blank');
        self::assertSame('_blank', $link->target);
        self::assertSame('noopener noreferrer', $link->rel);
    }

    public function test_non_blank_target_leaves_rel_empty_by_default(): void
    {
        $link = new Link('https://example.com', '_self');
        self::assertSame('', $link->rel);
    }

    public function test_explicit_rel_overrides_default(): void
    {
        $link = new Link('https://example.com', '_blank', 'noopener');
        self::assertSame('noopener', $link->rel);
    }

    public function test_javascript_url_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Link('javascript:alert(1)');
    }

    public function test_javascript_url_with_leading_spaces_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Link('   javascript:void(0)');
    }

    public function test_javascript_url_with_mixed_case_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Link('JavaScript:alert(1)');
    }

    public function test_data_url_is_allowed(): void
    {
        $link = new Link('data:text/plain,hello');
        self::assertSame('data:text/plain,hello', $link->href);
    }

    public function test_relative_url_is_allowed(): void
    {
        $link = new Link('/dashboard');
        self::assertSame('/dashboard', $link->href);
    }
}

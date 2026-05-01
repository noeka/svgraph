<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Svg;

/**
 * Allowlist sanitizers for values interpolated into a CSS context inside a
 * `style="..."` attribute. Tag::escapeAttr stops attribute breakout, but the
 * contents of the attribute are still parsed as a CSS declaration list — so
 * a value like `red;background:url(x)` would inject an extra rule. These
 * helpers accept a tight whitelist and return null for anything else, so
 * callers can fall back to a safe default.
 */
final class Css
{
    private const COLOR = '/\A(?:#[0-9a-f]{3,8}|rgba?\([0-9.,\s%\-]{1,40}\)|hsla?\([0-9.,\s%\-]{1,40}\)|[a-z]{3,20})\z/i';
    private const LENGTH = '/\A[0-9]+(?:\.[0-9]+)?(?:px|em|rem|%|pt|vh|vw|ex|ch)?\z/';
    private const FONT_FAMILY = '/\A[A-Za-z0-9 ,\-\'"]{1,80}\z/';

    public static function color(?string $value): ?string
    {
        return $value !== null && preg_match(self::COLOR, $value) === 1 ? $value : null;
    }

    public static function length(?string $value): ?string
    {
        return $value !== null && preg_match(self::LENGTH, $value) === 1 ? $value : null;
    }

    public static function fontFamily(?string $value): ?string
    {
        return $value !== null && preg_match(self::FONT_FAMILY, $value) === 1 ? $value : null;
    }
}

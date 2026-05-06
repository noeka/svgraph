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
    private const string COLOR = '/\A(?:#[0-9a-f]{3,8}|rgba?\([0-9.,\s%\-]{1,40}\)|hsla?\([0-9.,\s%\-]{1,40}\)|[a-z]{3,20})\z/i';
    private const string LENGTH = '/\A\d+(?:\.\d+)?(?:px|em|rem|%|pt|vh|vw|ex|ch)?\z/';
    private const string LENGTH_WITH_UNIT = '/\A\d+(?:\.\d+)?(?:px|em|rem|%|pt|vh|vw|ex|ch)\z/';
    private const string FONT_FAMILY = '/\A[A-Za-z0-9 ,\-\'"]{1,80}\z/';
    private const string NUMBER = '/\A\d+(?:\.\d+)?\z/';
    private const string DURATION = '/\A\d+(?:\.\d+)?(?:ms|s)\z/';
    private const string EASING = '/\A(?:ease(?:-in(?:-out)?|-out)?|linear|step-(?:start|end)|steps\(\d+(?:,(?:start|end))?\)|cubic-bezier\(-?[0-9.]+,-?[0-9.]+,-?[0-9.]+,-?[0-9.]+\))\z/';

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

    /** Validates a non-negative CSS `<number>` (no unit), e.g. "1.2" or "2". */
    public static function number(?string $value): ?string
    {
        return $value !== null && preg_match(self::NUMBER, $value) === 1 ? $value : null;
    }

    /** Validates a CSS `<length>` that must include a unit, e.g. "3px", "0.5rem". */
    public static function lengthWithUnit(?string $value): ?string
    {
        return $value !== null && preg_match(self::LENGTH_WITH_UNIT, $value) === 1 ? $value : null;
    }

    /** Validates a CSS `<time>` value, e.g. "0.6s" or "300ms". */
    public static function duration(?string $value): ?string
    {
        return $value !== null && preg_match(self::DURATION, $value) === 1 ? $value : null;
    }

    /** Validates a CSS easing keyword or function, e.g. "ease-out" or "cubic-bezier(0.4,0,0.2,1)". */
    public static function easing(?string $value): ?string
    {
        return $value !== null && preg_match(self::EASING, $value) === 1 ? $value : null;
    }
}

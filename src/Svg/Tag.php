<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Svg;

final class Tag implements \Stringable
{
    /** @var list<string> */
    private array $children = [];

    /**
     * @param array<string, scalar|null> $attributes
     */
    public function __construct(
        private readonly string $name,
        private array $attributes = [],
        private readonly bool $selfClosing = false,
    ) {}

    /**
     * @param array<string, scalar|null> $attributes
     */
    public static function make(string $name, array $attributes = []): self
    {
        return new self($name, $attributes);
    }

    /**
     * @param array<string, scalar|null> $attributes
     */
    public static function void(string $name, array $attributes = []): self
    {
        return new self($name, $attributes, selfClosing: true);
    }

    public function attr(string $key, string|int|float|bool|null $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * @param array<string, scalar|null> $attributes
     */
    public function attrs(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    public function append(string|self|null $child): self
    {
        if ($child === null) {
            return $this;
        }

        $this->children[] = $child instanceof self ? (string) $child : self::escapeText($child);

        return $this;
    }

    public function appendRaw(string $markup): self
    {
        $this->children[] = $markup;

        return $this;
    }

    public function __toString(): string
    {
        $attrs = $this->renderAttributes($this->attributes);

        if ($this->selfClosing) {
            return "<{$this->name}{$attrs}/>";
        }

        $inner = implode('', $this->children);

        return "<{$this->name}{$attrs}>{$inner}</{$this->name}>";
    }

    public static function escapeText(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, scalar|null> $attributes
     */
    private function renderAttributes(array $attributes): string
    {
        $out = '';

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false || ! $this->isValidAttrName($key)) {
                continue;
            }

            if ($value === true) {
                $out .= ' ' . $key;
                continue;
            }

            $stringified = is_float($value)
                ? self::formatFloat($value)
                : (string) $value;

            $out .= ' ' . $key . '="' . self::escapeAttr($stringified) . '"';
        }

        return $out;
    }

    private function isValidAttrName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z_:][A-Za-z0-9_:.\-]*$/', $name);
    }

    public static function formatFloat(float $value): string
    {
        if (is_nan($value) || is_infinite($value)) {
            return '0';
        }

        $formatted = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}

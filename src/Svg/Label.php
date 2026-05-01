<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Svg;

/**
 * A single absolutely-positioned text label rendered as HTML alongside the SVG.
 * Coordinates are percentages of the wrapper, so labels track data positions
 * across resizes without the SVG's `preserveAspectRatio="none"` distorting them.
 */
final readonly class Label
{
    public function __construct(
        public string $text,
        public ?float $left = null,
        public ?float $right = null,
        public ?float $top = null,
        public ?float $bottom = null,
        public string $align = 'start',
        public string $verticalAlign = 'baseline',
        public ?string $color = null,
        public bool $raw = false,
    ) {}

    public function render(): string
    {
        $rules = ['position:absolute'];
        if ($this->left !== null) {
            $rules[] = 'left:' . Tag::formatFloat($this->left) . '%';
        }
        if ($this->right !== null) {
            $rules[] = 'right:' . Tag::formatFloat($this->right) . '%';
        }
        if ($this->top !== null) {
            $rules[] = 'top:' . Tag::formatFloat($this->top) . '%';
        }
        if ($this->bottom !== null) {
            $rules[] = 'bottom:' . Tag::formatFloat($this->bottom) . '%';
        }

        $tx = match ($this->align) {
            'center' => '-50%',
            'end' => '-100%',
            default => '0',
        };
        $ty = match ($this->verticalAlign) {
            'middle' => '-50%',
            'top' => '0',
            'bottom' => '-100%',
            default => '0',
        };
        if ($tx !== '0' || $ty !== '0') {
            $rules[] = "transform:translate({$tx},{$ty})";
        }
        $rules[] = 'white-space:nowrap';
        $color = Css::color($this->color);
        if ($color !== null) {
            $rules[] = 'color:' . $color;
        }

        $span = Tag::make('span', ['style' => implode(';', $rules)]);
        if ($this->raw) {
            $span->appendRaw($this->text);
        } else {
            $span->append($this->text);
        }
        return (string) $span;
    }
}

<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Geometry;

/**
 * The logical SVG viewport. We always use a fixed 100x100 box because the
 * outer wrapper stretches the SVG with preserveAspectRatio="none". Padding
 * carves out room for axis labels and gridlines on each edge.
 */
final readonly class Viewport
{
    public function __construct(
        public float $width = 100.0,
        public float $height = 100.0,
        public float $paddingTop = 0.0,
        public float $paddingRight = 0.0,
        public float $paddingBottom = 0.0,
        public float $paddingLeft = 0.0,
    ) {}

    public function withPadding(float $top, float $right, float $bottom, float $left): self
    {
        return new self($this->width, $this->height, $top, $right, $bottom, $left);
    }

    public function plotLeft(): float
    {
        return $this->paddingLeft;
    }

    public function plotRight(): float
    {
        return $this->width - $this->paddingRight;
    }

    public function plotTop(): float
    {
        return $this->paddingTop;
    }

    public function plotBottom(): float
    {
        return $this->height - $this->paddingBottom;
    }

    public function plotWidth(): float
    {
        return max(0.0, $this->plotRight() - $this->plotLeft());
    }

    public function plotHeight(): float
    {
        return max(0.0, $this->plotBottom() - $this->plotTop());
    }

    public function viewBox(): string
    {
        return sprintf(
            '0 0 %s %s',
            rtrim(rtrim(number_format($this->width, 2, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($this->height, 2, '.', ''), '0'), '.'),
        );
    }
}

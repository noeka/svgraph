<?php

declare(strict_types=1);

namespace Noeka\Svgraph;

final readonly class Theme
{
    /**
     * @param list<string> $palette  Hex/CSS colors used in order for multi-series or partition charts.
     * @param string       $stroke   Default stroke color for lines/axes.
     * @param float        $strokeWidth
     * @param string       $fill     Default fill color (e.g. for bars when no color specified).
     * @param string       $textColor
     * @param string       $fontFamily
     * @param string       $fontSize CSS length, e.g. "0.75rem".
     * @param string       $gridColor
     * @param string       $axisColor
     * @param string       $trackColor Background color for progress track and donut "empty" portion.
     */
    public function __construct(
        public array $palette,
        public string $stroke,
        public float $strokeWidth,
        public string $fill,
        public string $textColor,
        public string $fontFamily,
        public string $fontSize,
        public string $gridColor,
        public string $axisColor,
        public string $trackColor,
    ) {}

    public static function default(): self
    {
        return new self(
            palette: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'],
            stroke: '#3b82f6',
            strokeWidth: 2.0,
            fill: '#3b82f6',
            textColor: '#374151',
            fontFamily: 'inherit',
            fontSize: '0.75rem',
            gridColor: '#e5e7eb',
            axisColor: '#9ca3af',
            trackColor: '#e5e7eb',
        );
    }

    public static function dark(): self
    {
        return new self(
            palette: ['#60a5fa', '#34d399', '#fbbf24', '#f87171', '#a78bfa', '#f472b6', '#2dd4bf', '#fb923c'],
            stroke: '#60a5fa',
            strokeWidth: 2.0,
            fill: '#60a5fa',
            textColor: '#e5e7eb',
            fontFamily: 'inherit',
            fontSize: '0.75rem',
            gridColor: '#374151',
            axisColor: '#6b7280',
            trackColor: '#374151',
        );
    }

    public function withPalette(string ...$colors): self
    {
        return new self(
            palette: array_values($colors),
            stroke: $this->stroke,
            strokeWidth: $this->strokeWidth,
            fill: $this->fill,
            textColor: $this->textColor,
            fontFamily: $this->fontFamily,
            fontSize: $this->fontSize,
            gridColor: $this->gridColor,
            axisColor: $this->axisColor,
            trackColor: $this->trackColor,
        );
    }

    public function colorAt(int $index): string
    {
        if ($this->palette === []) {
            return $this->stroke;
        }
        return $this->palette[$index % count($this->palette)];
    }
}

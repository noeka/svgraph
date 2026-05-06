<?php

declare(strict_types=1);

namespace Noeka\Svgraph;

final readonly class Theme
{
    /**
     * @param list<string> $palette          Hex/CSS colors used in order for multi-series or partition charts.
     * @param string       $stroke           Default stroke color for lines/axes.
     * @param string       $fill             Default fill color (e.g. for bars when no color specified).
     * @param string       $fontSize         CSS length, e.g. "0.75rem".
     * @param string       $trackColor       Background color for progress track and donut "empty" portion.
     *
     * CSS-hover tooltip theming tokens — these map to the following CSS custom
     * properties on the `.svgraph` wrapper, which you can also override in your
     * own stylesheet:
     *
     * @param string $tooltipBackground  `--svgraph-tt-bg`     Tooltip panel background color.
     * @param string $tooltipTextColor   `--svgraph-tt-fg`     Tooltip text color.
     * @param string $tooltipBorderRadius `--svgraph-tt-r`     Tooltip corner radius (CSS length).
     *
     * Hover/focus highlight theming tokens — pure CSS, overridable via custom
     * properties on the `.svgraph` wrapper:
     *
     * @param string $hoverBrightness   `--svgraph-hover-brightness`   CSS `<number>` passed to brightness(). Default 1.2.
     * @param string $hoverStrokeWidth  `--svgraph-hover-stroke-width` SVG stroke-width added on hover. Default 1.5.
     * @param string $piePopDistance    `--svgraph-pie-pop-distance`   CSS `<length>` (e.g. "3px") pie slices pop outward by on hover. Default "3px".
     *
     * Entrance animation tokens — emitted as CSS custom properties when `->animate()` is used:
     *
     * @param string $animationDuration `--svgraph-anim-dur`  CSS `<time>` for the animation (e.g. "0.6s"). Default "0.6s".
     * @param string $animationEasing   `--svgraph-anim-ease` CSS easing function (e.g. "ease-out"). Default "ease-out".
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
        public string $tooltipBackground = '#1f2937',
        public string $tooltipTextColor = '#f9fafb',
        public string $tooltipBorderRadius = '0.25rem',
        public string $hoverBrightness = '1.2',
        public string $hoverStrokeWidth = '1.5',
        public string $piePopDistance = '3px',
        public string $animationDuration = '0.6s',
        public string $animationEasing = 'ease-out',
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
            tooltipBackground: '#111827',
            tooltipTextColor: '#f3f4f6',
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
            tooltipBackground: $this->tooltipBackground,
            tooltipTextColor: $this->tooltipTextColor,
            tooltipBorderRadius: $this->tooltipBorderRadius,
            hoverBrightness: $this->hoverBrightness,
            hoverStrokeWidth: $this->hoverStrokeWidth,
            piePopDistance: $this->piePopDistance,
            animationDuration: $this->animationDuration,
            animationEasing: $this->animationEasing,
        );
    }

    /**
     * Return a copy with different tooltip styling.
     *
     * The values are emitted as CSS custom properties on the wrapper element:
     *   --svgraph-tt-bg, --svgraph-tt-fg, --svgraph-tt-r
     * You can also set these properties directly in your own CSS.
     */
    public function withTooltip(
        ?string $background = null,
        ?string $textColor = null,
        ?string $borderRadius = null,
    ): self {
        return new self(
            palette: $this->palette,
            stroke: $this->stroke,
            strokeWidth: $this->strokeWidth,
            fill: $this->fill,
            textColor: $this->textColor,
            fontFamily: $this->fontFamily,
            fontSize: $this->fontSize,
            gridColor: $this->gridColor,
            axisColor: $this->axisColor,
            trackColor: $this->trackColor,
            tooltipBackground: $background ?? $this->tooltipBackground,
            tooltipTextColor: $textColor ?? $this->tooltipTextColor,
            tooltipBorderRadius: $borderRadius ?? $this->tooltipBorderRadius,
            hoverBrightness: $this->hoverBrightness,
            hoverStrokeWidth: $this->hoverStrokeWidth,
            piePopDistance: $this->piePopDistance,
            animationDuration: $this->animationDuration,
            animationEasing: $this->animationEasing,
        );
    }

    /**
     * Return a copy with different hover/focus highlight styling.
     *
     * The values are emitted as CSS custom properties on the wrapper element:
     *   --svgraph-hover-brightness, --svgraph-hover-stroke-width, --svgraph-pie-pop-distance
     * You can also set these properties directly in your own CSS.
     */
    public function withHover(
        ?string $brightness = null,
        ?string $strokeWidth = null,
        ?string $piePopDistance = null,
    ): self {
        return new self(
            palette: $this->palette,
            stroke: $this->stroke,
            strokeWidth: $this->strokeWidth,
            fill: $this->fill,
            textColor: $this->textColor,
            fontFamily: $this->fontFamily,
            fontSize: $this->fontSize,
            gridColor: $this->gridColor,
            axisColor: $this->axisColor,
            trackColor: $this->trackColor,
            tooltipBackground: $this->tooltipBackground,
            tooltipTextColor: $this->tooltipTextColor,
            tooltipBorderRadius: $this->tooltipBorderRadius,
            hoverBrightness: $brightness ?? $this->hoverBrightness,
            hoverStrokeWidth: $strokeWidth ?? $this->hoverStrokeWidth,
            piePopDistance: $piePopDistance ?? $this->piePopDistance,
            animationDuration: $this->animationDuration,
            animationEasing: $this->animationEasing,
        );
    }

    /**
     * Return a copy with different animation timing.
     *
     * The values are emitted as CSS custom properties on the wrapper element:
     *   --svgraph-anim-dur, --svgraph-anim-ease
     * You can also set these properties directly in your own CSS.
     */
    public function withAnimation(string $duration, string $easing = 'ease-out'): self
    {
        return new self(
            palette: $this->palette,
            stroke: $this->stroke,
            strokeWidth: $this->strokeWidth,
            fill: $this->fill,
            textColor: $this->textColor,
            fontFamily: $this->fontFamily,
            fontSize: $this->fontSize,
            gridColor: $this->gridColor,
            axisColor: $this->axisColor,
            trackColor: $this->trackColor,
            tooltipBackground: $this->tooltipBackground,
            tooltipTextColor: $this->tooltipTextColor,
            tooltipBorderRadius: $this->tooltipBorderRadius,
            hoverBrightness: $this->hoverBrightness,
            hoverStrokeWidth: $this->hoverStrokeWidth,
            piePopDistance: $this->piePopDistance,
            animationDuration: $duration,
            animationEasing: $easing,
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

<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Annotations;

use Noeka\Svgraph\Geometry\Scale;
use DateTimeInterface;
use Noeka\Svgraph\Data\Axis;
use Noeka\Svgraph\Svg\Label;
use Noeka\Svgraph\Svg\Tag;

/**
 * A horizontal or vertical line drawn across the plot at a fixed value —
 * goal lines, averages, control limits.
 *
 * - `ReferenceLine::y(100)` draws a horizontal line at y = 100.
 * - `ReferenceLine::x(5)` (or a `DateTimeImmutable`) draws a vertical line.
 *
 * The optional label is emitted as an HTML overlay so it renders cleanly
 * even when the SVG is stretched by the chart's aspect ratio.
 */
final class ReferenceLine extends Annotation
{
    private const string ORIENT_H = 'h';
    private const string ORIENT_V = 'v';

    private ?string $labelText = null;
    private ?string $color = null;
    private float $strokeWidth = 1.0;
    private bool $dashed = true;
    private Axis $axis = Axis::Left;

    private function __construct(
        private readonly string $orientation,
        private readonly float|DateTimeInterface $value,
    ) {}

    /** Horizontal reference line at the given Y value. */
    public static function y(float $value): self
    {
        return new self(self::ORIENT_H, $value);
    }

    /** Vertical reference line at the given X value (column index or date). */
    public static function x(float|DateTimeInterface $value): self
    {
        return new self(self::ORIENT_V, $value);
    }

    public function label(string $text): self
    {
        $this->labelText = $text;

        return $this;
    }

    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function strokeWidth(float $width): self
    {
        $this->strokeWidth = max(0.0, $width);

        return $this;
    }

    public function solid(): self
    {
        $this->dashed = false;

        return $this;
    }

    public function dashed(bool $on = true): self
    {
        $this->dashed = $on;

        return $this;
    }

    /**
     * Bind a horizontal reference line to a specific Y axis. Only meaningful
     * when the underlying chart has a secondary axis enabled.
     */
    public function onAxis(Axis|string $axis): self
    {
        $this->axis = $axis instanceof Axis ? $axis : Axis::from($axis);

        return $this;
    }

    public function render(AnnotationContext $context): string
    {
        $coords = $this->coordinates($context);

        if ($coords === null) {
            return '';
        }

        $color = $this->color ?? $context->theme->axisColor;
        $attrs = [
            'class' => 'svgraph-annotation-ref',
            'x1' => Tag::formatFloat($coords[0]),
            'y1' => Tag::formatFloat($coords[1]),
            'x2' => Tag::formatFloat($coords[2]),
            'y2' => Tag::formatFloat($coords[3]),
            'stroke' => $color,
            'stroke-width' => Tag::formatFloat($this->strokeWidth),
            'vector-effect' => 'non-scaling-stroke',
        ];

        if ($this->dashed) {
            $attrs['stroke-dasharray'] = '4,3';
        }

        return (string) Tag::void('line', $attrs);
    }

    #[\Override]
    public function labels(AnnotationContext $context): array
    {
        if ($this->labelText === null || $this->labelText === '') {
            return [];
        }

        $coords = $this->coordinates($context);

        if ($coords === null) {
            return [];
        }

        $viewport = $context->viewport;
        $color = $this->color ?? $context->theme->axisColor;

        if ($this->orientation === self::ORIENT_H) {
            return [new Label(
                text: $this->labelText,
                right: max(0.0, $viewport->width - $coords[2]),
                top: $coords[1],
                align: 'end',
                verticalAlign: 'bottom',
                color: $color,
            )];
        }

        return [new Label(
            text: $this->labelText,
            left: $coords[0],
            top: $coords[1],
            align: 'start',
            verticalAlign: 'top',
            color: $color,
        )];
    }

    /**
     * Endpoints of the line in viewport coordinates, or null when the value
     * falls outside the visible domain.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    private function coordinates(AnnotationContext $context): ?array
    {
        $viewport = $context->viewport;

        if ($this->orientation === self::ORIENT_H) {
            if ($this->value instanceof DateTimeInterface) {
                return null;
            }

            $scale = $context->yScaleFor($this->axis);

            if (!$scale instanceof Scale) {
                return null;
            }

            if (!$context->yInDomain($this->value, $this->axis)) {
                return null;
            }

            $y = $scale->map($this->value);

            return [$viewport->plotLeft(), $y, $viewport->plotRight(), $y];
        }

        if (!$context->xInDomain($this->value)) {
            return null;
        }

        $x = $context->mapX($this->value);

        if ($x === null) {
            return null;
        }

        return [$x, $viewport->plotTop(), $x, $viewport->plotBottom()];
    }
}

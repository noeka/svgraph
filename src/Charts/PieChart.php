<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Data\Slice;
use Noeka\Svgraph\Geometry\Path;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Svg\Label;
use Noeka\Svgraph\Svg\Tag;
use Noeka\Svgraph\Svg\Wrapper;

class PieChart extends AbstractChart
{
    /** @var list<Slice> */
    protected array $slices = [];

    protected float $thickness = 0.0;
    protected bool $showLegend = false;
    protected float $startAngle = 0.0;
    protected float $padAngle = 0.0;

    public function __construct()
    {
        parent::__construct();
        $this->variantClass = 'pie';
        $this->aspectRatio = 1.0;
    }

    /** @param iterable<mixed> $data */
    public function data(iterable $data): static
    {
        $this->slices = Slice::listFrom($data);
        return $this;
    }

    /**
     * Donut thickness as a fraction of the outer radius (0=pie, 0.4=typical donut, 1=hairline).
     */
    public function thickness(float $fraction): static
    {
        $this->thickness = max(0.0, min(0.95, $fraction));
        return $this;
    }

    public function legend(bool $on = true): static
    {
        $this->showLegend = $on;
        return $this;
    }

    /**
     * Rotation offset in degrees, clockwise from 12 o'clock. Default 0.
     */
    public function startAngle(float $degrees): static
    {
        $this->startAngle = $degrees;
        return $this;
    }

    /**
     * Gap between slices in degrees.
     */
    public function gap(float $degrees): static
    {
        $this->padAngle = max(0.0, $degrees);
        return $this;
    }

    public function render(): string
    {
        $viewport = new Viewport();
        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);

        if ($this->slices === []) {
            return $wrapper->render();
        }

        $total = 0.0;
        foreach ($this->slices as $slice) {
            $total += max(0.0, $slice->value);
        }
        if ($total <= 0.0) {
            return $wrapper->render();
        }

        $hasLegend = $this->showLegend;
        $cx = 50.0;
        $cy = $hasLegend ? 42.0 : 50.0;
        $outerRadius = $hasLegend ? 38.0 : 48.0;
        $innerRadius = $outerRadius * $this->thickness;

        $startRad = deg2rad($this->startAngle);
        $padRad = deg2rad($this->padAngle);

        if ($this->thickness === 0.0 && count($this->slices) === 1) {
            $only = $this->slices[0];
            $color = $only->color ?? $this->theme->colorAt(0);
            $wrapper->add(Tag::void('circle', [
                'cx' => Tag::formatFloat($cx),
                'cy' => Tag::formatFloat($cy),
                'r' => Tag::formatFloat($outerRadius),
                'fill' => $color,
            ]));
            if ($hasLegend) {
                $this->addLegend($wrapper);
            }
            return $wrapper->render();
        }

        $angle = $startRad;
        foreach ($this->slices as $i => $slice) {
            $value = max(0.0, $slice->value);
            if ($value <= 0.0) {
                continue;
            }
            $sweep = ($value / $total) * 2 * M_PI;
            $start = $angle + ($padRad / 2);
            $end = $angle + $sweep - ($padRad / 2);
            if ($end <= $start) {
                $angle += $sweep;
                continue;
            }
            $color = $slice->color ?? $this->theme->colorAt($i);
            $d = Path::arc($cx, $cy, $outerRadius, $innerRadius, $start, $end);
            $wrapper->add(Tag::void('path', [
                'd' => $d,
                'fill' => $color,
            ]));
            $angle += $sweep;
        }

        if ($hasLegend) {
            $this->addLegend($wrapper);
        }

        return $wrapper->render();
    }

    protected function addLegend(Wrapper $wrapper): void
    {
        $legendTopPercent = 86.0;
        $count = count($this->slices);
        if ($count === 0) {
            return;
        }
        $columns = min(4, max(1, $count));
        $colWidth = 100.0 / $columns;

        foreach ($this->slices as $i => $slice) {
            $col = $i % $columns;
            $row = intdiv($i, $columns);
            $left = $col * $colWidth;
            $top = $legendTopPercent + $row * 6.0;
            $color = $slice->color ?? $this->theme->colorAt($i);

            $swatch = '<span style="display:inline-block;width:0.5em;height:0.5em;'
                . 'border-radius:0.125em;margin-right:0.4em;vertical-align:middle;'
                . 'background:' . Tag::escapeAttr($color) . ';"></span>';
            $text = Tag::escapeText($slice->label);
            $wrapper->label(new Label(
                text: $swatch . $text,
                left: $left + 1,
                top: $top,
                align: 'start',
                verticalAlign: 'top',
                raw: true,
            ));
        }
    }
}

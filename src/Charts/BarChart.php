<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Geometry\Scale;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Svg\Label;
use Noeka\Svgraph\Svg\Tag;
use Noeka\Svgraph\Svg\Tooltip;
use Noeka\Svgraph\Svg\Wrapper;

class BarChart extends AbstractChart
{
    protected Series $series;

    protected ?string $color = null;
    protected float $gap = 0.2;
    protected bool $horizontal = false;
    protected float $cornerRadius = 0.0;
    protected bool $showAxes = false;
    protected bool $showGrid = false;
    protected int $tickCount = 5;
    protected bool $useColorPerBar = false;

    public function __construct()
    {
        parent::__construct();
        $this->variantClass = 'bar';
        $this->aspectRatio = 2.0;
        $this->series = new Series([]);
    }

    /** @param iterable<mixed> $data */
    public function data(iterable $data): static
    {
        $this->series = Series::from($data);
        return $this;
    }

    public function color(string $color): static
    {
        $this->color = $color;
        return $this;
    }

    /**
     * Use the theme palette to color each bar individually.
     */
    public function rainbow(bool $on = true): static
    {
        $this->useColorPerBar = $on;
        return $this;
    }

    /**
     * Gap between bars as a fraction of slot width (0.0 = touching, 0.5 = half-width gap).
     */
    public function gap(float $fraction): static
    {
        $this->gap = max(0.0, min(0.9, $fraction));
        return $this;
    }

    public function horizontal(bool $on = true): static
    {
        $this->horizontal = $on;
        return $this;
    }

    public function rounded(float $radius): static
    {
        $this->cornerRadius = max(0.0, $radius);
        return $this;
    }

    public function axes(bool $on = true): static
    {
        $this->showAxes = $on;
        return $this;
    }

    public function grid(bool $on = true): static
    {
        $this->showGrid = $on;
        return $this;
    }

    public function ticks(int $count): static
    {
        $this->tickCount = max(2, $count);
        return $this;
    }

    public function render(): string
    {
        if ($this->series->isEmpty()) {
            $viewport = new Viewport();
            $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
            $wrapper->setUserClass($this->cssClass);
            return $wrapper->render();
        }

        return $this->horizontal ? $this->renderHorizontal() : $this->renderVertical();
    }

    protected function renderVertical(): string
    {
        $hasLabels = $this->series->hasLabels();
        $padTop = $this->showAxes || $this->showGrid ? 4.0 : 0.0;
        $padRight = $this->showAxes || $this->showGrid ? 2.0 : 0.0;
        $padBottom = $hasLabels ? ($this->showAxes ? 14.0 : 8.0) : 0.0;
        $padLeft = $this->showAxes || $this->showGrid ? 12.0 : 0.0;

        $viewport = new Viewport(100, 100, $padTop, $padRight, $padBottom, $padLeft);
        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);

        $values = $this->series->values();
        if ($values === []) {
            return $wrapper->render();
        }
        $count = count($values);
        $max = max($values);
        $min = min(0.0, min($values));
        if ($min === $max) {
            $max += 1.0;
        }

        $yScale = Scale::linear($min, $max, $viewport->plotTop(), $viewport->plotBottom(), invert: true);
        $slotWidth = $viewport->plotWidth() / $count;
        $barWidth = $slotWidth * (1.0 - $this->gap);
        $barOffset = ($slotWidth - $barWidth) / 2.0;

        if ($this->showGrid) {
            foreach ($yScale->ticks($this->tickCount) as $tick) {
                $y = $yScale->map($tick);
                $wrapper->add(Tag::void('line', [
                    'x1' => Tag::formatFloat($viewport->plotLeft()),
                    'x2' => Tag::formatFloat($viewport->plotRight()),
                    'y1' => Tag::formatFloat($y),
                    'y2' => Tag::formatFloat($y),
                    'stroke' => $this->theme->gridColor,
                    'stroke-width' => '1',
                    'vector-effect' => 'non-scaling-stroke',
                ]));
            }
        }

        $chartId = $this->chartId();
        $baseY = $yScale->map(0.0);
        $wrapper->markHasSeriesElements();
        foreach ($this->series->points as $i => $point) {
            $value = $point->value;
            $x = $viewport->plotLeft() + $i * $slotWidth + $barOffset;
            $valueY = $yScale->map($value);
            $top = min($baseY, $valueY);
            $height = abs($valueY - $baseY);
            $color = $this->useColorPerBar ? $this->theme->colorAt($i) : ($this->color ?? $this->theme->fill);
            $seriesIndex = $this->useColorPerBar ? $i : 0;
            $id = "{$chartId}-pt-{$i}";
            $attrs = [
                'class' => "series-{$seriesIndex}",
                'x' => Tag::formatFloat($x),
                'y' => Tag::formatFloat($top),
                'width' => Tag::formatFloat($barWidth),
                'height' => Tag::formatFloat($height),
                'fill' => $color,
            ];
            if ($this->cornerRadius > 0.0) {
                $attrs['rx'] = Tag::formatFloat($this->cornerRadius);
                $attrs['ry'] = Tag::formatFloat($this->cornerRadius);
            }
            $tipText = $this->tooltip($point->label, $value);
            $rect = Tag::make('rect', $attrs)->append(Tag::make('title')->append($tipText));
            $wrapper->add($this->buildLink($point->link, $id, $rect));
            $wrapper->tooltip(new Tooltip(
                id: $id,
                text: Tag::escapeText($tipText),
                leftPct: ($x + $barWidth / 2) / $viewport->width * 100,
                topPct: $top / $viewport->height * 100,
            ));
        }

        if ($this->showAxes) {
            $wrapper->add(Tag::void('line', [
                'x1' => Tag::formatFloat($viewport->plotLeft()),
                'x2' => Tag::formatFloat($viewport->plotRight()),
                'y1' => Tag::formatFloat($baseY),
                'y2' => Tag::formatFloat($baseY),
                'stroke' => $this->theme->axisColor,
                'stroke-width' => '1',
                'vector-effect' => 'non-scaling-stroke',
            ]));

            foreach ($yScale->ticks($this->tickCount) as $tick) {
                $y = $yScale->map($tick);
                $wrapper->label(new Label(
                    text: $this->formatNumber($tick),
                    left: 0,
                    top: $y,
                    align: 'start',
                    verticalAlign: 'middle',
                ));
            }
        }

        if ($hasLabels) {
            foreach ($this->series->labels() as $i => $label) {
                if ($label === null || $label === '') {
                    continue;
                }
                $x = $viewport->plotLeft() + ($i + 0.5) * $slotWidth;
                $wrapper->label(new Label(
                    text: $label,
                    left: $x,
                    bottom: 0,
                    align: 'center',
                    verticalAlign: 'bottom',
                ));
            }
        }

        return $wrapper->render();
    }

    protected function renderHorizontal(): string
    {
        $hasLabels = $this->series->hasLabels();
        $padTop = 2.0;
        $padRight = $this->showAxes ? 6.0 : 2.0;
        $padBottom = $this->showAxes ? 8.0 : 2.0;
        $padLeft = $hasLabels ? 22.0 : 2.0;

        $viewport = new Viewport(100, 100, $padTop, $padRight, $padBottom, $padLeft);
        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);

        $values = $this->series->values();
        if ($values === []) {
            return $wrapper->render();
        }
        $count = count($values);
        $max = max($values);
        $min = min(0.0, min($values));
        if ($min === $max) {
            $max += 1.0;
        }

        $xScale = Scale::linear($min, $max, $viewport->plotLeft(), $viewport->plotRight());
        $slotHeight = $viewport->plotHeight() / $count;
        $barHeight = $slotHeight * (1.0 - $this->gap);
        $barOffset = ($slotHeight - $barHeight) / 2.0;

        if ($this->showGrid) {
            foreach ($xScale->ticks($this->tickCount) as $tick) {
                $x = $xScale->map($tick);
                $wrapper->add(Tag::void('line', [
                    'x1' => Tag::formatFloat($x),
                    'x2' => Tag::formatFloat($x),
                    'y1' => Tag::formatFloat($viewport->plotTop()),
                    'y2' => Tag::formatFloat($viewport->plotBottom()),
                    'stroke' => $this->theme->gridColor,
                    'stroke-width' => '1',
                    'vector-effect' => 'non-scaling-stroke',
                ]));
            }
        }

        $chartId = $this->chartId();
        $baseX = $xScale->map(0.0);
        $wrapper->markHasSeriesElements();
        foreach ($this->series->points as $i => $point) {
            $value = $point->value;
            $y = $viewport->plotTop() + $i * $slotHeight + $barOffset;
            $valueX = $xScale->map($value);
            $left = min($baseX, $valueX);
            $width = abs($valueX - $baseX);
            $color = $this->useColorPerBar ? $this->theme->colorAt($i) : ($this->color ?? $this->theme->fill);
            $seriesIndex = $this->useColorPerBar ? $i : 0;
            $id = "{$chartId}-pt-{$i}";
            $attrs = [
                'class' => "series-{$seriesIndex}",
                'x' => Tag::formatFloat($left),
                'y' => Tag::formatFloat($y),
                'width' => Tag::formatFloat($width),
                'height' => Tag::formatFloat($barHeight),
                'fill' => $color,
            ];
            if ($this->cornerRadius > 0.0) {
                $attrs['rx'] = Tag::formatFloat($this->cornerRadius);
                $attrs['ry'] = Tag::formatFloat($this->cornerRadius);
            }
            $tipText = $this->tooltip($point->label, $value);
            $rect = Tag::make('rect', $attrs)->append(Tag::make('title')->append($tipText));
            $wrapper->add($this->buildLink($point->link, $id, $rect));
            $wrapper->tooltip(new Tooltip(
                id: $id,
                text: Tag::escapeText($tipText),
                leftPct: ($left + $width) / $viewport->width * 100,
                topPct: ($y + $barHeight / 2) / $viewport->height * 100,
            ));
        }

        if ($hasLabels) {
            foreach ($this->series->labels() as $i => $label) {
                if ($label === null || $label === '') {
                    continue;
                }
                $y = $viewport->plotTop() + ($i + 0.5) * $slotHeight;
                $wrapper->label(new Label(
                    text: $label,
                    left: 0,
                    top: $y,
                    align: 'start',
                    verticalAlign: 'middle',
                ));
            }
        }

        if ($this->showAxes) {
            foreach ($xScale->ticks($this->tickCount) as $tick) {
                $x = $xScale->map($tick);
                $wrapper->label(new Label(
                    text: $this->formatNumber($tick),
                    left: $x,
                    bottom: 0,
                    align: 'center',
                    verticalAlign: 'bottom',
                ));
            }
        }

        return $wrapper->render();
    }
}

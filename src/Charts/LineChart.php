<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Geometry\Path;
use Noeka\Svgraph\Geometry\Scale;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Svg\Label;
use Noeka\Svgraph\Svg\Tag;
use Noeka\Svgraph\Svg\Tooltip;
use Noeka\Svgraph\Svg\Wrapper;

class LineChart extends AbstractChart
{
    protected Series $series;

    protected ?string $strokeColor = null;
    protected ?float $strokeWidth = null;

    protected bool $fillEnabled = false;
    protected ?string $fillColor = null;
    protected float $fillOpacity = 0.15;

    protected bool $smooth = false;
    protected bool $showAxes = false;
    protected bool $showGrid = false;
    protected bool $showPoints = false;

    protected int $tickCount = 5;

    public function __construct()
    {
        parent::__construct();
        $this->variantClass = 'line';
        $this->series = new Series([]);
    }

    /** @param iterable<mixed> $data */
    public function series(iterable $data): static
    {
        $this->series = Series::from($data);
        return $this;
    }

    /** @param iterable<mixed> $data */
    public function data(iterable $data): static
    {
        return $this->series($data);
    }

    public function stroke(string $color, ?float $width = null): static
    {
        $this->strokeColor = $color;
        if ($width !== null) {
            $this->strokeWidth = $width;
        }
        return $this;
    }

    public function strokeWidth(float $width): static
    {
        $this->strokeWidth = $width;
        return $this;
    }

    public function fillBelow(?string $color = null, float $opacity = 0.15): static
    {
        $this->fillEnabled = true;
        $this->fillColor = $color;
        $this->fillOpacity = $opacity;
        return $this;
    }

    public function smooth(bool $smooth = true): static
    {
        $this->smooth = $smooth;
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

    public function points(bool $on = true): static
    {
        $this->showPoints = $on;
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
            return $this->renderEmpty();
        }

        $hasLabels = $this->series->hasLabels();
        $padTop = $this->showAxes || $this->showGrid ? 4.0 : 0.0;
        $padRight = $this->showAxes || $this->showGrid ? 2.0 : 0.0;
        $padBottom = $hasLabels && ($this->showAxes || $this->showGrid) ? 14.0 : 0.0;
        $padLeft = $this->showAxes || $this->showGrid ? 12.0 : 0.0;

        $viewport = new Viewport(100, 100, $padTop, $padRight, $padBottom, $padLeft);

        $values = $this->series->values();
        if ($values === []) {
            return $this->renderEmpty();
        }
        $count = count($values);
        $min = min($values);
        $max = max($values);
        if ($min === $max) {
            $min -= 1.0;
            $max += 1.0;
        }
        $padding = ($max - $min) * 0.1;
        $domainMin = $min - $padding;
        $domainMax = $max + $padding;

        $xScale = Scale::linear(0, max(1, $count - 1), $viewport->plotLeft(), $viewport->plotRight());
        $yScale = Scale::linear($domainMin, $domainMax, $viewport->plotTop(), $viewport->plotBottom(), invert: true);

        $points = [];
        foreach ($values as $i => $v) {
            $points[] = [$xScale->map((float) $i), $yScale->map($v)];
        }

        $stroke = $this->strokeColor ?? $this->theme->stroke;
        $strokeWidth = $this->strokeWidth ?? $this->theme->strokeWidth;
        $fillColor = $this->fillColor ?? $stroke;

        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);

        if ($this->showGrid) {
            foreach ($this->buildGridLines($yScale, $viewport) as $gridLine) {
                $wrapper->add($gridLine);
            }
        }

        if ($this->showAxes) {
            foreach ($this->buildAxisLines($viewport) as $axis) {
                $wrapper->add($axis);
            }
        }

        if ($this->fillEnabled) {
            $areaD = Path::area($points, $viewport->plotBottom(), $this->smooth);
            $wrapper->add(Tag::void('path', [
                'd' => $areaD,
                'fill' => $fillColor,
                'fill-opacity' => Tag::formatFloat($this->fillOpacity),
                'stroke' => 'none',
            ]));
        }

        $lineD = $this->smooth ? Path::smoothLine($points) : Path::line($points);
        $wrapper->add(Tag::void('path', [
            'd' => $lineD,
            'fill' => 'none',
            'stroke' => $stroke,
            'stroke-width' => Tag::formatFloat($strokeWidth),
            'stroke-linecap' => 'round',
            'stroke-linejoin' => 'round',
            'vector-effect' => 'non-scaling-stroke',
        ]));

        if ($this->showPoints) {
            $chartId = $this->chartId();
            $r = $strokeWidth * 0.6;
            $rx = $r / max(0.01, $this->aspectRatio);
            $hitR = max(4.0, $r * 2);
            $hitRx = $hitR / max(0.01, $this->aspectRatio);
            $wrapper->markHasSeriesElements();
            foreach ($points as $i => [$x, $y]) {
                $p = $this->series->points[$i];
                $id = "{$chartId}-pt-{$i}";
                $tipText = $this->tooltip($p->label, $p->value);
                // Wrap visual marker + transparent hit target in a <g> so that
                // CSS :hover/:focus-within on the group can highlight the visual
                // ellipse even though the (larger) hit target intercepts events.
                $group = Tag::make('g', ['class' => 'series-0']);
                $group->append(Tag::make('ellipse', [
                    'cx' => Tag::formatFloat($x),
                    'cy' => Tag::formatFloat($y),
                    'rx' => Tag::formatFloat($rx),
                    'ry' => Tag::formatFloat($r),
                    'fill' => $stroke,
                ])->append(Tag::make('title')->append($tipText)));
                // Transparent hit target — larger than the visual dot for easier hover/focus.
                $group->append(Tag::make('ellipse', [
                    'id' => $id,
                    'cx' => Tag::formatFloat($x),
                    'cy' => Tag::formatFloat($y),
                    'rx' => Tag::formatFloat($hitRx),
                    'ry' => Tag::formatFloat($hitR),
                    'fill' => 'transparent',
                    'tabindex' => '0',
                ])->append(Tag::make('title')->append($tipText)));
                $wrapper->add($group);
                $wrapper->tooltip(new Tooltip(
                    id: $id,
                    text: Tag::escapeText($tipText),
                    leftPct: $x / $viewport->width * 100,
                    topPct: $y / $viewport->height * 100,
                ));
            }
        }

        if ($this->showAxes) {
            $this->addLabels($wrapper, $xScale, $yScale);
        }

        return $wrapper->render();
    }

    /**
     * @return list<Tag>
     */
    protected function buildGridLines(Scale $yScale, Viewport $viewport): array
    {
        $lines = [];
        foreach ($yScale->ticks($this->tickCount) as $tick) {
            $y = $yScale->map($tick);
            $lines[] = Tag::void('line', [
                'x1' => Tag::formatFloat($viewport->plotLeft()),
                'x2' => Tag::formatFloat($viewport->plotRight()),
                'y1' => Tag::formatFloat($y),
                'y2' => Tag::formatFloat($y),
                'stroke' => $this->theme->gridColor,
                'stroke-width' => '1',
                'vector-effect' => 'non-scaling-stroke',
            ]);
        }
        return $lines;
    }

    /**
     * @return list<Tag>
     */
    protected function buildAxisLines(Viewport $viewport): array
    {
        return [
            Tag::void('line', [
                'x1' => Tag::formatFloat($viewport->plotLeft()),
                'x2' => Tag::formatFloat($viewport->plotLeft()),
                'y1' => Tag::formatFloat($viewport->plotTop()),
                'y2' => Tag::formatFloat($viewport->plotBottom()),
                'stroke' => $this->theme->axisColor,
                'stroke-width' => '1',
                'vector-effect' => 'non-scaling-stroke',
            ]),
            Tag::void('line', [
                'x1' => Tag::formatFloat($viewport->plotLeft()),
                'x2' => Tag::formatFloat($viewport->plotRight()),
                'y1' => Tag::formatFloat($viewport->plotBottom()),
                'y2' => Tag::formatFloat($viewport->plotBottom()),
                'stroke' => $this->theme->axisColor,
                'stroke-width' => '1',
                'vector-effect' => 'non-scaling-stroke',
            ]),
        ];
    }

    protected function addLabels(Wrapper $wrapper, Scale $xScale, Scale $yScale): void
    {
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

        $labels = $this->series->labels();
        foreach ($labels as $i => $label) {
            if ($label === null || $label === '') {
                continue;
            }
            $x = $xScale->map((float) $i);
            $wrapper->label(new Label(
                text: $label,
                left: $x,
                bottom: 0,
                align: 'center',
                verticalAlign: 'bottom',
            ));
        }
    }

    protected function renderEmpty(): string
    {
        $viewport = new Viewport();
        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);
        return $wrapper->render();
    }
}

<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Data\SeriesCollection;
use Noeka\Svgraph\Geometry\Path;
use Noeka\Svgraph\Geometry\Scale;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Svg\Label;
use Noeka\Svgraph\Svg\Tag;
use Noeka\Svgraph\Svg\Tooltip;
use Noeka\Svgraph\Svg\Wrapper;

class LineChart extends AbstractChart
{
    protected SeriesCollection $seriesCollection;

    protected ?string $strokeColor = null;
    protected ?float $strokeWidth = null;

    protected bool $fillEnabled = false;
    protected ?string $fillColor = null;
    protected float $fillOpacity = 0.15;

    protected bool $smooth = false;
    protected bool $showAxes = false;
    protected bool $showGrid = false;
    protected bool $showPoints = false;
    protected bool $showCrosshair = false;

    protected int $tickCount = 5;

    public function __construct()
    {
        parent::__construct();
        $this->variantClass = 'line';
        $this->seriesCollection = new SeriesCollection();
    }

    /** @param iterable<mixed> $data */
    public function series(iterable $data): static
    {
        $this->seriesCollection = new SeriesCollection([Series::from($data)]);
        return $this;
    }

    /** @param iterable<mixed> $data */
    public function data(iterable $data): static
    {
        return $this->series($data);
    }

    /**
     * Append a series. Combine with `data()` (which sets the first) or call
     * `addSeries()` repeatedly to build up the chart series-by-series.
     */
    public function addSeries(Series $series): static
    {
        $this->seriesCollection = $this->seriesCollection->with($series);
        return $this;
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

    /**
     * Opt in to a hover crosshair. When the pointer enters the chart area
     * the column nearest the cursor reveals a vertical guide line, brightens
     * every series' marker at that x, and opens every series' tooltip.
     *
     * Pure CSS — uses `:has(...)` so the feature degrades silently in browsers
     * without it. Implies marker emission: when `points()` is also off, the
     * markers stay invisible until a column is hovered, then fade in.
     */
    public function crosshair(bool $on = true): static
    {
        $this->showCrosshair = $on;
        return $this;
    }

    public function ticks(int $count): static
    {
        $this->tickCount = max(2, $count);
        return $this;
    }

    public function render(): string
    {
        if ($this->seriesCollection->isEmpty()) {
            return $this->renderEmpty();
        }

        $hasLabels = $this->seriesCollection->hasLabels();
        $padTop = $this->showAxes || $this->showGrid ? 4.0 : 0.0;
        $padRight = $this->showAxes || $this->showGrid ? 2.0 : 0.0;
        $padBottom = $hasLabels && ($this->showAxes || $this->showGrid) ? 14.0 : 0.0;
        $padLeft = $this->showAxes || $this->showGrid ? 12.0 : 0.0;

        $viewport = new Viewport(100, 100, $padTop, $padRight, $padBottom, $padLeft);

        $maxLen = $this->seriesCollection->maxLength();
        if ($maxLen === 0) {
            return $this->renderEmpty();
        }

        $min = $this->seriesCollection->valueMin();
        $max = $this->seriesCollection->valueMax();
        if ($min === $max) {
            $min -= 1.0;
            $max += 1.0;
        }
        $padding = ($max - $min) * 0.1;
        $domainMin = $min - $padding;
        $domainMax = $max + $padding;

        $xScale = Scale::linear(0, max(1, $maxLen - 1), $viewport->plotLeft(), $viewport->plotRight());
        $yScale = Scale::linear($domainMin, $domainMax, $viewport->plotTop(), $viewport->plotBottom(), invert: true);

        $strokeWidth = $this->strokeWidth ?? $this->theme->strokeWidth;

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

        if ($this->animated) {
            $wrapper->enableAnimation();
        }

        if ($this->showCrosshair) {
            foreach ($this->buildCrosshairLines($maxLen, $xScale, $viewport) as $line) {
                $wrapper->add($line);
            }
        }

        foreach ($this->seriesCollection->items as $i => $series) {
            $this->renderSeries($wrapper, $series, $i, $xScale, $yScale, $viewport, $strokeWidth);
        }

        if ($this->showCrosshair) {
            foreach ($this->buildCrosshairHits($maxLen, $xScale, $viewport) as $hit) {
                $wrapper->add($hit);
            }
            $wrapper->enableCrosshair($maxLen);
        }

        if ($this->showAxes) {
            $this->addLabels($wrapper, $xScale, $yScale);
        }

        return $wrapper->render();
    }

    private function renderSeries(
        Wrapper $wrapper,
        Series $series,
        int $index,
        Scale $xScale,
        Scale $yScale,
        Viewport $viewport,
        float $strokeWidth,
    ): void {
        if ($series->isEmpty()) {
            return;
        }

        $color = $this->resolveColor($series, $index);
        $points = [];
        foreach ($series->values as $i => $v) {
            $points[] = [$xScale->map((float) $i), $yScale->map($v)];
        }

        if ($this->fillEnabled) {
            $fillColor = $this->fillColor ?? $color;
            $areaD = Path::area($points, $viewport->plotBottom(), $this->smooth);
            $wrapper->add(Tag::void('path', [
                'class' => "series-{$index}",
                'd' => $areaD,
                'fill' => $fillColor,
                'fill-opacity' => Tag::formatFloat($this->fillOpacity),
                'stroke' => 'none',
            ]));
        }

        $lineD = $this->smooth ? Path::smoothLine($points) : Path::line($points);
        $lineAttrs = [
            'class' => "series-{$index}",
            'd' => $lineD,
            'fill' => 'none',
            'stroke' => $color,
            'stroke-width' => Tag::formatFloat($strokeWidth),
            'stroke-linecap' => 'round',
            'stroke-linejoin' => 'round',
            'vector-effect' => 'non-scaling-stroke',
        ];
        if ($this->animated) {
            $lineAttrs['class'] = "series-{$index} svgraph-line-path";
            $lineAttrs['pathLength'] = '1';
            $lineAttrs['stroke-dasharray'] = '1';
            $lineAttrs['stroke-dashoffset'] = '1';
        }
        $wrapper->add(Tag::void('path', $lineAttrs));

        if ($this->showPoints || $this->showCrosshair) {
            $this->renderMarkers($wrapper, $series, $index, $points, $color, $strokeWidth, $viewport);
        }
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     */
    private function renderMarkers(
        Wrapper $wrapper,
        Series $series,
        int $index,
        array $points,
        string $color,
        float $strokeWidth,
        Viewport $viewport,
    ): void {
        $chartId = $this->chartId();
        $r = $strokeWidth * 0.6;
        $rx = $r / max(0.01, $this->aspectRatio);
        $hitR = max(4.0, $r * 2);
        $hitRx = $hitR / max(0.01, $this->aspectRatio);
        $wrapper->markHasSeriesElements();

        // Ghost markers: when crosshair is on but `points()` was never called,
        // emit the marker DOM but keep the visible ellipse opacity:0 by default.
        // The crosshair CSS (and direct hover/focus on the marker) reveals it.
        $ghost = $this->showCrosshair && !$this->showPoints;

        foreach ($points as $i => [$x, $y]) {
            $p = $series->points[$i];
            $id = "{$chartId}-s{$index}-pt-{$i}";
            $tipText = $this->tooltip($this->labelFor($series, $p->label), $p->value);
            $hasLink = $p->link !== null;
            // Wrap visual marker + transparent hit target in a <g> so that
            // CSS :hover/:focus-within on the group can highlight the visual
            // ellipse even though the (larger) hit target intercepts events.
            $groupAttrs = ['class' => "series-{$index}"];
            if ($this->showCrosshair) {
                $groupAttrs['data-x'] = (string) $i;
            }
            $group = Tag::make('g', $groupAttrs);
            $visualAttrs = [
                'cx' => Tag::formatFloat($x),
                'cy' => Tag::formatFloat($y),
                'rx' => Tag::formatFloat($rx),
                'ry' => Tag::formatFloat($r),
                'fill' => $color,
            ];
            if ($ghost) {
                $visualAttrs['opacity'] = '0';
            }
            $group->append(Tag::make('ellipse', $visualAttrs)->append(Tag::make('title')->append($tipText)));
            $hitAttrs = [
                'id' => $hasLink ? null : $id,
                'cx' => Tag::formatFloat($x),
                'cy' => Tag::formatFloat($y),
                'rx' => Tag::formatFloat($hitRx),
                'ry' => Tag::formatFloat($hitR),
                'fill' => 'transparent',
                'tabindex' => $hasLink ? null : '0',
            ];
            $group->append(Tag::make('ellipse', $hitAttrs)->append(Tag::make('title')->append($tipText)));
            $wrapper->add($hasLink ? $this->buildLink($p->link, $id, $group) : $group);
            $wrapper->tooltip(new Tooltip(
                id: $id,
                text: Tag::escapeText($tipText),
                leftPct: $x / $viewport->width * 100,
                topPct: $y / $viewport->height * 100,
                dataX: $this->showCrosshair ? $i : null,
            ));
        }
    }

    /**
     * Per-series color: explicit Series->color wins, then `->stroke()` for
     * series 0 (the chart-level shortcut), then the theme palette.
     */
    private function resolveColor(Series $series, int $index): string
    {
        if ($series->color !== null) {
            return $series->color;
        }
        if ($index === 0 && $this->strokeColor !== null) {
            return $this->strokeColor;
        }
        return $this->theme->colorAt($index);
    }

    /**
     * Prefix tooltip with the series name when set so multi-series points
     * can be told apart.
     */
    private function labelFor(Series $series, ?string $pointLabel): ?string
    {
        if ($series->name === '') {
            return $pointLabel;
        }
        return $pointLabel === null || $pointLabel === ''
            ? $series->name
            : "{$series->name} — {$pointLabel}";
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
     * Vertical guide lines, one per x-column. Hidden by default; the wrapper's
     * crosshair CSS reveals the line whose `data-x` matches the hovered column.
     *
     * @return list<Tag>
     */
    protected function buildCrosshairLines(int $maxLen, Scale $xScale, Viewport $viewport): array
    {
        $lines = [];
        for ($i = 0; $i < $maxLen; $i++) {
            $x = $xScale->map((float) $i);
            $lines[] = Tag::void('line', [
                'class' => 'svgraph-crosshair',
                'data-x' => (string) $i,
                'x1' => Tag::formatFloat($x),
                'x2' => Tag::formatFloat($x),
                'y1' => Tag::formatFloat($viewport->plotTop()),
                'y2' => Tag::formatFloat($viewport->plotBottom()),
                'stroke' => $this->theme->axisColor,
                'stroke-width' => '1',
                'stroke-dasharray' => '2,2',
                'vector-effect' => 'non-scaling-stroke',
            ]);
        }
        return $lines;
    }

    /**
     * Per-column hit rects covering the plot area. Each column owns the
     * half-gap on either side of its x position so the cursor snaps to the
     * nearest column. Emitted last in the SVG so they sit above the data and
     * reliably catch pointer events.
     *
     * @return list<Tag>
     */
    protected function buildCrosshairHits(int $maxLen, Scale $xScale, Viewport $viewport): array
    {
        $top = $viewport->plotTop();
        $height = $viewport->plotBottom() - $top;
        $left = $viewport->plotLeft();
        $right = $viewport->plotRight();

        $xs = [];
        for ($i = 0; $i < $maxLen; $i++) {
            $xs[] = $xScale->map((float) $i);
        }

        $rects = [];
        foreach ($xs as $i => $x) {
            $colLeft = $i === 0 ? $left : ($xs[$i - 1] + $x) / 2;
            $colRight = $i === $maxLen - 1 ? $right : ($x + $xs[$i + 1]) / 2;
            $rects[] = Tag::void('rect', [
                'class' => 'svgraph-x-hit',
                'data-x' => (string) $i,
                'x' => Tag::formatFloat($colLeft),
                'y' => Tag::formatFloat($top),
                'width' => Tag::formatFloat(max(0.0, $colRight - $colLeft)),
                'height' => Tag::formatFloat(max(0.0, $height)),
                'fill' => 'transparent',
            ]);
        }
        return $rects;
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

        foreach ($this->seriesCollection->commonLabels() as $i => $label) {
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

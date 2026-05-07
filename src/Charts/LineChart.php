<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use InvalidArgumentException;
use Noeka\Svgraph\Annotations\AnnotationContext;
use Noeka\Svgraph\Annotations\AnnotationLayer;
use Noeka\Svgraph\Data\Axis;
use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Data\SeriesCollection;
use Noeka\Svgraph\Geometry\LogScale;
use Noeka\Svgraph\Geometry\Path;
use Noeka\Svgraph\Geometry\Scale;
use Noeka\Svgraph\Geometry\TimeScale;
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
    protected bool $showLegend = false;

    protected int $tickCount = 5;

    protected bool $useTimeAxis = false;
    protected ?string $timeAxisLocale = null;
    protected ?\DateTimeZone $timeAxisTz = null;
    protected ?string $timeAxisFormat = null;

    protected ?float $leftLogBase = null;
    protected ?float $rightLogBase = null;
    protected bool $secondaryAxisEnabled = false;
    protected ?Scale $secondaryAxisOverride = null;

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

    /**
     * Treat point x-values as datetimes. Series points carrying a
     * `DateTimeImmutable` (e.g. from `[[$dt, 10], [$dt2, 24]]` tuples) are
     * positioned by time across the plot area; the x-axis is labelled with
     * locale-aware ticks chosen to fit `ticks()` count.
     *
     * `$tz` accepts any value `DateTimeZone::__construct` accepts. `$format`
     * is treated as an ICU pattern when ext-intl is present, otherwise as a
     * `DateTime::format()` string.
     */
    public function timeAxis(?string $locale = null, ?string $tz = null, ?string $format = null): static
    {
        $this->useTimeAxis = true;
        $this->timeAxisLocale = $locale;
        $this->timeAxisTz = $tz !== null ? new \DateTimeZone($tz) : null;
        $this->timeAxisFormat = $format;
        return $this;
    }

    /**
     * Render a CSS-only toggle legend below the chart. Each entry is a
     * `<label>` bound to a hidden checkbox; clicking an entry hides its
     * series and dims the entry. State is page-local (no JS = no
     * persistence) and the Y axis does not rescale to the remaining series.
     */
    public function legend(bool $on = true): static
    {
        $this->showLegend = $on;
        return $this;
    }

    /**
     * Plot the chosen Y axis on a logarithmic scale. Useful for orders-of-
     * magnitude data — revenue across decades, file sizes, request latency.
     *
     * Targeting `'right'` implicitly enables the secondary Y axis (you can
     * still call `secondaryAxis()` to override the auto-derived domain).
     *
     * Throws `InvalidArgumentException` at render time if the axis ends up
     * with non-positive data; log scales require strictly positive values.
     */
    public function logScale(float $base = 10.0, Axis|string $axis = Axis::Left): static
    {
        $resolved = $axis instanceof Axis ? $axis : Axis::from($axis);
        if ($resolved === Axis::Right) {
            $this->rightLogBase = $base;
            $this->secondaryAxisEnabled = true;
        } else {
            $this->leftLogBase = $base;
        }
        return $this;
    }

    /**
     * Enable a second Y axis on the right edge of the plot. Series carrying
     * `Axis::Right` (via `Series::onAxis('right')`) plot against this axis;
     * the rest stay on the primary axis.
     *
     * Pass a `Scale` to fix the secondary domain (and pick log/linear)
     * explicitly; otherwise the chart auto-derives a linear domain from
     * right-axis series. The supplied scale's range is replaced with the
     * viewport's plot extents — only the domain (and `LogScale` base) is
     * read from it.
     */
    public function secondaryAxis(?Scale $scale = null): static
    {
        $this->secondaryAxisEnabled = true;
        $this->secondaryAxisOverride = $scale;
        if ($scale instanceof LogScale) {
            $this->rightLogBase = $scale->base;
        }
        return $this;
    }

    public function render(): string
    {
        if ($this->seriesCollection->isEmpty()) {
            return $this->renderEmpty();
        }

        $hasLabels = $this->seriesCollection->hasLabels();
        $hasTimeAxis = $this->useTimeAxis && $this->collectTimes() !== [];
        $hasSecondary = $this->secondaryAxisEnabled;
        $padTop = $this->showAxes || $this->showGrid ? 4.0 : 0.0;
        $padRight = $hasSecondary && ($this->showAxes || $this->showGrid)
            ? 12.0
            : ($this->showAxes || $this->showGrid ? 2.0 : 0.0);
        $padBottom = ($hasLabels || $hasTimeAxis) && ($this->showAxes || $this->showGrid) ? 14.0 : 0.0;
        $padLeft = $this->showAxes || $this->showGrid ? 12.0 : 0.0;

        $viewport = new Viewport(100, 100, $padTop, $padRight, $padBottom, $padLeft);

        $maxLen = $this->seriesCollection->maxLength();
        if ($maxLen === 0) {
            return $this->renderEmpty();
        }

        $xScale = Scale::linear(0, max(1, $maxLen - 1), $viewport->plotLeft(), $viewport->plotRight());
        $leftYScale = $this->buildYScale(Axis::Left, $viewport, $this->leftLogBase, null);
        $rightYScale = $hasSecondary
            ? $this->buildYScale(Axis::Right, $viewport, $this->rightLogBase, $this->secondaryAxisOverride)
            : null;
        $timeScale = $hasTimeAxis ? $this->buildTimeScale($viewport) : null;

        $primaryXs = $this->buildPrimaryXs($maxLen, $xScale, $timeScale);

        $strokeWidth = $this->strokeWidth ?? $this->theme->strokeWidth;

        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);

        if ($this->showGrid) {
            foreach ($this->buildGridLines($leftYScale, $viewport) as $gridLine) {
                $wrapper->add($gridLine);
            }
        }

        if ($this->showAxes) {
            foreach ($this->buildAxisLines($viewport, $rightYScale instanceof Scale) as $axis) {
                $wrapper->add($axis);
            }
        }

        if ($this->animated) {
            $wrapper->enableAnimation();
        }

        if ($this->showCrosshair) {
            foreach ($this->buildCrosshairLines($primaryXs, $viewport) as $line) {
                $wrapper->add($line);
            }
        }

        $annotationContext = new AnnotationContext(
            viewport: $viewport,
            theme: $this->theme,
            xScale: $timeScale ?? $xScale,
            yScale: $leftYScale,
            rightYScale: $rightYScale,
        );
        $this->renderAnnotationLayer($wrapper, $annotationContext, AnnotationLayer::BehindData);

        foreach ($this->seriesCollection->items as $i => $series) {
            $ys = $series->axis === Axis::Right && $rightYScale instanceof Scale ? $rightYScale : $leftYScale;
            $this->renderSeries($wrapper, $series, $i, $xScale, $ys, $viewport, $strokeWidth, $timeScale);
        }

        if ($this->showCrosshair) {
            foreach ($this->buildCrosshairHits($primaryXs, $viewport) as $hit) {
                $wrapper->add($hit);
            }
            $wrapper->enableCrosshair($maxLen);
        }

        $this->renderAnnotationLayer($wrapper, $annotationContext, AnnotationLayer::OverData);

        if ($this->showAxes) {
            $this->addLabels($wrapper, $xScale, $leftYScale, $rightYScale, $timeScale, $viewport);
        }

        $this->emitAnnotationLabels($wrapper, $annotationContext);

        if ($this->showLegend) {
            $wrapper->setLegend($this->buildLegendEntries());
        }

        $this->applyAccessibility($wrapper);

        return $wrapper->render();
    }

    /**
     * Build a Y scale for the requested axis. Combines the min/max of every
     * series assigned to that axis, applies 10% padding (linear) or one
     * decade's headroom (log), and returns either a `Scale` or `LogScale`.
     *
     * `$override` lets a caller supply a fixed domain via `secondaryAxis()`.
     * Its range is replaced with the viewport's plot extents (only domain
     * and, for `LogScale`, base are read from the override).
     */
    private function buildYScale(Axis $axis, Viewport $viewport, ?float $logBase, ?Scale $override): Scale
    {
        $rangeStart = $viewport->plotTop();
        $rangeEnd = $viewport->plotBottom();

        if ($override instanceof LogScale) {
            return new LogScale($override->domainMin, $override->domainMax, $rangeStart, $rangeEnd, true, $override->base);
        }
        if ($override instanceof Scale) {
            return new Scale($override->domainMin, $override->domainMax, $rangeStart, $rangeEnd, true);
        }

        [$min, $max] = $this->axisDomain($axis);
        if ($min === $max) {
            $min -= 1.0;
            $max += 1.0;
        }

        if ($logBase !== null) {
            if ($min <= 0.0) {
                throw new InvalidArgumentException(
                    'Log scale on the ' . $axis->value . ' Y axis requires strictly positive data; '
                    . 'minimum value seen is ' . $min . '.',
                );
            }
            return LogScale::log($min, $max, $rangeStart, $rangeEnd, invert: true, base: $logBase);
        }

        $padding = ($max - $min) * 0.1;
        return Scale::linear($min - $padding, $max + $padding, $rangeStart, $rangeEnd, invert: true);
    }

    /**
     * Combined min/max across every non-empty series on the given axis.
     * Falls back to the full collection when no series target the requested
     * axis (e.g. secondary axis enabled without any right-flagged series),
     * so the secondary axis still mirrors the primary's data range.
     *
     * @return array{0: float, 1: float}
     */
    private function axisDomain(Axis $axis): array
    {
        $min = INF;
        $max = -INF;
        $sawAny = false;
        foreach ($this->seriesCollection->items as $series) {
            if ($series->isEmpty()) {
                continue;
            }
            if ($series->axis !== $axis) {
                continue;
            }
            $sawAny = true;
            if ($series->min < $min) {
                $min = $series->min;
            }
            if ($series->max > $max) {
                $max = $series->max;
            }
        }
        if (!$sawAny) {
            $min = $this->seriesCollection->valueMin();
            $max = $this->seriesCollection->valueMax();
        }
        return [$min, $max];
    }

    /**
     * @return list<\DateTimeImmutable>
     */
    private function collectTimes(): array
    {
        $times = [];
        foreach ($this->seriesCollection->items as $series) {
            foreach ($series->points as $point) {
                if ($point->time !== null) {
                    $times[] = $point->time;
                }
            }
        }
        return $times;
    }

    private function buildTimeScale(Viewport $viewport): ?TimeScale
    {
        $times = $this->collectTimes();
        if ($times === []) {
            return null;
        }
        return TimeScale::fromValues(
            $times,
            $viewport->plotLeft(),
            $viewport->plotRight(),
            $this->timeAxisLocale,
            $this->timeAxisTz,
            $this->timeAxisFormat,
        );
    }

    /**
     * X-coordinate per column index, used for crosshair geometry. In time
     * mode the longest time-bearing series drives the layout; columns
     * without a time fall back to the index-based scale.
     *
     * @return list<float>
     */
    private function buildPrimaryXs(int $maxLen, Scale $xScale, ?TimeScale $timeScale): array
    {
        $primary = null;
        if ($timeScale instanceof TimeScale) {
            foreach ($this->seriesCollection->items as $series) {
                if ($series->isEmpty()) {
                    continue;
                }
                if ($primary === null || count($series) > count($primary)) {
                    $primary = $series;
                }
            }
        }
        $xs = [];
        for ($i = 0; $i < $maxLen; $i++) {
            $time = $primary !== null ? ($primary->points[$i]->time ?? null) : null;
            $xs[] = $time !== null && $timeScale instanceof TimeScale
                ? $timeScale->mapDate($time)
                : $xScale->map((float) $i);
        }
        return $xs;
    }

    /**
     * @return list<array{id: string, seriesIndex: int, name: string, color: string}>
     */
    private function buildLegendEntries(): array
    {
        $chartId = $this->chartId();
        $entries = [];
        foreach ($this->seriesCollection->items as $i => $series) {
            $name = $series->name !== '' ? $series->name : 'Series ' . ($i + 1);
            $entries[] = [
                'id' => "{$chartId}-s{$i}",
                'seriesIndex' => $i,
                'name' => $name,
                'color' => $this->resolveColor($series, $i),
            ];
        }
        return $entries;
    }

    private function renderSeries(
        Wrapper $wrapper,
        Series $series,
        int $index,
        Scale $xScale,
        Scale $yScale,
        Viewport $viewport,
        float $strokeWidth,
        ?TimeScale $timeScale,
    ): void {
        if ($series->isEmpty()) {
            return;
        }

        $color = $this->resolveColor($series, $index);
        $points = [];
        foreach ($series->values as $i => $v) {
            $time = $series->points[$i]->time ?? null;
            $x = $time !== null && $timeScale instanceof TimeScale
                ? $timeScale->mapDate($time)
                : $xScale->map((float) $i);
            $points[] = [$x, $yScale->map($v)];
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
     * @param list<float> $xs
     * @return list<Tag>
     */
    protected function buildCrosshairLines(array $xs, Viewport $viewport): array
    {
        $lines = [];
        foreach ($xs as $i => $x) {
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
     * @param list<float> $xs
     * @return list<Tag>
     */
    protected function buildCrosshairHits(array $xs, Viewport $viewport): array
    {
        $top = $viewport->plotTop();
        $height = $viewport->plotBottom() - $top;
        $left = $viewport->plotLeft();
        $right = $viewport->plotRight();
        $count = count($xs);

        $rects = [];
        foreach ($xs as $i => $x) {
            $colLeft = $i === 0 ? $left : ($xs[$i - 1] + $x) / 2;
            $colRight = $i === $count - 1 ? $right : ($x + $xs[$i + 1]) / 2;
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
    protected function buildAxisLines(Viewport $viewport, bool $secondary = false): array
    {
        $rightAxisColor = $secondary ? $this->resolveAxisColor(Axis::Right) : $this->theme->axisColor;
        $lines = [
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
        if ($secondary) {
            $lines[] = Tag::void('line', [
                'x1' => Tag::formatFloat($viewport->plotRight()),
                'x2' => Tag::formatFloat($viewport->plotRight()),
                'y1' => Tag::formatFloat($viewport->plotTop()),
                'y2' => Tag::formatFloat($viewport->plotBottom()),
                'stroke' => $rightAxisColor,
                'stroke-width' => '1',
                'vector-effect' => 'non-scaling-stroke',
            ]);
        }
        return $lines;
    }

    /**
     * Pick a tinting color for an axis. The secondary axis adopts the color
     * of the first series assigned to it so the link between axis and data
     * is visually obvious; if no series is assigned, we fall back to the
     * theme axis color.
     */
    private function resolveAxisColor(Axis $axis): string
    {
        foreach ($this->seriesCollection->items as $i => $series) {
            if ($series->axis === $axis && !$series->isEmpty()) {
                return $this->resolveColor($series, $i);
            }
        }
        return $this->theme->axisColor;
    }

    protected function addLabels(
        Wrapper $wrapper,
        Scale $xScale,
        Scale $yScale,
        ?Scale $rightYScale,
        ?TimeScale $timeScale,
        Viewport $viewport,
    ): void {
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

        if ($rightYScale instanceof Scale) {
            $rightColor = $this->resolveAxisColor(Axis::Right);
            foreach ($rightYScale->ticks($this->tickCount) as $tick) {
                $y = $rightYScale->map($tick);
                $wrapper->label(new Label(
                    text: $this->formatNumber($tick),
                    left: $viewport->plotRight(),
                    top: $y,
                    align: 'start',
                    verticalAlign: 'middle',
                    color: $rightColor,
                ));
            }
        }

        if ($timeScale instanceof TimeScale) {
            foreach ($timeScale->timeTicks($this->tickCount) as $tick) {
                $x = $timeScale->mapDate($tick);
                $wrapper->label(new Label(
                    text: $timeScale->formatTick($tick, $this->tickCount),
                    left: $x,
                    bottom: 0,
                    align: 'center',
                    verticalAlign: 'bottom',
                ));
            }
            return;
        }

        foreach ($this->seriesCollection->commonLabels() as $i => $label) {
            if ($label === null) {
                continue;
            }
            if ($label === '') {
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
        $this->applyAccessibility($wrapper);
        return $wrapper->render();
    }

    #[\Override]
    protected function defaultDescription(): string
    {
        if ($this->seriesCollection->isEmpty()) {
            return $this->defaultTitle() . ' (no data).';
        }
        $count = $this->seriesCollection->count();
        $points = $this->seriesCollection->maxLength();
        $min = $this->formatNumber($this->seriesCollection->valueMin());
        $max = $this->formatNumber($this->seriesCollection->valueMax());
        return sprintf(
            '%s with %d series of %d %s. Range: %s to %s.',
            $this->defaultTitle(),
            $count,
            $points,
            $points === 1 ? 'point' : 'points',
            $min,
            $max,
        );
    }

    #[\Override]
    protected function buildDataTable(): array
    {
        if ($this->seriesCollection->isEmpty()) {
            return ['columns' => [], 'rows' => []];
        }

        $columns = ['Label'];
        foreach ($this->seriesCollection->items as $i => $series) {
            $columns[] = $series->name !== '' ? $series->name : 'Series ' . ($i + 1);
        }

        $labels = $this->seriesCollection->commonLabels();
        $maxLen = $this->seriesCollection->maxLength();
        $rows = [];
        for ($i = 0; $i < $maxLen; $i++) {
            $rowLabel = $labels[$i] ?? null;
            if ($rowLabel === null || $rowLabel === '') {
                $rowLabel = (string) ($i + 1);
            }
            $row = [$rowLabel];
            foreach ($this->seriesCollection->items as $series) {
                $row[] = isset($series->values[$i])
                    ? $this->formatNumber($series->values[$i])
                    : '';
            }
            $rows[] = $row;
        }
        return ['columns' => $columns, 'rows' => $rows];
    }
}

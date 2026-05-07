<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Annotations\AnnotationContext;
use Noeka\Svgraph\Annotations\AnnotationLayer;
use Noeka\Svgraph\Data\Link;
use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Data\SeriesCollection;
use Noeka\Svgraph\Geometry\Scale;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Svg\Label;
use Noeka\Svgraph\Svg\Tag;
use Noeka\Svgraph\Svg\Tooltip;
use Noeka\Svgraph\Svg\Wrapper;

class BarChart extends AbstractChart
{
    private const string MODE_AUTO = 'auto';
    private const string MODE_GROUPED = 'grouped';
    private const string MODE_STACKED = 'stacked';

    protected SeriesCollection $seriesCollection;

    protected ?string $color = null;
    protected float $gap = 0.2;
    protected bool $horizontal = false;
    protected float $cornerRadius = 0.0;
    protected bool $showAxes = false;
    protected bool $showGrid = false;
    protected bool $showLegend = false;
    protected int $tickCount = 5;
    protected bool $useColorPerBar = false;

    protected string $mode = self::MODE_AUTO;

    public function __construct()
    {
        parent::__construct();
        $this->variantClass = 'bar';
        $this->aspectRatio = 2.0;
        $this->seriesCollection = new SeriesCollection();
    }

    /** @param iterable<mixed> $data */
    public function data(iterable $data): static
    {
        $this->seriesCollection = new SeriesCollection([Series::from($data)]);
        return $this;
    }

    /**
     * Append a series. The first call to `data()` (or `addSeries()` on an
     * empty chart) seeds series 0; subsequent `addSeries()` calls append.
     * Multi-series charts default to grouped layout — call `stacked()` to
     * stack bars instead.
     */
    public function addSeries(Series $series): static
    {
        $this->seriesCollection = $this->seriesCollection->with($series);
        return $this;
    }

    public function color(string $color): static
    {
        $this->color = $color;
        return $this;
    }

    /**
     * Use the theme palette to color each bar individually.
     * Only applies to single-series charts; multi-series charts already
     * pick a per-series colour from the palette.
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

    /**
     * Render a CSS-only toggle legend below the chart. Each entry is a
     * `<label>` bound to a hidden checkbox; clicking an entry hides its
     * series and dims the entry. State is page-local (no JS = no
     * persistence) and the value axis does not rescale to the remaining
     * series.
     */
    public function legend(bool $on = true): static
    {
        $this->showLegend = $on;
        return $this;
    }

    /**
     * Place bars side-by-side per X tick across all series. Default for
     * multi-series charts.
     */
    public function grouped(bool $on = true): static
    {
        $this->mode = $on ? self::MODE_GROUPED : self::MODE_AUTO;
        return $this;
    }

    /**
     * Stack bars atop each other per X tick. Y axis grows to the cumulative
     * max sum, not the largest single value.
     */
    public function stacked(bool $on = true): static
    {
        $this->mode = $on ? self::MODE_STACKED : self::MODE_AUTO;
        return $this;
    }

    public function render(): string
    {
        if ($this->seriesCollection->isEmpty()) {
            $viewport = new Viewport();
            $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
            $wrapper->setUserClass($this->cssClass);
            $this->applyAccessibility($wrapper);
            return $wrapper->render();
        }

        return $this->horizontal ? $this->renderHorizontal() : $this->renderVertical();
    }

    /**
     * `auto` is grouped — single series naturally collapses into one bar
     * per slot since the per-slot bar count equals the series count.
     */
    private function effectiveMode(): string
    {
        return $this->mode === self::MODE_AUTO ? self::MODE_GROUPED : $this->mode;
    }

    /**
     * For animation staggering: index in the rendering order so visually
     * adjacent bars wave in together.
     */
    private function staggerDelay(int $position): string
    {
        return (string) round($position * 0.08, 3);
    }

    protected function renderVertical(): string
    {
        $hasLabels = $this->seriesCollection->hasLabels();
        $padTop = $this->showAxes || $this->showGrid ? 4.0 : 0.0;
        $padRight = $this->showAxes || $this->showGrid ? 2.0 : 0.0;
        $padBottom = $hasLabels ? ($this->showAxes ? 14.0 : 8.0) : 0.0;
        $padLeft = $this->showAxes || $this->showGrid ? 12.0 : 0.0;

        $viewport = new Viewport(100, 100, $padTop, $padRight, $padBottom, $padLeft);
        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);

        $maxLen = $this->seriesCollection->maxLength();
        if ($maxLen === 0) {
            return $wrapper->render();
        }

        $mode = $this->effectiveMode();
        if ($mode === self::MODE_STACKED) {
            $domainMin = min(0.0, $this->seriesCollection->stackedMin());
            $domainMax = $this->seriesCollection->stackedMax();
        } else {
            $domainMin = min(0.0, $this->seriesCollection->valueMin());
            $domainMax = $this->seriesCollection->valueMax();
        }
        if ($domainMin === $domainMax) {
            $domainMax += 1.0;
        }

        $yScale = Scale::linear($domainMin, $domainMax, $viewport->plotTop(), $viewport->plotBottom(), invert: true);
        $slotWidth = $viewport->plotWidth() / $maxLen;
        $baseY = $yScale->map(0.0);

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

        $wrapper->markHasSeriesElements();
        if ($this->animated) {
            $wrapper->enableAnimation();
        }

        $annotationContext = new AnnotationContext(
            viewport: $viewport,
            theme: $this->theme,
            xScale: Scale::linear(-0.5, $maxLen - 0.5, $viewport->plotLeft(), $viewport->plotRight()),
            yScale: $yScale,
        );
        $this->renderAnnotationLayer($wrapper, $annotationContext, AnnotationLayer::BehindData);

        if ($mode === self::MODE_STACKED) {
            $this->renderVerticalStacked($wrapper, $viewport, $yScale, $slotWidth);
        } else {
            $this->renderVerticalGrouped($wrapper, $viewport, $yScale, $slotWidth, $baseY);
        }

        $this->renderAnnotationLayer($wrapper, $annotationContext, AnnotationLayer::OverData);

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
            foreach ($this->seriesCollection->commonLabels() as $i => $label) {
                if ($label === null) {
                    continue;
                }
                if ($label === '') {
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

        $this->emitAnnotationLabels($wrapper, $annotationContext);

        if ($this->showLegend) {
            $wrapper->setLegend($this->buildLegendEntries());
        }

        $this->applyAccessibility($wrapper);

        return $wrapper->render();
    }

    private function renderVerticalGrouped(
        Wrapper $wrapper,
        Viewport $viewport,
        Scale $yScale,
        float $slotWidth,
        float $baseY,
    ): void {
        $chartId = $this->chartId();
        $seriesCount = $this->seriesCollection->count();
        $groupWidth = $slotWidth * (1.0 - $this->gap);
        $groupOffset = ($slotWidth - $groupWidth) / 2.0;
        $barWidth = $groupWidth / $seriesCount;
        $rainbowSingle = $seriesCount === 1 && $this->useColorPerBar;

        $position = 0;
        foreach ($this->seriesCollection->items as $j => $series) {
            $seriesColor = $this->resolveSeriesColor($series, $j);
            foreach ($series->points as $i => $point) {
                $value = $point->value;
                $slotX = $viewport->plotLeft() + $i * $slotWidth + $groupOffset;
                $x = $slotX + $j * $barWidth;
                $valueY = $yScale->map($value);
                $top = min($baseY, $valueY);
                $height = abs($valueY - $baseY);
                [$color, $classIndex] = $rainbowSingle
                    ? [$this->theme->colorAt($i), $i]
                    : [$seriesColor, $j];
                $id = "{$chartId}-s{$j}-pt-{$i}";
                $tipText = $this->tooltip($this->labelFor($series, $point->label), $value);
                $tfo = $value >= 0.0 ? 'center bottom' : 'center top';
                $this->emitVerticalBar(
                    $wrapper,
                    $viewport,
                    $id,
                    $classIndex,
                    $x,
                    $top,
                    $barWidth,
                    $height,
                    $color,
                    $tipText,
                    $point->link,
                    $tfo,
                    $position++,
                );
            }
        }
    }

    private function renderVerticalStacked(
        Wrapper $wrapper,
        Viewport $viewport,
        Scale $yScale,
        float $slotWidth,
    ): void {
        $chartId = $this->chartId();
        $barWidth = $slotWidth * (1.0 - $this->gap);
        $barOffset = ($slotWidth - $barWidth) / 2.0;
        $maxLen = $this->seriesCollection->maxLength();

        // Track running positive/negative tops per slot so each segment
        // sits flush against the previous one.
        $posCursor = array_fill(0, $maxLen, 0.0);
        $negCursor = array_fill(0, $maxLen, 0.0);

        $position = 0;
        foreach ($this->seriesCollection->items as $j => $series) {
            $color = $this->resolveSeriesColor($series, $j);
            foreach ($series->points as $i => $point) {
                $value = $point->value;
                if ($value === 0.0) {
                    continue;
                }
                $x = $viewport->plotLeft() + $i * $slotWidth + $barOffset;
                if ($value > 0.0) {
                    $bottomVal = $posCursor[$i];
                    $topVal = $bottomVal + $value;
                    $posCursor[$i] = $topVal;
                } else {
                    $topVal = $negCursor[$i];
                    $bottomVal = $topVal + $value;
                    $negCursor[$i] = $bottomVal;
                }
                $topY = $yScale->map(max($topVal, $bottomVal));
                $bottomY = $yScale->map(min($topVal, $bottomVal));
                $height = abs($bottomY - $topY);
                $id = "{$chartId}-s{$j}-pt-{$i}";
                $tipText = $this->tooltip($this->labelFor($series, $point->label), $value);
                $tfo = $value >= 0.0 ? 'center bottom' : 'center top';
                $this->emitVerticalBar(
                    $wrapper,
                    $viewport,
                    $id,
                    $j,
                    $x,
                    $topY,
                    $barWidth,
                    $height,
                    $color,
                    $tipText,
                    $point->link,
                    $tfo,
                    $position++,
                );
            }
        }
    }

    private function emitVerticalBar(
        Wrapper $wrapper,
        Viewport $viewport,
        string $id,
        int $seriesIndex,
        float $x,
        float $top,
        float $width,
        float $height,
        string $color,
        string $tipText,
        ?Link $link,
        string $tfo,
        int $stagger,
    ): void {
        $attrs = [
            'class' => "series-{$seriesIndex}",
            'x' => Tag::formatFloat($x),
            'y' => Tag::formatFloat($top),
            'width' => Tag::formatFloat($width),
            'height' => Tag::formatFloat($height),
            'fill' => $color,
        ];
        if ($this->cornerRadius > 0.0) {
            $attrs['rx'] = Tag::formatFloat($this->cornerRadius);
            $attrs['ry'] = Tag::formatFloat($this->cornerRadius);
        }
        if ($this->animated) {
            $delay = $this->staggerDelay($stagger);
            $attrs['style'] = "--svgraph-bar-tfo:{$tfo};--svgraph-bar-delay:{$delay}s;";
        }
        $rect = Tag::make('rect', $attrs)->append(Tag::make('title')->append($tipText));
        $wrapper->add($this->buildLink($link, $id, $rect));
        $wrapper->tooltip(new Tooltip(
            id: $id,
            text: Tag::escapeText($tipText),
            leftPct: ($x + $width / 2) / $viewport->width * 100,
            topPct: $top / $viewport->height * 100,
        ));
    }

    protected function renderHorizontal(): string
    {
        $hasLabels = $this->seriesCollection->hasLabels();
        $padTop = 2.0;
        $padRight = $this->showAxes ? 6.0 : 2.0;
        $padBottom = $this->showAxes ? 8.0 : 2.0;
        $padLeft = $hasLabels ? 22.0 : 2.0;

        $viewport = new Viewport(100, 100, $padTop, $padRight, $padBottom, $padLeft);
        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);

        $maxLen = $this->seriesCollection->maxLength();
        if ($maxLen === 0) {
            return $wrapper->render();
        }

        $mode = $this->effectiveMode();
        if ($mode === self::MODE_STACKED) {
            $domainMin = min(0.0, $this->seriesCollection->stackedMin());
            $domainMax = $this->seriesCollection->stackedMax();
        } else {
            $domainMin = min(0.0, $this->seriesCollection->valueMin());
            $domainMax = $this->seriesCollection->valueMax();
        }
        if ($domainMin === $domainMax) {
            $domainMax += 1.0;
        }

        $xScale = Scale::linear($domainMin, $domainMax, $viewport->plotLeft(), $viewport->plotRight());
        $slotHeight = $viewport->plotHeight() / $maxLen;
        $baseX = $xScale->map(0.0);

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

        $wrapper->markHasSeriesElements();
        if ($this->animated) {
            $wrapper->enableAnimation();
            $wrapper->setSecondaryVariant('bar-h');
        }

        $annotationContext = new AnnotationContext(
            viewport: $viewport,
            theme: $this->theme,
            xScale: $xScale,
            yScale: Scale::linear(-0.5, $maxLen - 0.5, $viewport->plotTop(), $viewport->plotBottom(), invert: true),
        );
        $this->renderAnnotationLayer($wrapper, $annotationContext, AnnotationLayer::BehindData);

        if ($mode === self::MODE_STACKED) {
            $this->renderHorizontalStacked($wrapper, $viewport, $xScale, $slotHeight);
        } else {
            $this->renderHorizontalGrouped($wrapper, $viewport, $xScale, $slotHeight, $baseX);
        }

        $this->renderAnnotationLayer($wrapper, $annotationContext, AnnotationLayer::OverData);

        if ($hasLabels) {
            foreach ($this->seriesCollection->commonLabels() as $i => $label) {
                if ($label === null) {
                    continue;
                }
                if ($label === '') {
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

        $this->emitAnnotationLabels($wrapper, $annotationContext);

        if ($this->showLegend) {
            $wrapper->setLegend($this->buildLegendEntries());
        }

        $this->applyAccessibility($wrapper);

        return $wrapper->render();
    }

    private function renderHorizontalGrouped(
        Wrapper $wrapper,
        Viewport $viewport,
        Scale $xScale,
        float $slotHeight,
        float $baseX,
    ): void {
        $chartId = $this->chartId();
        $seriesCount = $this->seriesCollection->count();
        $groupHeight = $slotHeight * (1.0 - $this->gap);
        $groupOffset = ($slotHeight - $groupHeight) / 2.0;
        $barHeight = $groupHeight / $seriesCount;
        $rainbowSingle = $seriesCount === 1 && $this->useColorPerBar;

        $position = 0;
        foreach ($this->seriesCollection->items as $j => $series) {
            $seriesColor = $this->resolveSeriesColor($series, $j);
            foreach ($series->points as $i => $point) {
                $value = $point->value;
                $slotY = $viewport->plotTop() + $i * $slotHeight + $groupOffset;
                $y = $slotY + $j * $barHeight;
                $valueX = $xScale->map($value);
                $left = min($baseX, $valueX);
                $width = abs($valueX - $baseX);
                [$color, $classIndex] = $rainbowSingle
                    ? [$this->theme->colorAt($i), $i]
                    : [$seriesColor, $j];
                $id = "{$chartId}-s{$j}-pt-{$i}";
                $tipText = $this->tooltip($this->labelFor($series, $point->label), $value);
                $tfo = $value >= 0.0 ? 'left center' : 'right center';
                $this->emitHorizontalBar(
                    $wrapper,
                    $viewport,
                    $id,
                    $classIndex,
                    $left,
                    $y,
                    $width,
                    $barHeight,
                    $color,
                    $tipText,
                    $point->link,
                    $tfo,
                    $position++,
                );
            }
        }
    }

    private function renderHorizontalStacked(
        Wrapper $wrapper,
        Viewport $viewport,
        Scale $xScale,
        float $slotHeight,
    ): void {
        $chartId = $this->chartId();
        $barHeight = $slotHeight * (1.0 - $this->gap);
        $barOffset = ($slotHeight - $barHeight) / 2.0;
        $maxLen = $this->seriesCollection->maxLength();

        $posCursor = array_fill(0, $maxLen, 0.0);
        $negCursor = array_fill(0, $maxLen, 0.0);

        $position = 0;
        foreach ($this->seriesCollection->items as $j => $series) {
            $color = $this->resolveSeriesColor($series, $j);
            foreach ($series->points as $i => $point) {
                $value = $point->value;
                if ($value === 0.0) {
                    continue;
                }
                $y = $viewport->plotTop() + $i * $slotHeight + $barOffset;
                if ($value > 0.0) {
                    $startVal = $posCursor[$i];
                    $endVal = $startVal + $value;
                    $posCursor[$i] = $endVal;
                } else {
                    $endVal = $negCursor[$i];
                    $startVal = $endVal + $value;
                    $negCursor[$i] = $startVal;
                }
                $leftX = $xScale->map(min($startVal, $endVal));
                $rightX = $xScale->map(max($startVal, $endVal));
                $width = abs($rightX - $leftX);
                $id = "{$chartId}-s{$j}-pt-{$i}";
                $tipText = $this->tooltip($this->labelFor($series, $point->label), $value);
                $tfo = $value >= 0.0 ? 'left center' : 'right center';
                $this->emitHorizontalBar(
                    $wrapper,
                    $viewport,
                    $id,
                    $j,
                    $leftX,
                    $y,
                    $width,
                    $barHeight,
                    $color,
                    $tipText,
                    $point->link,
                    $tfo,
                    $position++,
                );
            }
        }
    }

    private function emitHorizontalBar(
        Wrapper $wrapper,
        Viewport $viewport,
        string $id,
        int $seriesIndex,
        float $left,
        float $y,
        float $width,
        float $height,
        string $color,
        string $tipText,
        ?Link $link,
        string $tfo,
        int $stagger,
    ): void {
        $attrs = [
            'class' => "series-{$seriesIndex}",
            'x' => Tag::formatFloat($left),
            'y' => Tag::formatFloat($y),
            'width' => Tag::formatFloat($width),
            'height' => Tag::formatFloat($height),
            'fill' => $color,
        ];
        if ($this->cornerRadius > 0.0) {
            $attrs['rx'] = Tag::formatFloat($this->cornerRadius);
            $attrs['ry'] = Tag::formatFloat($this->cornerRadius);
        }
        if ($this->animated) {
            $delay = $this->staggerDelay($stagger);
            $attrs['style'] = "--svgraph-bar-tfo:{$tfo};--svgraph-bar-delay:{$delay}s;";
        }
        $rect = Tag::make('rect', $attrs)->append(Tag::make('title')->append($tipText));
        $wrapper->add($this->buildLink($link, $id, $rect));
        $wrapper->tooltip(new Tooltip(
            id: $id,
            text: Tag::escapeText($tipText),
            leftPct: ($left + $width) / $viewport->width * 100,
            topPct: ($y + $height / 2) / $viewport->height * 100,
        ));
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
                'color' => $this->resolveSeriesColor($series, $i),
            ];
        }
        return $entries;
    }

    /**
     * Per-series color: explicit Series->color wins, then chart-level
     * `->color()` for series 0 (so single-series `Chart::bar()->color()`
     * stays ergonomic), then the theme palette.
     */
    private function resolveSeriesColor(Series $series, int $index): string
    {
        if ($series->color !== null) {
            return $series->color;
        }
        if ($index === 0 && $this->color !== null) {
            return $this->color;
        }
        return $this->theme->colorAt($index);
    }

    private function labelFor(Series $series, ?string $pointLabel): ?string
    {
        if ($series->name !== '') {
            return $pointLabel === null || $pointLabel === ''
                ? $series->name
                : "{$series->name} — {$pointLabel}";
        }
        return $pointLabel;
    }

    #[\Override]
    protected function defaultTitle(): string
    {
        return $this->horizontal ? 'Horizontal bar chart' : 'Bar chart';
    }

    #[\Override]
    protected function defaultDescription(): string
    {
        if ($this->seriesCollection->isEmpty()) {
            return $this->defaultTitle() . ' (no data).';
        }
        $count = $this->seriesCollection->count();
        $cats = $this->seriesCollection->maxLength();
        $min = $this->formatNumber($this->seriesCollection->valueMin());
        $max = $this->formatNumber($this->seriesCollection->valueMax());
        return sprintf(
            '%s with %d series across %d %s. Range: %s to %s.',
            $this->defaultTitle(),
            $count,
            $cats,
            $cats === 1 ? 'category' : 'categories',
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

        $columns = ['Category'];
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

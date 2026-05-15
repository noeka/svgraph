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
            return $this->renderEmpty();
        }

        return $this->horizontal ? $this->renderHorizontal() : $this->renderVertical();
    }

    private function renderEmpty(): string
    {
        $wrapper = $this->makeWrapper(new Viewport());
        $this->applyAccessibility($wrapper);

        return $wrapper->render();
    }

    protected function renderVertical(): string
    {
        $hasLabels = $this->seriesCollection->hasLabels();
        $viewport = $this->verticalViewport($hasLabels);
        $wrapper = $this->makeWrapper($viewport);

        $maxLen = $this->seriesCollection->maxLength();

        if ($maxLen === 0) {
            return $wrapper->render();
        }

        $mode = $this->effectiveMode();
        [$domainMin, $domainMax] = $this->resolveDomain($mode);

        $yScale = Scale::linear($domainMin, $domainMax, $viewport->plotTop(), $viewport->plotBottom(), invert: true);
        $xScale = Scale::linear(-0.5, $maxLen - 0.5, $viewport->plotLeft(), $viewport->plotRight());

        $slotWidth = $viewport->plotWidth() / $maxLen;

        $baseY = $yScale->map(0.0);

        $this->emitHorizontalGridLines($wrapper, $viewport, $yScale);
        $this->markInteractivity($wrapper);

        $annotationContext = new AnnotationContext(
            viewport: $viewport,
            theme: $this->theme,
            xScale: $xScale,
            yScale: $yScale,
        );

        $this->renderAnnotationLayer($wrapper, $annotationContext, AnnotationLayer::BehindData);

        match ($mode) {
            self::MODE_STACKED => $this->renderVerticalStacked($wrapper, $viewport, $yScale, $slotWidth),
            self::MODE_GROUPED => $this->renderVerticalGrouped($wrapper, $viewport, $yScale, $slotWidth, $baseY),
            default => throw new \LogicException("Unexpected mode: {$mode}"),
        };

        $this->renderAnnotationLayer($wrapper, $annotationContext, AnnotationLayer::OverData);

        $this->emitVerticalAxis($wrapper, $viewport, $yScale, $baseY);
        $this->emitBottomCategoryLabels($wrapper, $viewport, $slotWidth);
        $this->emitAnnotationLabels($wrapper, $annotationContext);
        $this->applyLegend($wrapper);
        $this->applyAccessibility($wrapper);

        return $wrapper->render();
    }

    protected function renderHorizontal(): string
    {
        $hasLabels = $this->seriesCollection->hasLabels();
        $viewport = $this->horizontalViewport($hasLabels);
        $wrapper = $this->makeWrapper($viewport);

        $maxLen = $this->seriesCollection->maxLength();

        if ($maxLen === 0) {
            return $wrapper->render();
        }

        $mode = $this->effectiveMode();
        [$domainMin, $domainMax] = $this->resolveDomain($mode);

        $xScale = Scale::linear($domainMin, $domainMax, $viewport->plotLeft(), $viewport->plotRight());
        $yScale = Scale::linear(-0.5, $maxLen - 0.5, $viewport->plotTop(), $viewport->plotBottom(), invert: true);
        $slotHeight = $viewport->plotHeight() / $maxLen;

        $this->emitVerticalGridLines($wrapper, $viewport, $xScale);
        $this->markInteractivity($wrapper, secondaryVariant: 'bar-h');

        $annotationContext = new AnnotationContext(
            viewport: $viewport,
            theme: $this->theme,
            xScale: $xScale,
            yScale: $yScale,
        );

        $this->renderAnnotationLayer($wrapper, $annotationContext, AnnotationLayer::BehindData);

        match ($mode) {
            self::MODE_STACKED => $this->renderHorizontalStacked($wrapper, $viewport, $xScale, $slotHeight),
            self::MODE_GROUPED => $this->renderHorizontalGrouped($wrapper, $viewport, $xScale, $slotHeight),
            default => throw new \LogicException("Unexpected mode: {$mode}"),
        };

        $this->renderAnnotationLayer($wrapper, $annotationContext, AnnotationLayer::OverData);

        $this->emitLeftCategoryLabels($wrapper, $viewport, $slotHeight);
        $this->emitHorizontalAxisLabels($wrapper, $xScale);
        $this->emitAnnotationLabels($wrapper, $annotationContext);
        $this->applyLegend($wrapper);
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
        $seriesCount = $this->seriesCollection->count();
        $rainbowSingle = $seriesCount === 1 && $this->useColorPerBar;

        $groupWidth = $slotWidth * (1.0 - $this->gap);
        $groupOffset = ($slotWidth - $groupWidth) / 2.0;
        $barWidth = $groupWidth / $seriesCount;

        $position = 0;

        foreach ($this->seriesCollection->items as $j => $series) {
            $seriesColor = $this->resolveSeriesColor($series, $j);

            foreach ($series->points as $i => $point) {
                $value = $point->value;

                $slotX = $viewport->plotLeft() + $i * $slotWidth + $groupOffset;
                $x = $slotX + $j * $barWidth;
                $valueY = $yScale->map($value);

                [$color, $classIndex] = $rainbowSingle
                    ? [$this->theme->colorAt($i), $i]
                    : [$seriesColor, $j];

                $this->emitBar(
                    wrapper: $wrapper,
                    viewport: $viewport,
                    id: $this->barId($j, $i),
                    seriesIndex: $classIndex,
                    x: $x,
                    y: min($baseY, $valueY),
                    width: $barWidth,
                    height: abs($valueY - $baseY),
                    color: $color,
                    tipText: $this->tooltip($this->labelFor($series, $point->label), $value),
                    link: $point->link,
                    tfo: $this->verticalTfo($value),
                    stagger: $position++,
                    anchorX: $x + $barWidth / 2,
                    anchorY: min($baseY, $valueY),
                    roundedSide: $this->verticalRoundedSide($value),
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
        $barWidth = $slotWidth * (1.0 - $this->gap);
        $barOffset = ($slotWidth - $barWidth) / 2.0;

        // Running positive/negative tops per slot so each segment sits
        // flush against the previous one.
        $maxLen = $this->seriesCollection->maxLength();
        $posCursor = array_fill(0, $maxLen, 0.0);
        $negCursor = array_fill(0, $maxLen, 0.0);

        [$lastPositive, $lastNegative] = $this->stackedOutermostIndices();

        $position = 0;

        foreach ($this->seriesCollection->items as $j => $series) {
            $color = $this->resolveSeriesColor($series, $j);

            foreach ($series->points as $i => $point) {
                $value = $point->value;

                if ($value === 0.0) {
                    continue;
                }

                [$startVal, $endVal] = $this->advanceStack($posCursor, $negCursor, $i, $value);

                $startY = $yScale->map($startVal);
                $endY = $yScale->map($endVal);
                $top = min($startY, $endY);

                $x = $viewport->plotLeft() + $i * $slotWidth + $barOffset;

                $this->emitBar(
                    wrapper: $wrapper,
                    viewport: $viewport,
                    id: $this->barId($j, $i),
                    seriesIndex: $j,
                    x: $x,
                    y: $top,
                    width: $barWidth,
                    height: abs($endY - $startY),
                    color: $color,
                    tipText: $this->tooltip($this->labelFor($series, $point->label), $value),
                    link: $point->link,
                    tfo: $this->verticalTfo($value),
                    stagger: $position++,
                    anchorX: $x + $barWidth / 2,
                    anchorY: $top,
                    roundedSide: $this->stackedVerticalRoundedSide($value, $lastPositive, $lastNegative, $i, $j),
                );
            }
        }
    }

    private function renderHorizontalGrouped(
        Wrapper $wrapper,
        Viewport $viewport,
        Scale $xScale,
        float $slotHeight,
    ): void {
        $seriesCount = $this->seriesCollection->count();
        $rainbowSingle = $seriesCount === 1 && $this->useColorPerBar;

        $groupHeight = $slotHeight * (1.0 - $this->gap);
        $groupOffset = ($slotHeight - $groupHeight) / 2.0;
        $barHeight = $groupHeight / $seriesCount;

        $baseX = $xScale->map(0.0);

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

                $this->emitBar(
                    wrapper: $wrapper,
                    viewport: $viewport,
                    id: $this->barId($j, $i),
                    seriesIndex: $classIndex,
                    x: $left,
                    y: $y,
                    width: $width,
                    height: $barHeight,
                    color: $color,
                    tipText: $this->tooltip($this->labelFor($series, $point->label), $value),
                    link: $point->link,
                    tfo: $this->horizontalTfo($value),
                    stagger: $position++,
                    anchorX: $left + $width,
                    anchorY: $y + $barHeight / 2,
                    roundedSide: $this->horizontalRoundedSide($value),
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
        $barHeight = $slotHeight * (1.0 - $this->gap);
        $barOffset = ($slotHeight - $barHeight) / 2.0;

        $maxLen = $this->seriesCollection->maxLength();
        $posCursor = array_fill(0, $maxLen, 0.0);
        $negCursor = array_fill(0, $maxLen, 0.0);

        [$lastPositive, $lastNegative] = $this->stackedOutermostIndices();

        $position = 0;

        foreach ($this->seriesCollection->items as $j => $series) {
            $color = $this->resolveSeriesColor($series, $j);

            foreach ($series->points as $i => $point) {
                $value = $point->value;

                if ($value === 0.0) {
                    continue;
                }

                [$startVal, $endVal] = $this->advanceStack($posCursor, $negCursor, $i, $value);

                $startX = $xScale->map($startVal);
                $endX = $xScale->map($endVal);
                $left = min($startX, $endX);
                $width = abs($endX - $startX);

                $y = $viewport->plotTop() + $i * $slotHeight + $barOffset;

                $this->emitBar(
                    wrapper: $wrapper,
                    viewport: $viewport,
                    id: $this->barId($j, $i),
                    seriesIndex: $j,
                    x: $left,
                    y: $y,
                    width: $width,
                    height: $barHeight,
                    color: $color,
                    tipText: $this->tooltip($this->labelFor($series, $point->label), $value),
                    link: $point->link,
                    tfo: $this->horizontalTfo($value),
                    stagger: $position++,
                    anchorX: $left + $width,
                    anchorY: $y + $barHeight / 2,
                    roundedSide: $this->stackedHorizontalRoundedSide($value, $lastPositive, $lastNegative, $i, $j),
                );
            }
        }
    }

    private function emitBar(
        Wrapper $wrapper,
        Viewport $viewport,
        string $id,
        int $seriesIndex,
        float $x,
        float $y,
        float $width,
        float $height,
        string $color,
        string $tipText,
        ?Link $link,
        string $tfo,
        int $stagger,
        float $anchorX,
        float $anchorY,
        string $roundedSide = 'none',
    ): void {
        $element = $this->buildBarElement($seriesIndex, $x, $y, $width, $height, $color, $roundedSide);
        $element->append(Tag::make('title')->append($tipText));

        if ($this->animated) {
            $delay = $this->staggerDelay($stagger);
            $element->attr('style', "--svgraph-bar-tfo:{$tfo};--svgraph-bar-delay:{$delay}s;");
        }

        $wrapper->add($this->buildLink($link, $id, $element));
        $wrapper->tooltip(new Tooltip(
            id: $id,
            text: Tag::escapeText($tipText),
            leftPct: $anchorX / $viewport->width * 100,
            topPct: $anchorY / $viewport->height * 100,
        ));
    }

    /**
     * Pick `<path>` with selectively rounded corners when there is a side
     * to round and a non-zero radius that fits; otherwise emit a flat
     * `<rect>` so the no-rounding path remains stable.
     */
    private function buildBarElement(
        int $seriesIndex,
        float $x,
        float $y,
        float $width,
        float $height,
        string $color,
        string $roundedSide,
    ): Tag {
        if ($this->cornerRadius > 0.0 && $roundedSide !== 'none' && $width > 0.0 && $height > 0.0) {
            [$rx, $ry] = $this->resolveRadii($width, $height, $roundedSide);

            if ($rx > 0.0 && $ry > 0.0) {
                return Tag::make('path', [
                    'class' => "series-{$seriesIndex}",
                    'd' => $this->barPath($x, $y, $width, $height, $rx, $ry, $roundedSide),
                    'fill' => $color,
                ]);
            }
        }

        return Tag::make('rect', [
            'class' => "series-{$seriesIndex}",
            'x' => Tag::formatFloat($x),
            'y' => Tag::formatFloat($y),
            'width' => Tag::formatFloat($width),
            'height' => Tag::formatFloat($height),
            'fill' => $color,
        ]);
    }

    /**
     * Aspect-correct the radius so the rendered arc is circular after the
     * wrapper's `preserveAspectRatio="none"` stretch: a horizontal viewBox
     * unit ends up `aspectRatio` times longer in pixels than a vertical
     * one, so `ry` is scaled to compensate.
     *
     * `rx` is clamped against the rectangle's bounds in both directions
     * (different limits for top/bottom vs left/right rounded sides) so
     * the path never overshoots the bar.
     *
     * @return array{float, float}
     */
    private function resolveRadii(float $width, float $height, string $roundedSide): array
    {
        $aspect = max($this->aspectRatio, 0.01);

        // Two arcs run along the rounded side; the perpendicular axis
        // hosts the straight runs and only one arc-radius.
        [$maxRx, $maxRy] = match ($roundedSide) {
            'top', 'bottom' => [$width / 2, $height],
            'left', 'right' => [$width, $height / 2],
            default => [0.0, 0.0],
        };

        $rx = min($this->cornerRadius, $maxRx, $maxRy / $aspect);
        $rx = max($rx, 0.0);

        return [$rx, $rx * $aspect];
    }

    /**
     * Build the path "d" attribute for a rectangle with rounded corners
     * along a single side. Winding is clockwise so the fill-rule produces
     * a filled shape.
     */
    private function barPath(
        float $x,
        float $y,
        float $width,
        float $height,
        float $rx,
        float $ry,
        string $roundedSide,
    ): string {
        $f = static fn(float $v): string => Tag::formatFloat($v);

        $x2 = $x + $width;
        $y2 = $y + $height;

        return match ($roundedSide) {
            'top' => 'M' . $f($x) . ',' . $f($y2)
                . ' L' . $f($x) . ',' . $f($y + $ry)
                . ' A' . $f($rx) . ',' . $f($ry) . ' 0 0 1 ' . $f($x + $rx) . ',' . $f($y)
                . ' L' . $f($x2 - $rx) . ',' . $f($y)
                . ' A' . $f($rx) . ',' . $f($ry) . ' 0 0 1 ' . $f($x2) . ',' . $f($y + $ry)
                . ' L' . $f($x2) . ',' . $f($y2)
                . ' Z',
            'bottom' => 'M' . $f($x) . ',' . $f($y)
                . ' L' . $f($x2) . ',' . $f($y)
                . ' L' . $f($x2) . ',' . $f($y2 - $ry)
                . ' A' . $f($rx) . ',' . $f($ry) . ' 0 0 1 ' . $f($x2 - $rx) . ',' . $f($y2)
                . ' L' . $f($x + $rx) . ',' . $f($y2)
                . ' A' . $f($rx) . ',' . $f($ry) . ' 0 0 1 ' . $f($x) . ',' . $f($y2 - $ry)
                . ' Z',
            'right' => 'M' . $f($x) . ',' . $f($y)
                . ' L' . $f($x2 - $rx) . ',' . $f($y)
                . ' A' . $f($rx) . ',' . $f($ry) . ' 0 0 1 ' . $f($x2) . ',' . $f($y + $ry)
                . ' L' . $f($x2) . ',' . $f($y2 - $ry)
                . ' A' . $f($rx) . ',' . $f($ry) . ' 0 0 1 ' . $f($x2 - $rx) . ',' . $f($y2)
                . ' L' . $f($x) . ',' . $f($y2)
                . ' Z',
            'left' => 'M' . $f($x2) . ',' . $f($y)
                . ' L' . $f($x2) . ',' . $f($y2)
                . ' L' . $f($x + $rx) . ',' . $f($y2)
                . ' A' . $f($rx) . ',' . $f($ry) . ' 0 0 1 ' . $f($x) . ',' . $f($y2 - $ry)
                . ' L' . $f($x) . ',' . $f($y + $ry)
                . ' A' . $f($rx) . ',' . $f($ry) . ' 0 0 1 ' . $f($x + $rx) . ',' . $f($y)
                . ' Z',
            default => throw new \LogicException("Unknown rounded side: {$roundedSide}"),
        };
    }

    private function verticalRoundedSide(float $value): string
    {
        if ($this->cornerRadius <= 0.0) {
            return 'none';
        }

        return match (true) {
            $value > 0.0 => 'top',
            $value < 0.0 => 'bottom',
            default => 'none',
        };
    }

    private function horizontalRoundedSide(float $value): string
    {
        if ($this->cornerRadius <= 0.0) {
            return 'none';
        }

        return match (true) {
            $value > 0.0 => 'right',
            $value < 0.0 => 'left',
            default => 'none',
        };
    }

    /**
     * @param array<int, int> $lastPositive
     * @param array<int, int> $lastNegative
     */
    private function stackedVerticalRoundedSide(
        float $value,
        array $lastPositive,
        array $lastNegative,
        int $slot,
        int $seriesIndex,
    ): string {
        if ($this->cornerRadius <= 0.0) {
            return 'none';
        }

        if ($value > 0.0 && ($lastPositive[$slot] ?? null) === $seriesIndex) {
            return 'top';
        }

        if ($value < 0.0 && ($lastNegative[$slot] ?? null) === $seriesIndex) {
            return 'bottom';
        }

        return 'none';
    }

    /**
     * @param array<int, int> $lastPositive
     * @param array<int, int> $lastNegative
     */
    private function stackedHorizontalRoundedSide(
        float $value,
        array $lastPositive,
        array $lastNegative,
        int $slot,
        int $seriesIndex,
    ): string {
        if ($this->cornerRadius <= 0.0) {
            return 'none';
        }

        if ($value > 0.0 && ($lastPositive[$slot] ?? null) === $seriesIndex) {
            return 'right';
        }

        if ($value < 0.0 && ($lastNegative[$slot] ?? null) === $seriesIndex) {
            return 'left';
        }

        return 'none';
    }

    /**
     * For each slot, the highest series index that contributed a positive
     * or negative value — that is the segment whose outer face sits at
     * the open end of the stack and therefore gets the rounded side.
     *
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function stackedOutermostIndices(): array
    {
        $lastPositive = [];
        $lastNegative = [];

        foreach ($this->seriesCollection->items as $j => $series) {
            foreach ($series->points as $i => $point) {
                if ($point->value > 0.0) {
                    $lastPositive[$i] = $j;
                } elseif ($point->value < 0.0) {
                    $lastNegative[$i] = $j;
                }
            }
        }

        return [$lastPositive, $lastNegative];
    }

    /**
     * Advance the per-slot stacked cursor by $value and return the
     * segment's [start, end] data-space coordinates.
     *
     * @param array<int, float> $posCursor
     * @param array<int, float> $negCursor
     * @return array{float, float}
     */
    private function advanceStack(array &$posCursor, array &$negCursor, int $slot, float $value): array
    {
        if ($value > 0.0) {
            $start = $posCursor[$slot];
            $end = $start + $value;
            $posCursor[$slot] = $end;

            return [$start, $end];
        }

        $end = $negCursor[$slot];
        $start = $end + $value;
        $negCursor[$slot] = $start;

        return [$start, $end];
    }

    private function makeWrapper(Viewport $viewport): Wrapper
    {
        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);

        return $wrapper;
    }

    private function verticalViewport(bool $hasLabels): Viewport
    {
        $hasAxesOrGrid = $this->showAxes || $this->showGrid;

        return new Viewport(
            100,
            100,
            paddingTop: $hasAxesOrGrid ? 4.0 : 0.0,
            paddingRight: $hasAxesOrGrid ? 2.0 : 0.0,
            paddingBottom: $hasLabels ? ($this->showAxes ? 14.0 : 8.0) : 0.0,
            paddingLeft: $hasAxesOrGrid ? 12.0 : 0.0,
        );
    }

    private function horizontalViewport(bool $hasLabels): Viewport
    {
        return new Viewport(
            100,
            100,
            paddingTop: 2.0,
            paddingRight: $this->showAxes ? 6.0 : 2.0,
            paddingBottom: $this->showAxes ? 8.0 : 2.0,
            paddingLeft: $hasLabels ? 22.0 : 2.0,
        );
    }

    /**
     * @return array{float, float}
     */
    private function resolveDomain(string $mode): array
    {
        [$min, $max] = $this->rawDomain($mode);
        $min = min(0.0, $min);

        if ($min === $max) {
            $max += 1.0;
        }

        return [$min, $max];
    }

    /**
     * @return array{float, float}
     */
    private function rawDomain(string $mode): array
    {
        if ($mode === self::MODE_STACKED) {
            return [$this->seriesCollection->stackedMin(), $this->seriesCollection->stackedMax()];
        }

        return [$this->seriesCollection->valueMin(), $this->seriesCollection->valueMax()];
    }

    /**
     * `auto` is grouped — single series naturally collapses into one bar
     * per slot since the per-slot bar count equals the series count.
     */
    private function effectiveMode(): string
    {
        return $this->mode === self::MODE_AUTO ? self::MODE_GROUPED : $this->mode;
    }

    private function emitHorizontalGridLines(Wrapper $wrapper, Viewport $viewport, Scale $yScale): void
    {
        if (!$this->showGrid) {
            return;
        }

        foreach ($yScale->ticks($this->tickCount) as $tick) {
            $y = $yScale->map($tick);
            $wrapper->add($this->gridLine($viewport->plotLeft(), $y, $viewport->plotRight(), $y));
        }
    }

    private function emitVerticalGridLines(Wrapper $wrapper, Viewport $viewport, Scale $xScale): void
    {
        if (!$this->showGrid) {
            return;
        }

        foreach ($xScale->ticks($this->tickCount) as $tick) {
            $x = $xScale->map($tick);
            $wrapper->add($this->gridLine($x, $viewport->plotTop(), $x, $viewport->plotBottom()));
        }
    }

    private function gridLine(float $x1, float $y1, float $x2, float $y2): Tag
    {
        return Tag::void('line', [
            'x1' => Tag::formatFloat($x1),
            'x2' => Tag::formatFloat($x2),
            'y1' => Tag::formatFloat($y1),
            'y2' => Tag::formatFloat($y2),
            'stroke' => $this->theme->gridColor,
            'stroke-width' => '1',
            'vector-effect' => 'non-scaling-stroke',
        ]);
    }

    private function emitVerticalAxis(Wrapper $wrapper, Viewport $viewport, Scale $yScale, float $baseY): void
    {
        if (!$this->showAxes) {
            return;
        }

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
            $wrapper->label(new Label(
                text: $this->formatNumber($tick),
                left: 0,
                top: $yScale->map($tick),
                align: 'start',
                verticalAlign: 'middle',
            ));
        }
    }

    private function emitHorizontalAxisLabels(Wrapper $wrapper, Scale $xScale): void
    {
        if (!$this->showAxes) {
            return;
        }

        foreach ($xScale->ticks($this->tickCount) as $tick) {
            $wrapper->label(new Label(
                text: $this->formatNumber($tick),
                left: $xScale->map($tick),
                bottom: 0,
                align: 'center',
                verticalAlign: 'bottom',
            ));
        }
    }

    private function emitBottomCategoryLabels(Wrapper $wrapper, Viewport $viewport, float $slotWidth): void
    {
        foreach ($this->visibleCategoryLabels() as $i => $label) {
            $wrapper->label(new Label(
                text: $label,
                left: $viewport->plotLeft() + ($i + 0.5) * $slotWidth,
                bottom: 0,
                align: 'center',
                verticalAlign: 'bottom',
            ));
        }
    }

    private function emitLeftCategoryLabels(Wrapper $wrapper, Viewport $viewport, float $slotHeight): void
    {
        foreach ($this->visibleCategoryLabels() as $i => $label) {
            $wrapper->label(new Label(
                text: $label,
                left: 0,
                top: $viewport->plotTop() + ($i + 0.5) * $slotHeight,
                align: 'start',
                verticalAlign: 'middle',
            ));
        }
    }

    /**
     * @return array<int, string>
     */
    private function visibleCategoryLabels(): array
    {
        if (!$this->seriesCollection->hasLabels()) {
            return [];
        }

        $labels = [];

        foreach ($this->seriesCollection->commonLabels() as $i => $label) {
            if ($label === null) {
                continue;
            }

            if ($label === '') {
                continue;
            }

            $labels[$i] = $label;
        }

        return $labels;
    }

    private function markInteractivity(Wrapper $wrapper, ?string $secondaryVariant = null): void
    {
        $wrapper->markHasSeriesElements();

        if (!$this->animated) {
            return;
        }

        $wrapper->enableAnimation();

        if ($secondaryVariant !== null) {
            $wrapper->setSecondaryVariant($secondaryVariant);
        }
    }

    private function applyLegend(Wrapper $wrapper): void
    {
        if (!$this->showLegend) {
            return;
        }

        $wrapper->setLegend($this->buildLegendEntries());
    }

    /**
     * For animation staggering: index in the rendering order so visually
     * adjacent bars wave in together.
     */
    private function staggerDelay(int $position): string
    {
        return (string) round($position * 0.08, 3);
    }

    private function barId(int $seriesIndex, int $pointIndex): string
    {
        return "{$this->chartId()}-s{$seriesIndex}-pt-{$pointIndex}";
    }

    private function verticalTfo(float $value): string
    {
        return $value >= 0.0 ? 'center bottom' : 'center top';
    }

    private function horizontalTfo(float $value): string
    {
        return $value >= 0.0 ? 'left center' : 'right center';
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
        if ($series->name === '') {
            return $pointLabel;
        }

        if ($pointLabel === null || $pointLabel === '') {
            return $series->name;
        }

        return "{$series->name} — {$pointLabel}";
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

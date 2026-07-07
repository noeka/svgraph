<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Data\SeriesCollection;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Svg\Tag;
use Noeka\Svgraph\Svg\Wrapper;

/**
 * Base for charts plotting one or more `Series` against x/y axes (line,
 * sparkline, bar). Owns the series collection plumbing and the fluent
 * setters shared by every series chart; geometry and drawing stay in the
 * concrete classes.
 */
abstract class AbstractSeriesChart extends AbstractChart
{
    protected SeriesCollection $seriesCollection;

    protected ?string $primarySeriesColor = null;

    protected bool $showAxes = false;
    protected bool $showGrid = false;
    protected bool $showLegend = false;

    protected int $tickCount = 5;

    public function __construct()
    {
        parent::__construct();
        $this->seriesCollection = new SeriesCollection();
    }

    /** @param iterable<mixed> $data */
    public function data(iterable $data): static
    {
        $this->seriesCollection = new SeriesCollection([Series::from($data)]);

        return $this;
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

    protected function makeWrapper(Viewport $viewport): Wrapper
    {
        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);

        return $wrapper;
    }

    protected function renderEmpty(): string
    {
        $wrapper = $this->makeWrapper(new Viewport());
        $this->applyAccessibility($wrapper);

        return $wrapper->render();
    }

    /**
     * Per-series color: explicit Series->color wins, then the chart-level
     * primary-color shortcut for series 0 (`->stroke()` on line charts,
     * `->color()` on bar charts), then the theme palette.
     */
    protected function resolveSeriesColor(Series $series, int $index): string
    {
        if ($series->color !== null) {
            return $series->color;
        }

        if ($index === 0 && $this->primarySeriesColor !== null) {
            return $this->primarySeriesColor;
        }

        return $this->theme->colorAt($index);
    }

    /**
     * Display name for a series, falling back to "Series N" (1-based).
     */
    protected function seriesName(Series $series, int $index): string
    {
        return $series->name !== '' ? $series->name : 'Series ' . ($index + 1);
    }

    /**
     * Prefix tooltip with the series name when set so multi-series points
     * can be told apart.
     */
    protected function labelFor(Series $series, ?string $pointLabel): ?string
    {
        if ($series->name === '') {
            return $pointLabel;
        }

        return $pointLabel === null || $pointLabel === ''
            ? $series->name
            : "{$series->name} — {$pointLabel}";
    }

    /**
     * @return list<array{id: string, seriesIndex: int, name: string, color: string}>
     */
    protected function buildLegendEntries(): array
    {
        $chartId = $this->chartId();
        $entries = [];

        foreach ($this->seriesCollection->items as $i => $series) {
            $entries[] = [
                'id' => "{$chartId}-s{$i}",
                'seriesIndex' => $i,
                'name' => $this->seriesName($series, $i),
                'color' => $this->resolveSeriesColor($series, $i),
            ];
        }

        return $entries;
    }

    protected function applyLegend(Wrapper $wrapper): void
    {
        if (!$this->showLegend) {
            return;
        }

        $wrapper->setLegend($this->buildLegendEntries());
    }

    /**
     * Straight axis/grid guide line with a constant 1px stroke.
     */
    protected function plotLine(float $x1, float $y1, float $x2, float $y2, string $color): Tag
    {
        return Tag::void('line', [
            'x1' => Tag::formatFloat($x1),
            'x2' => Tag::formatFloat($x2),
            'y1' => Tag::formatFloat($y1),
            'y2' => Tag::formatFloat($y2),
            'stroke' => $color,
            'stroke-width' => '1',
            'vector-effect' => 'non-scaling-stroke',
        ]);
    }

    /**
     * Common x-axis labels with null/empty entries dropped, keyed by
     * column index.
     *
     * @return array<int, string>
     */
    protected function filteredCommonLabels(): array
    {
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

    /**
     * Header of the row-label column in the screen-reader data table.
     */
    protected function dataTableRowHeader(): string
    {
        return 'Label';
    }

    /**
     * Cell text for `$series->values[$i]`; the index is guaranteed set by
     * `buildDataTable()`. Charts override to enrich the plain value (e.g.
     * appending an error range).
     */
    protected function dataTableCell(Series $series, int $i): string
    {
        return $this->formatNumber($series->values[$i]);
    }

    #[\Override]
    protected function buildDataTable(): array
    {
        if ($this->seriesCollection->isEmpty()) {
            return ['columns' => [], 'rows' => []];
        }

        $columns = [$this->dataTableRowHeader()];

        foreach ($this->seriesCollection->items as $i => $series) {
            $columns[] = $this->seriesName($series, $i);
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
                    ? $this->dataTableCell($series, $i)
                    : '';
            }

            $rows[] = $row;
        }

        return ['columns' => $columns, 'rows' => $rows];
    }
}

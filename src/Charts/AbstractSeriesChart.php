<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Data\Series;
use Noeka\Svgraph\Data\SeriesCollection;
use Noeka\Svgraph\Geometry\Viewport;
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
}

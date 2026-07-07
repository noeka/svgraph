<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Data\Slice;
use Noeka\Svgraph\Geometry\Path;
use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Svg\Css;
use Noeka\Svgraph\Svg\Label;
use Noeka\Svgraph\Svg\Tag;
use Noeka\Svgraph\Svg\Tooltip;
use Noeka\Svgraph\Svg\Wrapper;

class PieChart extends AbstractChart
{
    /** @var list<Slice> */
    protected array $slices = [];

    protected float $thickness = 0.0;
    protected bool $showLegend = false;
    protected float $startAngle = 0.0;

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

    public function render(): string
    {
        $viewport = new Viewport();
        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);

        if ($this->slices === []) {
            $this->applyAccessibility($wrapper);

            return $wrapper->render();
        }

        $total = $this->sliceTotal();

        if ($total <= 0.0) {
            $this->applyAccessibility($wrapper);

            return $wrapper->render();
        }

        $hasLegend = $this->showLegend;
        $cx = 50.0;
        $cy = $hasLegend ? 42.0 : 50.0;
        $outerRadius = $hasLegend ? 38.0 : 48.0;
        $innerRadius = $outerRadius * $this->thickness;

        $startRad = deg2rad($this->startAngle);

        $chartId = $this->chartId();

        $wrapper->markHasSeriesElements();

        $this->renderStatic($wrapper, $viewport, $chartId, $cx, $cy, $outerRadius, $innerRadius, $total, $startRad, $hasLegend);

        $this->applyAccessibility($wrapper);

        return $wrapper->render();
    }

    private function renderStatic(
        Wrapper $wrapper,
        Viewport $viewport,
        string $chartId,
        float $cx,
        float $cy,
        float $outerRadius,
        float $innerRadius,
        float $total,
        float $startRad,
        bool $hasLegend,
    ): void {
        if ($this->thickness === 0.0 && count($this->slices) === 1) {
            $only = $this->slices[0];
            $color = $this->sliceColor($only, 0);
            $id = "{$chartId}-pt-0";
            $tipText = $this->tooltip($only->label, $only->value);
            $circle = Tag::make('circle', [
                'class' => 'series-0',
                'cx' => Tag::formatFloat($cx),
                'cy' => Tag::formatFloat($cy),
                'r' => Tag::formatFloat($outerRadius),
                'fill' => $color,
                'vector-effect' => 'non-scaling-stroke',
            ])->append(Tag::make('title')->append($tipText));
            $wrapper->add($this->buildLink($only->link, $id, $circle));
            $wrapper->tooltip(Tooltip::at($id, $tipText, $cx, $cy - $outerRadius, $viewport));

            if ($hasLegend) {
                $this->addLegend($wrapper);
            }

            return;
        }

        $angle = $startRad;

        foreach ($this->slices as $i => $slice) {
            $value = max(0.0, $slice->value);

            if ($value <= 0.0) {
                continue;
            }

            $sweep = ($value / $total) * 2 * M_PI;
            $start = $angle;
            $end = $angle + $sweep;

            $color = $this->sliceColor($slice, $i);
            $d = Path::arc($cx, $cy, $outerRadius, $innerRadius, $start, $end);
            $id = "{$chartId}-pt-{$i}";
            $tipText = $this->tooltip($slice->label, $value);
            $path = Tag::make('path', [
                'class' => "series-{$i}",
                'd' => $d,
                'fill' => $color,
                'vector-effect' => 'non-scaling-stroke',
            ])->append(Tag::make('title')->append($tipText));
            $wrapper->add($this->buildLink($slice->link, $id, $path));
            $tipRadius = $innerRadius > 0
                ? ($outerRadius + $innerRadius) / 2
                : $outerRadius * 0.6;
            [$tipX, $tipY] = Path::polar($cx, $cy, $tipRadius, ($start + $end) / 2);
            $wrapper->tooltip(Tooltip::at($id, $tipText, $tipX, $tipY, $viewport));
            $angle += $sweep;
        }

        if ($hasLegend) {
            $this->addLegend($wrapper);
        }
    }

    #[\Override]
    protected function defaultTitle(): string
    {
        return $this->thickness > 0.0 ? 'Donut chart' : 'Pie chart';
    }

    #[\Override]
    protected function defaultDescription(): string
    {
        if ($this->slices === []) {
            return $this->defaultTitle() . ' (no data).';
        }

        $count = count($this->slices);

        return sprintf(
            '%s with %d %s totalling %s.',
            $this->defaultTitle(),
            $count,
            $count === 1 ? 'slice' : 'slices',
            $this->formatNumber($this->sliceTotal()),
        );
    }

    #[\Override]
    protected function buildDataTable(): array
    {
        if ($this->slices === []) {
            return ['columns' => [], 'rows' => []];
        }

        $rows = [];

        foreach ($this->slices as $slice) {
            $rows[] = [
                $slice->label !== '' ? $slice->label : 'Slice',
                $this->formatNumber($slice->value),
            ];
        }

        return ['columns' => ['Slice', 'Value'], 'rows' => $rows];
    }

    /**
     * Sum of every non-negative slice value — negative inputs are clamped
     * to zero, matching how slices are drawn.
     */
    private function sliceTotal(): float
    {
        $total = 0.0;

        foreach ($this->slices as $slice) {
            $total += max(0.0, $slice->value);
        }

        return $total;
    }

    /**
     * Explicit Slice->color wins, then the theme palette by slice index.
     */
    private function sliceColor(Slice $slice, int $index): string
    {
        return $slice->color ?? $this->theme->colorAt($index);
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
            $swatchColor = Css::color($this->sliceColor($slice, $i)) ?? 'currentColor';

            $swatch = '<span style="display:inline-block;width:0.5em;height:0.5em;'
                . 'border-radius:0.125em;margin-right:0.4em;vertical-align:middle;'
                . 'background:' . $swatchColor . ';"></span>';
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

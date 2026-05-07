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
            $this->applyAccessibility($wrapper);
            return $wrapper->render();
        }

        $total = 0.0;
        foreach ($this->slices as $slice) {
            $total += max(0.0, $slice->value);
        }
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
        $padRad = deg2rad($this->padAngle);

        $chartId = $this->chartId();

        $wrapper->markHasSeriesElements();

        if ($this->animated) {
            $wrapper->enableAnimation();
            $this->renderAnimated($wrapper, $viewport, $chartId, $cx, $cy, $outerRadius, $innerRadius, $total, $startRad, $padRad, $hasLegend);
        } else {
            $this->renderStatic($wrapper, $viewport, $chartId, $cx, $cy, $outerRadius, $innerRadius, $total, $startRad, $padRad, $hasLegend);
        }

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
        float $padRad,
        bool $hasLegend,
    ): void {
        if ($this->thickness === 0.0 && count($this->slices) === 1) {
            $only = $this->slices[0];
            $color = $only->color ?? $this->theme->colorAt(0);
            $id = "{$chartId}-pt-0";
            $tipText = $this->tooltip($only->label, $only->value);
            $circle = Tag::make('circle', [
                'class' => 'series-0',
                'cx' => Tag::formatFloat($cx),
                'cy' => Tag::formatFloat($cy),
                'r' => Tag::formatFloat($outerRadius),
                'fill' => $color,
            ])->append(Tag::make('title')->append($tipText));
            $wrapper->add($this->buildLink($only->link, $id, $circle));
            $wrapper->tooltip(new Tooltip(
                id: $id,
                text: Tag::escapeText($tipText),
                leftPct: $cx / $viewport->width * 100,
                topPct: ($cy - $outerRadius) / $viewport->height * 100,
            ));
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
            $start = $angle + ($padRad / 2);
            $end = $angle + $sweep - ($padRad / 2);
            if ($end <= $start) {
                $angle += $sweep;
                continue;
            }
            $color = $slice->color ?? $this->theme->colorAt($i);
            $d = Path::arc($cx, $cy, $outerRadius, $innerRadius, $start, $end);
            $id = "{$chartId}-pt-{$i}";
            $tipText = $this->tooltip($slice->label, $value);
            $path = Tag::make('path', [
                'class' => "series-{$i}",
                'd' => $d,
                'fill' => $color,
            ])->append(Tag::make('title')->append($tipText));
            $wrapper->add($this->buildLink($slice->link, $id, $path));
            $tipRadius = $innerRadius > 0
                ? ($outerRadius + $innerRadius) / 2
                : $outerRadius * 0.6;
            [$tipX, $tipY] = Path::polar($cx, $cy, $tipRadius, ($start + $end) / 2);
            $wrapper->tooltip(new Tooltip(
                id: $id,
                text: Tag::escapeText($tipText),
                leftPct: $tipX / $viewport->width * 100,
                topPct: $tipY / $viewport->height * 100,
            ));
            $angle += $sweep;
        }

        if ($hasLegend) {
            $this->addLegend($wrapper);
        }
    }

    /**
     * Animated rendering using the stroke-circle technique:
     * each slice is a circle with stroke-dasharray set to show only its arc
     * portion, and stroke-dashoffset to position it correctly. The CSS
     * animation sweeps stroke-dasharray from 0 to the arc length.
     */
    private function renderAnimated(
        Wrapper $wrapper,
        Viewport $viewport,
        string $chartId,
        float $cx,
        float $cy,
        float $outerRadius,
        float $innerRadius,
        float $total,
        float $startRad,
        float $padRad,
        bool $hasLegend,
    ): void {
        // Stroke-circle geometry: the circle radius sits at the midpoint of the
        // ring, with stroke-width spanning the full ring width.
        $strokeR = $innerRadius > 0.0
            ? ($outerRadius + $innerRadius) / 2.0
            : $outerRadius / 2.0;
        $strokeWidth = $innerRadius > 0.0
            ? $outerRadius - $innerRadius
            : $outerRadius;
        $circumference = 2.0 * M_PI * $strokeR;

        $tipRadius = $innerRadius > 0.0
            ? ($outerRadius + $innerRadius) / 2.0
            : $outerRadius * 0.6;

        // Handle single solid-pie slice (thickness=0, one slice).
        // Any donut or multi-slice case falls through to the loop.
        if ($this->thickness === 0.0 && count($this->slices) === 1) {
            $only = $this->slices[0];
            $color = $only->color ?? $this->theme->colorAt(0);
            $id = "{$chartId}-pt-0";
            $tipText = $this->tooltip($only->label, $only->value);
            $circ = Tag::formatFloat($circumference);
            $off = Tag::formatFloat($circumference / 4.0 - ($this->startAngle / 360.0) * $circumference);
            // Full circle: initial dasharray = 0 circ (hidden); animation sweeps to circ 0.
            $circle = Tag::make('circle', [
                'class' => 'series-0',
                'cx' => Tag::formatFloat($cx),
                'cy' => Tag::formatFloat($cy),
                'r' => Tag::formatFloat($strokeR),
                'fill' => 'none',
                'stroke' => $color,
                'stroke-width' => Tag::formatFloat($strokeWidth),
                'stroke-dasharray' => "0 {$circ}",
                'stroke-dashoffset' => $off,
                'style' => "--svgraph-pie-off:{$off};--svgraph-pie-len:{$circ};--svgraph-pie-circ:{$circ};",
            ])->append(Tag::make('title')->append($tipText));
            $wrapper->add($this->buildLink($only->link, $id, $circle));
            $wrapper->tooltip(new Tooltip(
                id: $id,
                text: Tag::escapeText($tipText),
                leftPct: $cx / $viewport->width * 100,
                topPct: ($cy - $outerRadius) / $viewport->height * 100,
            ));
            if ($hasLegend) {
                $this->addLegend($wrapper);
            }
            return;
        }

        $startOffsetArc = ($this->startAngle / 360.0) * $circumference;
        $halfGapArc = $padRad * $strokeR / 2.0;
        $cumulativeArc = 0.0;
        $circ = Tag::formatFloat($circumference);

        $angle = $startRad;
        foreach ($this->slices as $i => $slice) {
            $value = max(0.0, $slice->value);
            if ($value <= 0.0) {
                continue;
            }
            $sweepAngle = ($value / $total) * 2.0 * M_PI;
            $sweepArc = $sweepAngle * $strokeR;
            $visibleArc = max(0.0, $sweepArc - $padRad * $strokeR);

            if ($visibleArc <= 0.0) {
                $cumulativeArc += $sweepArc;
                $angle += $sweepAngle;
                continue;
            }

            $dashOffset = $circumference / 4.0 - $startOffsetArc - $cumulativeArc - $halfGapArc;
            $arcLen = Tag::formatFloat($visibleArc);
            $off = Tag::formatFloat($dashOffset);
            $delay = round($i * 0.08, 3);

            $midAngle = $angle + $sweepAngle / 2.0;

            $color = $slice->color ?? $this->theme->colorAt($i);
            $id = "{$chartId}-pt-{$i}";
            $tipText = $this->tooltip($slice->label, $value);

            $circle = Tag::make('circle', [
                'class' => "series-{$i}",
                'cx' => Tag::formatFloat($cx),
                'cy' => Tag::formatFloat($cy),
                'r' => Tag::formatFloat($strokeR),
                'fill' => 'none',
                'stroke' => $color,
                'stroke-width' => Tag::formatFloat($strokeWidth),
                'stroke-dasharray' => "0 {$circ}",
                'stroke-dashoffset' => $off,
                'style' => "--svgraph-pie-off:{$off};--svgraph-pie-len:{$arcLen};--svgraph-pie-circ:{$circ};animation-delay:{$delay}s;",
            ])->append(Tag::make('title')->append($tipText));

            $wrapper->add($this->buildLink($slice->link, $id, $circle));

            [$tipX, $tipY] = Path::polar($cx, $cy, $tipRadius, $midAngle);
            $wrapper->tooltip(new Tooltip(
                id: $id,
                text: Tag::escapeText($tipText),
                leftPct: $tipX / $viewport->width * 100,
                topPct: $tipY / $viewport->height * 100,
            ));

            $cumulativeArc += $sweepArc;
            $angle += $sweepAngle;
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
        $total = 0.0;
        foreach ($this->slices as $slice) {
            $total += max(0.0, $slice->value);
        }
        $count = count($this->slices);
        return sprintf(
            '%s with %d %s totalling %s.',
            $this->defaultTitle(),
            $count,
            $count === 1 ? 'slice' : 'slices',
            $this->formatNumber($total),
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
            $swatchColor = Css::color($color) ?? 'currentColor';

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

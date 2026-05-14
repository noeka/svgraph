<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Svg\Label;
use Noeka\Svgraph\Svg\Tag;
use Noeka\Svgraph\Svg\Tooltip;
use Noeka\Svgraph\Svg\Wrapper;

final class ProgressChart extends AbstractChart
{
    private ?string $color = null;
    private ?string $trackColor = null;
    private float $cornerRadius = 50.0;
    private bool $showValue = false;
    private ?string $valueLabel = null;

    public function __construct(private float $value = 0.0, private float $target = 100.0)
    {
        parent::__construct();
        $this->variantClass = 'progress';
        $this->aspectRatio = 20.0;
    }

    public function value(float $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function target(float $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function color(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function trackColor(string $color): static
    {
        $this->trackColor = $color;

        return $this;
    }

    /**
     * Corner radius as a percentage of bar height (0=square, 50=fully rounded).
     */
    public function rounded(float $percent): static
    {
        $this->cornerRadius = max(0.0, min(50.0, $percent));

        return $this;
    }

    /**
     * Show a percentage label inside or above the bar.
     */
    public function showValue(bool $on = true, ?string $label = null): static
    {
        $this->showValue = $on;
        $this->valueLabel = $label;

        return $this;
    }

    public function render(): string
    {
        $height = 100.0 / max(0.01, $this->aspectRatio);
        $viewport = new Viewport(100.0, $height);
        $wrapper = new Wrapper($viewport, $this->aspectRatio, $this->variantClass, $this->theme);
        $wrapper->setUserClass($this->cssClass);

        $fraction = $this->target > 0.0 ? max(0.0, min(1.0, $this->value / $this->target)) : 0.0;
        $color = $this->color ?? $this->theme->fill;
        $trackColor = $this->trackColor ?? $this->theme->trackColor;
        $rx = ($this->cornerRadius / 100.0) * $height;

        $wrapper->add(Tag::void('rect', [
            'x' => '0',
            'y' => '0',
            'width' => '100',
            'height' => Tag::formatFloat($height),
            'rx' => Tag::formatFloat($rx),
            'ry' => Tag::formatFloat($rx),
            'fill' => $trackColor,
        ]));

        if ($fraction > 0.0) {
            $id = $this->chartId() . '-pt-0';
            $title = $this->formatNumber($this->value) . ' / ' . $this->formatNumber($this->target);
            $wrapper->add(Tag::make('rect', [
                'id' => $id,
                'x' => '0',
                'y' => '0',
                'width' => Tag::formatFloat($fraction * 100),
                'height' => Tag::formatFloat($height),
                'rx' => Tag::formatFloat($rx),
                'ry' => Tag::formatFloat($rx),
                'fill' => $color,
                'tabindex' => '0',
            ])->append(Tag::make('title')->append($title)));
            $wrapper->tooltip(new Tooltip(
                id: $id,
                text: Tag::escapeText($title),
                leftPct: ($fraction * 100) / $viewport->width * 100,
                topPct: ($height / 2) / $viewport->height * 100,
            ));
        }

        if ($this->showValue) {
            $text = $this->valueLabel ?? (round($fraction * 100) . '%');
            $wrapper->label(new Label(
                text: $text,
                right: 2,
                top: 50,
                align: 'end',
                verticalAlign: 'middle',
            ));
        }

        $this->applyAccessibility($wrapper);

        return $wrapper->render();
    }

    #[\Override]
    protected function defaultDescription(): string
    {
        $percent = $this->target > 0.0
            ? round(max(0.0, min(1.0, $this->value / $this->target)) * 100)
            : 0.0;

        return sprintf(
            'Progress: %s of %s (%s%%).',
            $this->formatNumber($this->value),
            $this->formatNumber($this->target),
            $this->formatNumber($percent),
        );
    }

    #[\Override]
    protected function buildDataTable(): array
    {
        return [
            'columns' => ['Metric', 'Value'],
            'rows' => [
                ['Value', $this->formatNumber($this->value)],
                ['Target', $this->formatNumber($this->target)],
            ],
        ];
    }
}

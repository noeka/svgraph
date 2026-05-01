<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Theme;

abstract class AbstractChart implements \Stringable
{
    protected Theme $theme;

    protected float $aspectRatio = 2.5;

    protected ?string $cssClass = null;

    protected string $variantClass = 'chart';

    private static int $nextId = 0;
    private int $instanceId;

    public function __construct()
    {
        $this->theme = Theme::default();
        $this->instanceId = ++self::$nextId;
    }

    public function theme(Theme $theme): static
    {
        $this->theme = $theme;
        return $this;
    }

    /**
     * Width:height ratio used by the responsive wrapper. e.g. 2.0 == 2:1 landscape.
     */
    public function aspect(float $ratio): static
    {
        $this->aspectRatio = $ratio;
        return $this;
    }

    public function cssClass(?string $class): static
    {
        $this->cssClass = $class;
        return $this;
    }

    abstract public function render(): string;

    public function __toString(): string
    {
        return $this->render();
    }

    protected function formatNumber(float $value): string
    {
        if (abs($value) >= 1000) {
            return number_format($value, 0, '.', ',');
        }
        if (floor($value) === $value) {
            return (string) (int) $value;
        }
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    protected function tooltip(?string $label, float $value): string
    {
        $formatted = $this->formatNumber($value);
        return ($label !== null && $label !== '') ? "{$label}: {$formatted}" : $formatted;
    }

    /**
     * Stable, unique-per-instance chart ID used to build SVG element IDs.
     * Format: svgraph-{n} where n is a monotonically increasing integer.
     */
    protected function chartId(): string
    {
        return 'svgraph-' . $this->instanceId;
    }
}

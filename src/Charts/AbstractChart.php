<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Data\Link;
use Noeka\Svgraph\Svg\Tag;
use Noeka\Svgraph\Theme;

abstract class AbstractChart implements \Stringable
{
    protected Theme $theme;

    protected float $aspectRatio = 2.5;

    protected ?string $cssClass = null;

    protected string $variantClass = 'chart';

    protected bool $animated = false;

    private static int $nextId = 0;
    private readonly int $instanceId;

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

    /**
     * Enable CSS entrance animations. Animations are wrapped in
     * `@media (prefers-reduced-motion: no-preference)` so users with reduced-motion
     * preferences always see a static chart. Duration and easing are configurable
     * via `Theme::withAnimation()`.
     */
    public function animate(bool $on = true): static
    {
        $this->animated = $on;
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

    /**
     * Wrap $inner in an SVG <a> carrying $id when $link is set; otherwise
     * attach $id and tabindex="0" directly to $inner and return it.
     *
     * When wrapped, the <a> is the ID/focus carrier and the inner element keeps
     * only its visual attributes (class, fill, etc.). SVG <a> is natively
     * keyboard-activatable via Enter — no tabindex needed.
     */
    protected function buildLink(?Link $link, string $id, Tag $inner): Tag
    {
        if (!$link instanceof Link) {
            return $inner->attr('id', $id)->attr('tabindex', '0');
        }
        $attrs = ['id' => $id, 'href' => $link->href, 'class' => 'svgraph-linked'];
        if ($link->target !== null) {
            $attrs['target'] = $link->target;
        }
        if ($link->rel !== '') {
            $attrs['rel'] = $link->rel;
        }
        return Tag::make('a', $attrs)->append($inner);
    }
}

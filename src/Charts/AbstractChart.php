<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Charts;

use Noeka\Svgraph\Annotations\Annotation;
use Noeka\Svgraph\Annotations\AnnotationContext;
use Noeka\Svgraph\Annotations\AnnotationLayer;
use Noeka\Svgraph\Data\Link;
use Noeka\Svgraph\Svg\Tag;
use Noeka\Svgraph\Svg\Wrapper;
use Noeka\Svgraph\Theme;

abstract class AbstractChart implements \Stringable
{
    protected Theme $theme;

    protected float $aspectRatio = 2.5;

    protected ?string $cssClass = null;

    protected string $variantClass = 'chart';

    protected bool $animated = false;

    protected ?string $accessibleTitle = null;

    protected ?string $accessibleDescription = null;

    /** @var list<Annotation> */
    protected array $annotations = [];

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

    /**
     * Add a chart overlay — reference line, threshold band, target zone, or
     * callout. Multiple annotations may be added; they are drawn in insertion
     * order within their z-layer (bands and reference lines below data,
     * callouts above).
     *
     * Annotations are stored on every chart type for API uniformity, but only
     * charts with a meaningful x/y plot area (line, sparkline, bar) actually
     * render them. Pie, donut, and progress charts silently ignore them.
     */
    public function annotate(Annotation $annotation): static
    {
        $this->annotations[] = $annotation;
        return $this;
    }

    /**
     * Set the chart's accessible name (surfaced as `<title>` and read by
     * screen readers via `aria-labelledby`). When unset, the chart falls
     * back to a generic "{Variant} chart" label.
     */
    public function title(string $text): static
    {
        $this->accessibleTitle = $text;
        return $this;
    }

    /**
     * Set the chart's accessible long description (surfaced as `<desc>` and
     * read by screen readers via `aria-describedby`). When unset, charts
     * derive a one-line summary from the data.
     */
    public function description(string $text): static
    {
        $this->accessibleDescription = $text;
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

    /**
     * Push every annotation in `$layer` into the wrapper as raw SVG. Empty
     * renders (annotations whose anchor falls outside the visible domain)
     * are skipped silently.
     */
    protected function renderAnnotationLayer(
        Wrapper $wrapper,
        AnnotationContext $context,
        AnnotationLayer $layer,
    ): void {
        foreach ($this->annotations as $annotation) {
            if ($annotation->layer() !== $layer) {
                continue;
            }
            $svg = $annotation->render($context);
            if ($svg !== '') {
                $wrapper->add($svg);
            }
        }
    }

    /**
     * Push every annotation's HTML labels into the wrapper. Called once per
     * render after axis labels so annotation labels paint on top.
     */
    protected function emitAnnotationLabels(Wrapper $wrapper, AnnotationContext $context): void
    {
        foreach ($this->annotations as $annotation) {
            foreach ($annotation->labels($context) as $label) {
                $wrapper->label($label);
            }
        }
    }

    /**
     * Wire the wrapper's accessible labels and screen-reader data table.
     * Every concrete chart calls this once before invoking `$wrapper->render()`.
     */
    protected function applyAccessibility(Wrapper $wrapper): void
    {
        $id = $this->chartId();
        $title = $this->accessibleTitle ?? $this->defaultTitle();
        $description = $this->accessibleDescription ?? $this->defaultDescription();
        $wrapper->setAccessibility(
            $id . '-title',
            $title,
            $id . '-desc',
            $description,
        );

        $table = $this->buildDataTable();
        if ($table['rows'] !== []) {
            $wrapper->setDataTable($table['columns'], $table['rows']);
        }
    }

    /**
     * Default chart title used when the caller hasn't supplied one. Concrete
     * charts can override for friendlier labels.
     */
    protected function defaultTitle(): string
    {
        return ucfirst($this->variantClass) . ' chart';
    }

    /**
     * Default `<desc>` summary. Charts override to surface the underlying
     * data shape (point count, value range, etc).
     */
    protected function defaultDescription(): string
    {
        return $this->defaultTitle() . '.';
    }

    /**
     * Data the wrapper renders as a screen-reader-only `<table>`. The first
     * column header labels rows; remaining columns are values. Empty rows
     * skip table emission entirely.
     *
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    protected function buildDataTable(): array
    {
        return ['columns' => [], 'rows' => []];
    }
}

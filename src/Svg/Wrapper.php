<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Svg;

use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Theme;

/**
 * Composes the envelope: a relatively-positioned <div>
 * sized by padding-bottom, an absolutely-positioned <svg> with
 * preserveAspectRatio="none" for shape stretching, and an absolute <div>
 * for HTML labels that stay readable at any aspect ratio.
 *
 * When tooltips are registered via tooltip(), a <style> block and hidden
 * tooltip <div>s are also emitted. CSS custom properties on the wrapper
 * element control tooltip appearance and can be overridden from user CSS:
 *   --svgraph-tt-bg, --svgraph-tt-fg, --svgraph-tt-r
 */
final class Wrapper
{
    /** @var list<string|Tag> */
    private array $svgChildren = [];

    /** @var list<Label> */
    private array $labels = [];

    /** @var list<Tooltip> */
    private array $tooltips = [];

    /** @var list<array{id: string, seriesIndex: int, name: string, color: string}> */
    private array $legendEntries = [];

    private ?string $userClass = null;

    private bool $hasSeriesElements = false;

    private bool $animated = false;

    private ?string $secondaryVariant = null;

    private int $crosshairColumns = 0;

    private ?string $titleId = null;
    private ?string $titleText = null;
    private ?string $descId = null;
    private ?string $descText = null;

    /** @var array{columns: list<string>, rows: list<list<string>>}|null */
    private ?array $dataTable = null;

    public function __construct(
        private readonly Viewport $viewport,
        private readonly float $aspectRatio,
        private readonly string $variantClass,
        private readonly Theme $theme,
    ) {}

    public function add(string|Tag $element): self
    {
        $this->svgChildren[] = $element;

        return $this;
    }

    public function label(Label $label): self
    {
        $this->labels[] = $label;

        return $this;
    }

    public function tooltip(Tooltip $tooltip): self
    {
        $this->tooltips[] = $tooltip;

        return $this;
    }

    public function setUserClass(?string $class): self
    {
        $this->userClass = $class;

        return $this;
    }

    public function markHasSeriesElements(): self
    {
        $this->hasSeriesElements = true;

        return $this;
    }

    public function enableAnimation(): self
    {
        $this->animated = true;

        return $this;
    }

    /**
     * Enable per-column crosshair styling. $columnCount is the number of x-columns
     * in the chart; one CSS rule block is emitted per column to drive the
     * `:has([data-x="N"]:hover)` activation pattern.
     */
    public function enableCrosshair(int $columnCount): self
    {
        $this->crosshairColumns = max(0, $columnCount);

        return $this;
    }

    /**
     * Add an extra `svgraph--{variant}` class to the wrapper, used to
     * distinguish sub-variants (e.g. horizontal bars) for animation CSS.
     */
    public function setSecondaryVariant(string $variant): self
    {
        $this->secondaryVariant = $variant;

        return $this;
    }

    /**
     * Enable a CSS-only toggle legend. Each entry produces a hidden
     * checkbox + label; the wrapper emits CSS rules that hide the matching
     * `series-{N}` elements when the entry is unchecked.
     *
     * @param list<array{id: string, seriesIndex: int, name: string, color: string}> $entries
     *   `id` must be a CSS-safe identifier unique to the chart instance
     *   (the chart layer is responsible for collision avoidance).
     */
    public function setLegend(array $entries): self
    {
        $this->legendEntries = $entries;

        return $this;
    }

    /**
     * Wire accessible labels onto the root SVG.
     *
     * The wrapper emits `<title id="$titleId">$title</title>` and
     * `<desc id="$descId">$description</desc>` as the first children of the
     * SVG, then sets `role="img"` plus `aria-labelledby` / `aria-describedby`
     * on the SVG root to reference them. Without a call to this method the
     * SVG falls back to `aria-hidden="true"` for legacy decorative use.
     */
    public function setAccessibility(
        string $titleId,
        string $title,
        string $descId,
        string $description,
    ): self {
        $this->titleId = $titleId;
        $this->titleText = $title;
        $this->descId = $descId;
        $this->descText = $description;

        return $this;
    }

    /**
     * Set a screen-reader-only `<table>` rendered alongside the chart so
     * assistive tech users can read the underlying data row by row.
     *
     * @param list<string>       $columns Column headers (first cell labels rows).
     * @param list<list<string>> $rows    One row per data point; cell count must match `$columns`.
     */
    public function setDataTable(array $columns, array $rows): self
    {
        $this->dataTable = ['columns' => $columns, 'rows' => $rows];

        return $this;
    }

    public function render(): string
    {
        $hasLegend = $this->legendEntries !== [];
        $hasDataTable = $this->dataTable !== null && $this->dataTable['rows'] !== [];

        // Aspect-ratio styling sits on whichever element holds the SVG: the
        // outer .svgraph when no legend, or an inner .svgraph__chart when a
        // legend is rendered (so the legend can sit below in flow).
        $aspectStyle = sprintf(
            'position:relative;width:100%%;padding-bottom:%s%%;',
            Tag::formatFloat((1.0 / max($this->aspectRatio, 0.01)) * 100.0),
        );

        $outerDiv = Tag::make('div', [
            'class' => implode(' ', $this->buildWrapperClasses()),
            'style' => $this->buildWrapperStyle($hasLegend, $aspectStyle),
        ]);

        if ($this->tooltips !== [] || $this->hasSeriesElements || $this->animated || $this->crosshairColumns > 0 || $hasLegend || $hasDataTable) {
            $outerDiv->appendRaw($this->buildStyle($hasDataTable));
        }

        // Hidden checkbox toggles must precede the chart and legend so the
        // sibling combinator (~) can target them when unchecked.
        if ($hasLegend) {
            foreach ($this->legendEntries as $entry) {
                $outerDiv->append(Tag::void('input', [
                    'type' => 'checkbox',
                    'id' => $entry['id'],
                    'class' => 'svgraph-toggle',
                    'checked' => true,
                    'aria-label' => $entry['name'],
                ]));
            }
        }

        // The chart parent: outer .svgraph when no legend, inner .svgraph__chart when legend is on.
        $chartParent = $hasLegend
            ? Tag::make('div', ['class' => 'svgraph__chart', 'style' => $aspectStyle])
            : $outerDiv;

        $chartParent->append($this->buildSvgElement());

        if ($this->labels !== []) {
            $chartParent->append($this->buildLabelsElement());
        }

        foreach ($this->tooltips as $tooltip) {
            $chartParent->append($this->buildTooltipElement($tooltip));
        }

        if ($hasLegend) {
            $outerDiv->append($chartParent);
            $outerDiv->append($this->buildLegend());
        }

        if ($hasDataTable) {
            $outerDiv->appendRaw($this->buildDataTable());
        }

        return (string) $outerDiv;
    }

    /** @return list<string> */
    private function buildWrapperClasses(): array
    {
        $classes = ['svgraph', 'svgraph--' . $this->variantClass];

        if ($this->secondaryVariant !== null) {
            $classes[] = 'svgraph--' . $this->secondaryVariant;
        }

        if ($this->userClass !== null && $this->userClass !== '') {
            $classes[] = $this->userClass;
        }

        return $classes;
    }

    private function buildWrapperStyle(bool $hasLegend, string $aspectStyle): string
    {
        $style = $hasLegend ? '' : $aspectStyle;

        if ($this->tooltips !== []) {
            $tooltipBackground = Css::color($this->theme->tooltipBackground) ?? '#1f2937';
            $tooltipForeground = Css::color($this->theme->tooltipTextColor) ?? '#f9fafb';
            $tooltipBorderRadius = Css::length($this->theme->tooltipBorderRadius) ?? '0.25rem';
            $style .= "--svgraph-tt-bg:{$tooltipBackground};--svgraph-tt-fg:{$tooltipForeground};--svgraph-tt-r:{$tooltipBorderRadius};";
        }

        if ($this->hasSeriesElements) {
            $hoverBrightness = Css::number($this->theme->hoverBrightness) ?? '1.2';
            $strokeWidth = Css::number($this->theme->hoverStrokeWidth) ?? '1.5';
            $popDistance = Css::lengthWithUnit($this->theme->piePopDistance) ?? '3px';
            $style .= "--svgraph-hover-brightness:{$hoverBrightness};"
                . "--svgraph-hover-stroke-width:{$strokeWidth};"
                . "--svgraph-pie-pop-distance:{$popDistance};";
        }

        if ($this->animated) {
            $animationDuration = Css::duration($this->theme->animationDuration) ?? '0.6s';
            $animationEasing = Css::easing($this->theme->animationEasing) ?? 'ease-out';
            $style .= "--svgraph-anim-dur:{$animationDuration};--svgraph-anim-ease:{$animationEasing};";
        }

        return $style;
    }

    private function buildSvgElement(): Tag
    {
        $svgStyle = 'position:absolute;inset:0;width:100%;height:100%;display:block;overflow:visible;';

        $accessibilityAttributes = $this->titleId !== null && $this->descId !== null
            ? ['role' => 'img', 'aria-labelledby' => $this->titleId, 'aria-describedby' => $this->descId]
            : ['aria-hidden' => 'true'];

        $svg = Tag::make('svg', [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'viewBox' => $this->viewport->viewBox(),
            'preserveAspectRatio' => 'none',
            'style' => $svgStyle,
            'focusable' => 'false',
            ...$accessibilityAttributes,
        ]);

        // <title> and <desc> must be the first children so AT picks them up
        // as the SVG's accessible name and description.
        if ($this->titleId !== null && $this->titleText !== null) {
            $svg->append(Tag::make('title', ['id' => $this->titleId])->append($this->titleText));
        }

        if ($this->descId !== null && $this->descText !== null) {
            $svg->append(Tag::make('desc', ['id' => $this->descId])->append($this->descText));
        }

        foreach ($this->svgChildren as $child) {
            $child instanceof Tag ? $svg->append($child) : $svg->appendRaw($child);
        }

        return $svg;
    }

    private function buildLabelsElement(): Tag
    {
        $labelStyle = sprintf(
            'position:absolute;inset:0;pointer-events:none;font-family:%s;font-size:%s;color:%s;line-height:1;',
            $this->resolvedFontFamily(),
            $this->resolvedFontSize(),
            $this->resolvedTextColor(),
        );

        $labelDiv = Tag::make('div', [
            'class' => 'svgraph__labels',
            'style' => $labelStyle,
        ]);

        foreach ($this->labels as $label) {
            $labelDiv->appendRaw($label->render());
        }

        return $labelDiv;
    }

    private function buildTooltipElement(Tooltip $tooltip): Tag
    {
        $left = Tag::formatFloat($tooltip->leftPct) . '%';
        $top = Tag::formatFloat($tooltip->topPct) . '%';

        return Tag::make('div', [
            'class' => 'svgraph-tooltip',
            'data-for' => $tooltip->id,
            'data-x' => $tooltip->dataX !== null ? (string) $tooltip->dataX : null,
            'style' => "position:absolute;left:{$left};top:{$top};",
        ])->appendRaw($tooltip->text);
    }

    private function resolvedFontFamily(): string
    {
        return Css::fontFamily($this->theme->fontFamily) ?? 'inherit';
    }

    private function resolvedFontSize(): string
    {
        return Css::length($this->theme->fontSize) ?? '0.75rem';
    }

    private function resolvedTextColor(): string
    {
        return Css::color($this->theme->textColor) ?? 'currentColor';
    }

    /**
     * Render the visually-hidden screen-reader data table.
     *
     * Hidden via the `.svgraph-sr-only` class (clip + 1px box) so it stays
     * out of layout and out of sighted view, but remains a fully-formed
     * `<table>` with `<thead>` and `<tbody>` for assistive tech.
     */
    private function buildDataTable(): string
    {
        if ($this->dataTable === null) {
            return '';
        }

        $table = Tag::make('table', ['class' => 'svgraph-sr-only']);
        $thead = Tag::make('thead');
        $headRow = Tag::make('tr');

        foreach ($this->dataTable['columns'] as $column) {
            $headRow->append(Tag::make('th', ['scope' => 'col'])->append($column));
        }

        $thead->append($headRow);
        $table->append($thead);

        $tbody = Tag::make('tbody');

        foreach ($this->dataTable['rows'] as $row) {
            $tableRow = Tag::make('tr');

            foreach ($row as $index => $cell) {
                // First cell of each row scopes the row; remaining cells are data.
                $cellTag = $index === 0
                    ? Tag::make('th', ['scope' => 'row'])
                    : Tag::make('td');

                $tableRow->append($cellTag->append($cell));
            }

            $tbody->append($tableRow);
        }

        $table->append($tbody);

        return (string) $table;
    }

    private function buildLegend(): Tag
    {
        $legend = Tag::make('div', ['class' => 'svgraph-legend']);

        foreach ($this->legendEntries as $entry) {
            $swatchColor = Css::color($entry['color']) ?? 'currentColor';

            $label = Tag::make('label', [
                'for' => $entry['id'],
                'class' => 'svgraph-legend__entry',
            ]);

            $label->appendRaw(
                '<span class="svgraph-legend__swatch" style="background:' . $swatchColor . ';"></span>',
            );

            $label->append($entry['name']);
            $legend->append($label);
        }

        return $legend;
    }

    private function buildStyle(bool $hasDataTable): string
    {
        $css = '';

        if ($hasDataTable) {
            $css .= '.svgraph-sr-only{position:absolute;width:1px;height:1px;padding:0;'
                . 'margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}';
        }

        if ($this->hasSeriesElements) {
            $css .= $this->buildHoverStyle();
        }

        if ($this->tooltips !== []) {
            $css .= $this->buildTooltipStyle();
        }

        if ($this->animated) {
            $css .= $this->buildAnimationStyle();
        }

        if ($this->crosshairColumns > 0) {
            $css .= $this->buildCrosshairStyle();
        }

        if ($this->legendEntries !== []) {
            $css .= $this->buildLegendStyle();
        }

        return "<style>{$css}</style>";
    }

    /**
     * Pure-CSS toggle legend: each hidden checkbox sits as a sibling of the
     * chart parent and the legend container, so :not(:checked) ~ rules can
     * hide the matching `series-{N}` elements and dim the legend entry.
     *
     * State is page-local — refreshing the page resets every toggle. Hiding
     * a series does NOT rescale the chart's axes; the remaining series stay
     * at their original positions.
     */
    private function buildLegendStyle(): string
    {
        // Visually-hidden checkbox (still keyboard-focusable via the matched <label>).
        $base = '.svgraph-toggle{position:absolute;width:1px;height:1px;padding:0;margin:-1px;'
            . 'overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}'
            . ".svgraph-legend{display:flex;flex-wrap:wrap;gap:0.4em 1em;margin-top:0.5em;"
            . "font-family:{$this->resolvedFontFamily()};font-size:{$this->resolvedFontSize()};color:{$this->resolvedTextColor()};line-height:1.2;}"
            . '.svgraph-legend__entry{display:inline-flex;align-items:center;gap:0.4em;'
            . 'cursor:pointer;user-select:none;transition:opacity 0.15s;}'
            . '.svgraph-legend__swatch{display:inline-block;width:0.8em;height:0.8em;'
            . 'border-radius:0.15em;flex:none;}';

        $rules = '';

        foreach ($this->legendEntries as $entry) {
            $id = $entry['id'];
            $seriesIndex = $entry['seriesIndex'];

            // id is built from chartId() (svgraph-{int}) + s{int}; only [a-zA-Z0-9-].
            $rules .= "#{$id}:not(:checked)~.svgraph__chart .series-{$seriesIndex}{display:none;}"
                . "#{$id}:not(:checked)~.svgraph-legend label[for=\"{$id}\"]{opacity:0.4;}"
                . "#{$id}:focus-visible~.svgraph-legend label[for=\"{$id}\"]{"
                . 'outline:2px solid currentColor;outline-offset:2px;}';
        }

        return $base . $rules;
    }

    private function buildHoverStyle(): string
    {
        // Bars and pie circles/paths: direct interactive elements with series class.
        $direct = '.svgraph rect[class^="series-"]:hover,'
            . '.svgraph rect[class^="series-"]:focus-visible{'
            . 'filter:brightness(var(--svgraph-hover-brightness,1.2));'
            . 'stroke:currentColor;'
            . 'stroke-width:var(--svgraph-hover-stroke-width,1.5);'
            . 'paint-order:stroke fill;'
            . 'outline:none;}'
            . '.svgraph circle[class^="series-"]:hover,'
            . '.svgraph circle[class^="series-"]:focus-visible,'
            . '.svgraph path[class^="series-"]:hover,'
            . '.svgraph path[class^="series-"]:focus-visible{'
            . 'filter:brightness(var(--svgraph-hover-brightness,1.2));'
            . 'outline:none;}';

        // Line markers: visual ellipse is first child of a <g class="series-*">.
        // :hover fires on the group when over the (transparent) hit-target child;
        // :focus-within fires when the hit-target child receives keyboard focus.
        $lineMarkers = '.svgraph g[class^="series-"]:hover>ellipse:first-child,'
            . '.svgraph g[class^="series-"]:focus-within>ellipse:first-child{'
            . 'filter:brightness(var(--svgraph-hover-brightness,1.2));}';

        // Linked elements: cursor + keyboard-focus highlight rules.
        // :hover on the inner element is already handled by the rules above
        // (the inner rect/path/etc. is still directly under the pointer).
        // Only :focus-visible needs extra rules because the <a> receives focus,
        // not the inner element (which has no tabindex when wrapped).
        $linked = '.svgraph a.svgraph-linked{cursor:pointer;}'
            . '.svgraph a.svgraph-linked:focus-visible rect[class^="series-"]{'
            . 'filter:brightness(var(--svgraph-hover-brightness,1.2));'
            . 'stroke:currentColor;'
            . 'stroke-width:var(--svgraph-hover-stroke-width,1.5);'
            . 'paint-order:stroke fill;'
            . 'outline:none;}'
            . '.svgraph a.svgraph-linked:focus-visible circle[class^="series-"],'
            . '.svgraph a.svgraph-linked:focus-visible path[class^="series-"]{'
            . 'filter:brightness(var(--svgraph-hover-brightness,1.2));'
            . 'outline:none;}'
            . '.svgraph a.svgraph-linked:focus-visible g[class^="series-"]>ellipse:first-child{'
            . 'filter:brightness(var(--svgraph-hover-brightness,1.2));}';

        return $direct . $lineMarkers . $linked;
    }

    private function buildTooltipStyle(): string
    {
        $base = '.svgraph-tooltip{'
            . 'position:absolute;display:none;'
            . 'background:var(--svgraph-tt-bg,#1f2937);color:var(--svgraph-tt-fg,#f9fafb);'
            . 'border-radius:var(--svgraph-tt-r,0.25rem);'
            . 'padding:.25rem .5rem;font-size:.75rem;line-height:1.4;'
            . 'white-space:nowrap;pointer-events:none;z-index:10;'
            . 'transform:translate(-50%,-100%);margin-top:-.25rem;}';

        $rules = '';

        foreach ($this->tooltips as $tooltip) {
            // id only contains [a-zA-Z0-9-] — no CSS escaping needed.
            $id = $tooltip->id;
            $rules .= ".svgraph:has(#{$id}:hover) [data-for=\"{$id}\"],"
                . ".svgraph:has(#{$id}:focus-visible) [data-for=\"{$id}\"]{display:block;}";
        }

        return $base . $this->wrapInSupportsHas($rules);
    }

    /**
     * Per-column hover/focus rules for the line-chart crosshair feature.
     *
     * Each column emits CSS that activates when any element carrying its
     * `data-x="N"` attribute is hovered or contains keyboard focus — typically
     * the wide `.svgraph-x-hit` rect or one of the marker `<g>`s. Activation
     * reveals the matching crosshair guide line, brightens every series'
     * marker at that x, and opens every series' tooltip stacked at that x.
     *
     * Wrapped in `@supports selector(:has(a))` so browsers without `:has`
     * fall back gracefully (no crosshair, but the rest of the chart still works).
     */
    private function buildCrosshairStyle(): string
    {
        $base = '.svgraph-crosshair{opacity:0;pointer-events:none;}'
            . '.svgraph-x-hit{fill:transparent;cursor:crosshair;}';

        $rules = '';

        for ($column = 0; $column < $this->crosshairColumns; $column++) {
            $rules .= ".svgraph svg:has([data-x=\"{$column}\"]:hover) .svgraph-crosshair[data-x=\"{$column}\"],"
                . ".svgraph svg:has([data-x=\"{$column}\"]:focus-within) .svgraph-crosshair[data-x=\"{$column}\"]{opacity:1;}"
                . ".svgraph svg:has([data-x=\"{$column}\"]:hover) g[data-x=\"{$column}\"]>ellipse:first-child,"
                . ".svgraph svg:has([data-x=\"{$column}\"]:focus-within) g[data-x=\"{$column}\"]>ellipse:first-child{"
                . 'opacity:1;filter:brightness(var(--svgraph-hover-brightness,1.2));}'
                . ".svgraph:has([data-x=\"{$column}\"]:hover) .svgraph-tooltip[data-x=\"{$column}\"],"
                . ".svgraph:has([data-x=\"{$column}\"]:focus-within) .svgraph-tooltip[data-x=\"{$column}\"]{display:block;}";
        }

        return $base . $this->wrapInSupportsHas($rules);
    }

    private function buildAnimationStyle(): string
    {
        $durationVar = 'var(--svgraph-anim-dur,0.6s)';
        $easingVar = 'var(--svgraph-anim-ease,ease-out)';
        $css = '';
        $reducedMotionCss = '';

        // Line / sparkline: stroke-dasharray draw-on using pathLength="1" normalisation.
        // stroke-dasharray="1" and stroke-dashoffset="1" are set as HTML attributes
        // (where pathLength normalization is reliable); CSS only drives the offset.
        if ($this->variantClass === 'line' || $this->variantClass === 'sparkline') {
            $css .= '@keyframes svgraph-draw-line{from{stroke-dashoffset:1}to{stroke-dashoffset:0}}'
                . '.svgraph--' . $this->variantClass . ' .svgraph-line-path{'
                . 'animation:svgraph-draw-line ' . $durationVar . ' ' . $easingVar . ' both;}';
            // stroke-dashoffset="1" is set in the HTML; override it to 0 here.
            $reducedMotionCss .= '.svgraph--' . $this->variantClass . ' .svgraph-line-path{stroke-dashoffset:0;}';
        }

        // Bar: scale from the baseline edge on enter.
        if ($this->variantClass === 'bar') {
            $css .= $this->buildBarAnimationStyle($durationVar, $easingVar);
        }

        // Pie / donut: stroke-dasharray sweep using the stroke-circle technique.
        // Each slice is rendered as a circle with stroke-dasharray; the from state
        // has dasharray:0 circ (nothing visible) and the to state shows the arc.
        // stroke-dashoffset stays constant (positions the arc correctly) while
        // stroke-dasharray animates to reveal the slice.
        if ($this->variantClass === 'pie' || $this->variantClass === 'donut') {
            $css .= '@keyframes svgraph-pie-sweep{'
                . 'from{stroke-dasharray:0 var(--svgraph-pie-circ)}'
                . 'to{stroke-dasharray:var(--svgraph-pie-len) calc(var(--svgraph-pie-circ) - var(--svgraph-pie-len))}}'
                . '.svgraph--' . $this->variantClass . ' circle[class^="series-"]{'
                . 'animation:svgraph-pie-sweep ' . $durationVar . ' ' . $easingVar . ' both;'
                . 'animation-delay:var(--svgraph-pie-delay,0ms);}';
            // stroke-dasharray="0 circ" is the initial hidden state; show the final arc.
            $reducedMotionCss .= '.svgraph--' . $this->variantClass . ' circle[class^="series-"]{'
                . 'stroke-dasharray:var(--svgraph-pie-len) calc(var(--svgraph-pie-circ) - var(--svgraph-pie-len));}';
        }

        $result = '@media (prefers-reduced-motion:no-preference){' . $css . '}';

        if ($reducedMotionCss !== '') {
            $result .= '@media (prefers-reduced-motion:reduce){' . $reducedMotionCss . '}';
        }

        return $result;
    }

    private function buildBarAnimationStyle(string $durationVar, string $easingVar): string
    {
        // Rounded bars render as <path>; flat bars as <rect>. The animation
        // applies to both shapes.
        if ($this->secondaryVariant === 'bar-h') {
            // Horizontal bars grow from left (positive) or right (negative).
            return '@keyframes svgraph-grow-hbar{from{transform:scaleX(0)}to{transform:scaleX(1)}}'
                . '.svgraph--bar.svgraph--bar-h rect[class^="series-"],'
                . '.svgraph--bar.svgraph--bar-h path[class^="series-"]{'
                . 'transform-box:fill-box;'
                . 'transform-origin:var(--svgraph-bar-tfo,left center);'
                . 'animation:svgraph-grow-hbar ' . $durationVar . ' ' . $easingVar . ' both;'
                . 'animation-delay:var(--svgraph-bar-delay,0s);}';
        }

        // Vertical bars grow from bottom (positive) or top (negative).
        return '@keyframes svgraph-grow-vbar{from{transform:scaleY(0)}to{transform:scaleY(1)}}'
            . '.svgraph--bar:not(.svgraph--bar-h) rect[class^="series-"],'
            . '.svgraph--bar:not(.svgraph--bar-h) path[class^="series-"]{'
            . 'transform-box:fill-box;'
            . 'transform-origin:var(--svgraph-bar-tfo,center bottom);'
            . 'animation:svgraph-grow-vbar ' . $durationVar . ' ' . $easingVar . ' both;'
            . 'animation-delay:var(--svgraph-bar-delay,0s);}';
    }

    private function wrapInSupportsHas(string $rules): string
    {
        return '@supports selector(:has(a)){' . $rules . '}';
    }
}

<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Svg;

use Noeka\Svgraph\Geometry\Viewport;
use Noeka\Svgraph\Theme;

/**
 * Composes the Rich Harris-style envelope: a relatively-positioned <div>
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
        $paddingBottom = (1.0 / max($this->aspectRatio, 0.01)) * 100.0;

        $classes = ['svgraph', 'svgraph--' . $this->variantClass];
        if ($this->secondaryVariant !== null) {
            $classes[] = 'svgraph--' . $this->secondaryVariant;
        }
        if ($this->userClass !== null && $this->userClass !== '') {
            $classes[] = $this->userClass;
        }

        $hasLegend = $this->legendEntries !== [];

        // Aspect-ratio styling sits on whichever element holds the SVG: the
        // outer .svgraph when no legend, or an inner .svgraph__chart when a
        // legend is rendered (so the legend can sit below in flow).
        $aspectStyle = sprintf(
            'position:relative;width:100%%;padding-bottom:%s%%;',
            Tag::formatFloat($paddingBottom),
        );
        $wrapperStyle = $hasLegend ? '' : $aspectStyle;

        if ($this->tooltips !== []) {
            $bg = Css::color($this->theme->tooltipBackground) ?? '#1f2937';
            $fg = Css::color($this->theme->tooltipTextColor) ?? '#f9fafb';
            $r = Css::length($this->theme->tooltipBorderRadius) ?? '0.25rem';
            $wrapperStyle .= "--svgraph-tt-bg:{$bg};--svgraph-tt-fg:{$fg};--svgraph-tt-r:{$r};";
        }

        if ($this->hasSeriesElements) {
            $brightness = Css::number($this->theme->hoverBrightness) ?? '1.2';
            $strokeW = Css::number($this->theme->hoverStrokeWidth) ?? '1.5';
            $popDist = Css::lengthWithUnit($this->theme->piePopDistance) ?? '3px';
            $wrapperStyle .= "--svgraph-hover-brightness:{$brightness};"
                . "--svgraph-hover-stroke-width:{$strokeW};"
                . "--svgraph-pie-pop-distance:{$popDist};";
        }

        if ($this->animated) {
            $dur = Css::duration($this->theme->animationDuration) ?? '0.6s';
            $ease = Css::easing($this->theme->animationEasing) ?? 'ease-out';
            $wrapperStyle .= "--svgraph-anim-dur:{$dur};--svgraph-anim-ease:{$ease};";
        }

        $svgStyle = 'position:absolute;inset:0;width:100%;height:100%;display:block;overflow:visible;';

        $svgAttrs = [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'viewBox' => $this->viewport->viewBox(),
            'preserveAspectRatio' => 'none',
            'style' => $svgStyle,
            'focusable' => 'false',
        ];
        if ($this->titleId !== null && $this->descId !== null) {
            $svgAttrs['role'] = 'img';
            $svgAttrs['aria-labelledby'] = $this->titleId;
            $svgAttrs['aria-describedby'] = $this->descId;
        } else {
            $svgAttrs['aria-hidden'] = 'true';
        }

        $svg = Tag::make('svg', $svgAttrs);
        // <title> and <desc> must be the first children so AT picks them up
        // as the SVG's accessible name and description.
        if ($this->titleId !== null && $this->titleText !== null) {
            $svg->append(Tag::make('title', ['id' => $this->titleId])->append($this->titleText));
        }
        if ($this->descId !== null && $this->descText !== null) {
            $svg->append(Tag::make('desc', ['id' => $this->descId])->append($this->descText));
        }
        foreach ($this->svgChildren as $child) {
            if ($child instanceof Tag) {
                $svg->append($child);
            } else {
                $svg->appendRaw($child);
            }
        }

        $div = Tag::make('div', [
            'class' => implode(' ', $classes),
            'style' => $wrapperStyle,
        ]);

        $hasDataTable = $this->dataTable !== null && $this->dataTable['rows'] !== [];

        if ($this->tooltips !== [] || $this->hasSeriesElements || $this->animated || $this->crosshairColumns > 0 || $hasLegend || $hasDataTable) {
            $div->appendRaw($this->buildStyle($hasDataTable));
        }

        // Hidden checkbox toggles must precede the chart and legend so the
        // sibling combinator (~) can target them when unchecked.
        if ($hasLegend) {
            foreach ($this->legendEntries as $entry) {
                $div->append(Tag::void('input', [
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
            : $div;

        $chartParent->append($svg);

        if ($this->labels !== []) {
            $fontFamily = Css::fontFamily($this->theme->fontFamily) ?? 'inherit';
            $fontSize = Css::length($this->theme->fontSize) ?? '0.75rem';
            $textColor = Css::color($this->theme->textColor) ?? 'currentColor';
            $labelStyle = sprintf(
                'position:absolute;inset:0;pointer-events:none;font-family:%s;font-size:%s;color:%s;line-height:1;',
                $fontFamily,
                $fontSize,
                $textColor,
            );
            $labelTag = Tag::make('div', [
                'class' => 'svgraph__labels',
                'style' => $labelStyle,
            ]);
            foreach ($this->labels as $label) {
                $labelTag->appendRaw($label->render());
            }
            $chartParent->append($labelTag);
        }

        foreach ($this->tooltips as $tip) {
            $left = Tag::formatFloat($tip->leftPct) . '%';
            $top = Tag::formatFloat($tip->topPct) . '%';
            $attrs = [
                'class' => 'svgraph-tooltip',
                'data-for' => $tip->id,
                'data-x' => $tip->dataX !== null ? (string) $tip->dataX : null,
                'style' => "position:absolute;left:{$left};top:{$top};",
            ];
            $chartParent->append(Tag::make('div', $attrs)->appendRaw($tip->text));
        }

        if ($hasLegend) {
            $div->append($chartParent);
            $div->append($this->buildLegend());
        }

        if ($hasDataTable) {
            $div->appendRaw($this->buildDataTable());
        }

        return (string) $div;
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
        foreach ($this->dataTable['columns'] as $col) {
            $headRow->append(Tag::make('th', ['scope' => 'col'])->append($col));
        }
        $thead->append($headRow);
        $table->append($thead);

        $tbody = Tag::make('tbody');
        foreach ($this->dataTable['rows'] as $row) {
            $tr = Tag::make('tr');
            foreach ($row as $i => $cell) {
                // First cell of each row scopes the row; remaining cells are data.
                $tag = $i === 0
                    ? Tag::make('th', ['scope' => 'row'])
                    : Tag::make('td');
                $tr->append($tag->append($cell));
            }
            $tbody->append($tr);
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
        $fontFamily = Css::fontFamily($this->theme->fontFamily) ?? 'inherit';
        $fontSize = Css::length($this->theme->fontSize) ?? '0.75rem';
        $textColor = Css::color($this->theme->textColor) ?? 'currentColor';

        // Visually-hidden checkbox (still keyboard-focusable via the matched <label>).
        $base = '.svgraph-toggle{position:absolute;width:1px;height:1px;padding:0;margin:-1px;'
            . 'overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}'
            . ".svgraph-legend{display:flex;flex-wrap:wrap;gap:0.4em 1em;margin-top:0.5em;"
            . "font-family:{$fontFamily};font-size:{$fontSize};color:{$textColor};line-height:1.2;}"
            . '.svgraph-legend__entry{display:inline-flex;align-items:center;gap:0.4em;'
            . 'cursor:pointer;user-select:none;transition:opacity 0.15s;}'
            . '.svgraph-legend__swatch{display:inline-block;width:0.8em;height:0.8em;'
            . 'border-radius:0.15em;flex:none;}';

        $rules = '';
        foreach ($this->legendEntries as $entry) {
            $id = $entry['id'];
            $n = $entry['seriesIndex'];
            // id is built from chartId() (svgraph-{int}) + s{int}; only [a-zA-Z0-9-].
            $rules .= "#{$id}:not(:checked)~.svgraph__chart .series-{$n}{display:none;}"
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
        foreach ($this->tooltips as $tip) {
            // id only contains [a-zA-Z0-9-] — no CSS escaping needed.
            $id = $tip->id;
            $rules .= ".svgraph:has(#{$id}:hover) [data-for=\"{$id}\"],"
                . ".svgraph:has(#{$id}:focus-visible) [data-for=\"{$id}\"]{display:block;}";
        }

        return $base . '@supports selector(:has(a)){' . $rules . '}';
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
        for ($i = 0; $i < $this->crosshairColumns; $i++) {
            $rules .= ".svgraph svg:has([data-x=\"{$i}\"]:hover) .svgraph-crosshair[data-x=\"{$i}\"],"
                . ".svgraph svg:has([data-x=\"{$i}\"]:focus-within) .svgraph-crosshair[data-x=\"{$i}\"]{opacity:1;}"
                . ".svgraph svg:has([data-x=\"{$i}\"]:hover) g[data-x=\"{$i}\"]>ellipse:first-child,"
                . ".svgraph svg:has([data-x=\"{$i}\"]:focus-within) g[data-x=\"{$i}\"]>ellipse:first-child{"
                . 'opacity:1;filter:brightness(var(--svgraph-hover-brightness,1.2));}'
                . ".svgraph:has([data-x=\"{$i}\"]:hover) .svgraph-tooltip[data-x=\"{$i}\"],"
                . ".svgraph:has([data-x=\"{$i}\"]:focus-within) .svgraph-tooltip[data-x=\"{$i}\"]{display:block;}";
        }

        return $base . '@supports selector(:has(a)){' . $rules . '}';
    }

    private function buildAnimationStyle(): string
    {
        $dur = 'var(--svgraph-anim-dur,0.6s)';
        $ease = 'var(--svgraph-anim-ease,ease-out)';
        $css = '';

        // Line / sparkline: stroke-dasharray draw-on using pathLength="1" normalisation.
        // stroke-dasharray="1" and stroke-dashoffset="1" are set as HTML attributes
        // (where pathLength normalization is reliable); CSS only drives the offset.
        if ($this->variantClass === 'line' || $this->variantClass === 'sparkline') {
            $css .= '@keyframes svgraph-draw-line{from{stroke-dashoffset:1}to{stroke-dashoffset:0}}'
                . '.svgraph--' . $this->variantClass . ' .svgraph-line-path{'
                . 'animation:svgraph-draw-line ' . $dur . ' ' . $ease . ' both;}';
        }

        // Bar: scale from the baseline edge on enter.
        if ($this->variantClass === 'bar') {
            if ($this->secondaryVariant === 'bar-h') {
                // Horizontal bars grow from left (positive) or right (negative).
                $css .= '@keyframes svgraph-grow-hbar{from{transform:scaleX(0)}to{transform:scaleX(1)}}'
                    . '.svgraph--bar.svgraph--bar-h rect[class^="series-"]{'
                    . 'transform-box:fill-box;'
                    . 'transform-origin:var(--svgraph-bar-tfo,left center);'
                    . 'animation:svgraph-grow-hbar ' . $dur . ' ' . $ease . ' both;'
                    . 'animation-delay:var(--svgraph-bar-delay,0s);}';
            } else {
                // Vertical bars grow from bottom (positive) or top (negative).
                $css .= '@keyframes svgraph-grow-vbar{from{transform:scaleY(0)}to{transform:scaleY(1)}}'
                    . '.svgraph--bar:not(.svgraph--bar-h) rect[class^="series-"]{'
                    . 'transform-box:fill-box;'
                    . 'transform-origin:var(--svgraph-bar-tfo,center bottom);'
                    . 'animation:svgraph-grow-vbar ' . $dur . ' ' . $ease . ' both;'
                    . 'animation-delay:var(--svgraph-bar-delay,0s);}';
            }
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
                . 'animation:svgraph-pie-sweep ' . $dur . ' ' . $ease . ' both;'
                . 'animation-delay:var(--svgraph-pie-delay,0ms);}';
        }

        $reduceCss = '';

        // Reduced-motion fallbacks: show the final state without animation for
        // users who request reduced motion but whose page still calls ->animate().
        if ($this->variantClass === 'line' || $this->variantClass === 'sparkline') {
            // stroke-dashoffset="1" is set in the HTML; override it to 0 here.
            $reduceCss .= '.svgraph--' . $this->variantClass . ' .svgraph-line-path{stroke-dashoffset:0;}';
        }
        if ($this->variantClass === 'pie' || $this->variantClass === 'donut') {
            // stroke-dasharray="0 circ" is the initial hidden state; show the final arc.
            $reduceCss .= '.svgraph--' . $this->variantClass . ' circle[class^="series-"]{'
                . 'stroke-dasharray:var(--svgraph-pie-len) calc(var(--svgraph-pie-circ) - var(--svgraph-pie-len));}';
        }

        $result = '@media (prefers-reduced-motion:no-preference){' . $css . '}';
        if ($reduceCss !== '') {
            $result .= '@media (prefers-reduced-motion:reduce){' . $reduceCss . '}';
        }
        return $result;
    }
}

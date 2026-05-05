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

    private ?string $userClass = null;

    private bool $hasSeriesElements = false;

    private bool $animated = false;

    private ?string $secondaryVariant = null;

    private int $crosshairColumns = 0;

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

        $wrapperStyle = sprintf(
            'position:relative;width:100%%;padding-bottom:%s%%;',
            Tag::formatFloat($paddingBottom),
        );

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

        $svg = Tag::make('svg', [
            'xmlns' => 'http://www.w3.org/2000/svg',
            'viewBox' => $this->viewport->viewBox(),
            'preserveAspectRatio' => 'none',
            'style' => $svgStyle,
            'aria-hidden' => 'true',
            'focusable' => 'false',
        ]);
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

        if ($this->tooltips !== [] || $this->hasSeriesElements || $this->animated || $this->crosshairColumns > 0) {
            $div->appendRaw($this->buildStyle());
        }

        $div->append($svg);

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
            $div->append($labelTag);
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
            $div->append(Tag::make('div', $attrs)->appendRaw($tip->text));
        }

        return (string) $div;
    }

    private function buildStyle(): string
    {
        $css = '';

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

        return "<style>{$css}</style>";
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

        // Pie/donut slice pop: per-slice --pop-x/--pop-y unit vectors are set as
        // inline CSS custom properties by the PHP renderer.
        $piePop = '.svgraph--pie path[class^="series-"]:hover,'
            . '.svgraph--pie path[class^="series-"]:focus-visible,'
            . '.svgraph--pie circle[class^="series-"]:hover,'
            . '.svgraph--pie circle[class^="series-"]:focus-visible,'
            . '.svgraph--donut path[class^="series-"]:hover,'
            . '.svgraph--donut path[class^="series-"]:focus-visible,'
            . '.svgraph--donut circle[class^="series-"]:hover,'
            . '.svgraph--donut circle[class^="series-"]:focus-visible{'
            . 'transform:translate('
            . 'calc(var(--svgraph-pie-pop-distance,3px)*var(--pop-x,0)),'
            . 'calc(var(--svgraph-pie-pop-distance,3px)*var(--pop-y,0))'
            . ');}';

        // Under reduced motion: suppress the translate pop but keep colour change.
        $reducedMotion = '@media (prefers-reduced-motion:reduce){'
            . '.svgraph--pie path[class^="series-"]:hover,'
            . '.svgraph--pie path[class^="series-"]:focus-visible,'
            . '.svgraph--pie a.svgraph-linked:focus-visible path[class^="series-"],'
            . '.svgraph--pie circle[class^="series-"]:hover,'
            . '.svgraph--pie circle[class^="series-"]:focus-visible,'
            . '.svgraph--pie a.svgraph-linked:focus-visible circle[class^="series-"],'
            . '.svgraph--donut path[class^="series-"]:hover,'
            . '.svgraph--donut path[class^="series-"]:focus-visible,'
            . '.svgraph--donut a.svgraph-linked:focus-visible path[class^="series-"],'
            . '.svgraph--donut circle[class^="series-"]:hover,'
            . '.svgraph--donut circle[class^="series-"]:focus-visible,'
            . '.svgraph--donut a.svgraph-linked:focus-visible circle[class^="series-"]{'
            . 'transform:none;}}';

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
            . 'filter:brightness(var(--svgraph-hover-brightness,1.2));}'
            . '.svgraph--pie a.svgraph-linked:focus-visible path[class^="series-"],'
            . '.svgraph--pie a.svgraph-linked:focus-visible circle[class^="series-"],'
            . '.svgraph--donut a.svgraph-linked:focus-visible path[class^="series-"],'
            . '.svgraph--donut a.svgraph-linked:focus-visible circle[class^="series-"]{'
            . 'transform:translate('
            . 'calc(var(--svgraph-pie-pop-distance,3px)*var(--pop-x,0)),'
            . 'calc(var(--svgraph-pie-pop-distance,3px)*var(--pop-y,0))'
            . ');}';

        return $direct . $lineMarkers . $piePop . $reducedMotion . $linked;
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
        if ($this->variantClass === 'line' || $this->variantClass === 'sparkline') {
            $css .= '@keyframes svgraph-draw-line{from{stroke-dashoffset:1}to{stroke-dashoffset:0}}'
                . '.svgraph--' . $this->variantClass . ' .svgraph-line-path{'
                . 'stroke-dasharray:1;'
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

        return '@media (prefers-reduced-motion:no-preference){' . $css . '}';
    }
}

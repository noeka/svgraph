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

    public function render(): string
    {
        $paddingBottom = (1.0 / max($this->aspectRatio, 0.01)) * 100.0;

        $classes = ['svgraph', 'svgraph--' . $this->variantClass];
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

        if ($this->tooltips !== []) {
            $div->appendRaw($this->buildTooltipStyle());
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
            $div->append(
                Tag::make('div', [
                    'class' => 'svgraph-tooltip',
                    'data-for' => $tip->id,
                    'style' => "position:absolute;left:{$left};top:{$top};",
                ])->appendRaw($tip->text),
            );
        }

        return (string) $div;
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

        return "<style>{$base}@supports selector(:has(a)){{$rules}}</style>";
    }
}

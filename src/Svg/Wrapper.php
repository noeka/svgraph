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
 */
final class Wrapper
{
    /** @var list<string|Tag> */
    private array $svgChildren = [];

    /** @var list<Label> */
    private array $labels = [];

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
        $div->append($svg);

        if ($this->labels !== []) {
            $labelStyle = sprintf(
                'position:absolute;inset:0;pointer-events:none;font-family:%s;font-size:%s;color:%s;line-height:1;',
                $this->theme->fontFamily,
                $this->theme->fontSize,
                $this->theme->textColor,
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

        return (string) $div;
    }
}

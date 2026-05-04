# svgraph documentation

Server-side SVG charts for PHP. No JavaScript, no canvas, no build step.

## Getting started

- [Getting started](getting-started.md) — install, render your first chart, output it from a controller or template
- [Data formats](data-formats.md) — every shape the package accepts (lists, tuples, maps, `Point`/`Series`/`Slice`/`Link`)

## Chart reference

Every chart page lists every option, with examples and rendered output.

- [Sparkline](charts/sparkline.md) — compact inline trend behind a metric
- [Line / area](charts/line.md) — lines, smoothed curves, filled areas, multi-series
- [Bar](charts/bar.md) — vertical, horizontal, grouped, stacked, rainbow
- [Pie](charts/pie.md) — pie slices with legends, gaps, custom rotation
- [Donut](charts/donut.md) — donut variant of pie with configurable thickness
- [Progress](charts/progress.md) — single value-versus-target progress bar

## Cross-cutting topics

- [Theming](theming.md) — built-in themes, custom palettes, all theme tokens
- [Animations](animations.md) — opt-in CSS entrance animations, reduced-motion handling
- [Accessibility](accessibility.md) — keyboard navigation, native tooltips, link safety
- [CSS customization](css-customization.md) — `.series-{N}` hooks and `--svgraph-*` custom properties
- [Recipes](recipes.md) — Blade, Twig, email-safe SVG, inline embedding, caching

## Regenerating example images

The SVG files in [`docs/images/`](images/) are generated from the PHP scripts
in [`examples/`](../examples/). To rebuild them after a code change:

```bash
composer docs:images
```

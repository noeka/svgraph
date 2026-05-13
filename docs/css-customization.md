# CSS customization

svgraph emits a deterministic class structure plus CSS custom properties
on the wrapper element, so almost everything visual is overridable from
your own stylesheet without touching PHP.

## Wrapper class

The outer element is a `<div class="svgraph svgraph--{variant}">`,
where `{variant}` is one of `line`, `sparkline`, `bar`, `pie`, `donut`,
or `progress`. Bar charts in horizontal mode also receive a
`svgraph--bar-h` class when animated.

You can attach extra classes via `->cssClass('your-class')`.

```php
Chart::line($data)->cssClass('dashboard-trend');
```

## Per-series class hooks

Every shape rendered for a series carries a `series-{N}` class, where
`N` is the zero-based series index. This lets you target individual
series from CSS even when colors are theme-driven.

```css
.dashboard-trend .series-0 { stroke-dasharray: 4 2; }
.dashboard-trend .series-1 { opacity: 0.7; }
```

## CSS custom properties

The wrapper element exposes the following custom properties. All of
them have defaults set on `.svgraph`, and all can be overridden either
via `Theme::with*()` or directly in your CSS.

| Property | Default | What it does |
|----------|---------|--------------|
| `--svgraph-tt-bg` | `#1f2937` | Tooltip panel background. |
| `--svgraph-tt-fg` | `#f9fafb` | Tooltip text color. |
| `--svgraph-tt-r` | `0.25rem` | Tooltip corner radius. |
| `--svgraph-hover-brightness` | `1.2` | Number passed to `filter: brightness()` on hover/focus. |
| `--svgraph-hover-stroke-width` | `1.5` | Extra SVG stroke width added on hover. |
| `--svgraph-pie-pop-distance` | `3px` | How far pie/donut slices pop outward on hover. |
| `--svgraph-anim-dur` | `0.6s` | Entrance-animation duration. |
| `--svgraph-anim-ease` | `ease-out` | Entrance-animation easing function. |

```css
.svgraph {
    --svgraph-tt-bg: #0f172a;
    --svgraph-pie-pop-distance: 6px;
    --svgraph-anim-dur: 0.4s;
}
```

## Hover and focus highlighting

Highlight rules are pre-baked into the inline `<style>` block:

- Lines and bars get `filter: brightness(var(--svgraph-hover-brightness))`
  on `:hover` and `:focus`.
- Lines additionally get `stroke-width` bumped by
  `var(--svgraph-hover-stroke-width)`.
- Pie/donut slices translate outward by
  `var(--svgraph-pie-pop-distance)` along their slice midpoint vector
  (computed at render time and passed in via `--pop-x` / `--pop-y`).

You can replace any of those by writing more-specific rules — your
selectors win because they're loaded from your stylesheet (after the
chart's inline `<style>`).

## Trend overlays

Line charts with `Series::withTrendLine()` emit one extra
`<path class="svgraph-trend series-{N}">` per trended series, drawn
on top of the raw data. The trend's `svgraph-trend` class comes first
so it isn't picked up by the `path[class^="series-"]` hover rules,
keeping the overlay visually static while still letting legend toggles
hide it (they match the `.series-{N}` class by name, not prefix).

Override the overlay's defaults from your stylesheet — for example to
darken the dash or swap to a solid line:

```css
.svgraph-trend {
    stroke-dasharray: 0;     /* solid */
    opacity: 0.8;
    stroke-width: 1.5;
}
```

## Error bars and confidence bands

`Series::withErrorBars()` and `Series::withConfidenceBand()` emit one
extra path per series, sharing the `series-{N}` class so legend toggles
hide them alongside the data:

- `<path class="svgraph-errorbars series-{N}">` — I-bars drawn as a
  single concatenated path per series (one M…L segment per point + two
  caps).
- `<path class="svgraph-band series-{N}">` — the filled polygon between
  the polyline of lows and the polyline of highs.

Like the trend overlay, both class names come first so the
`path[class^="series-"]` hover rule doesn't fire on them. Restyle from
your own CSS when you want a different look:

```css
.svgraph-errorbars { stroke-width: 1.5; }
.svgraph-band      { fill-opacity: 0.3; }
```

## Line-chart crosshair

When `->crosshair()` is enabled on a line chart, the markup gains:

- `<rect class="svgraph-x-hit" data-x="N">` — one transparent column-wide
  hit rect per data point. Catches the pointer for the column-hover effect.
- `<line class="svgraph-crosshair" data-x="N">` — one dashed vertical
  guide per column, hidden until its column is hovered or focused.
- A `data-x="N"` attribute on every marker `<g>` and tooltip `<div>`,
  shared across all series for that column.

Override the guide line's appearance from your stylesheet:

```css
.svgraph-crosshair {
    stroke: #cbd5e1;
    stroke-dasharray: 0; /* solid line */
}
```

The activation rules use `:has(...)`, so the column hover degrades to
nothing on browsers without `:has` support — the chart still renders.

## Disabling the inline style block

There is no API to suppress the inline `<style>` block. Its rules are
scoped to `.svgraph` and use minimal specificity, so site-level CSS
overrides Just Work without `!important`. If you really need to strip
it (e.g. for a CSS-purist build pipeline), post-process the rendered
string with a regex — but most users should not need to.

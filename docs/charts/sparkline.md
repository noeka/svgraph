# Sparkline

A compact inline trend chart, intended to sit behind a single metric
value. No axes, grid, or labels by default. Sparklines extend
`LineChart`, so every line option is also available — but the visual
defaults (4:1 aspect, fill enabled, no axes) are tuned for inline use.

![Sparkline hero](../images/sparkline-hero.svg)

## Quickstart

```php
use Noeka\Svgraph\Chart;

echo Chart::sparkline([240, 312, 280, 410, 495, 462, 530])
    ->stroke('#3b82f6')
    ->fillBelow('#3b82f6', 0.18);
```

## Accepted data

Sparkline accepts every shape `LineChart` does — see
[Data formats](../data-formats.md). For inline use you usually want a
plain list of numbers (no labels) since labels are not rendered.

## Options

Sparkline shares all options with `LineChart`. The most useful are:

| Method | Default | Description |
|--------|---------|-------------|
| `->stroke(color, ?width)` | theme stroke | Line color and optional stroke width. |
| `->strokeWidth(float)` | theme value | Stroke width (in viewBox units). |
| `->fillBelow(?color, opacity = 0.15)` | enabled, 0.15 | Shade the area under the line. Pass `null` for color to inherit from stroke. |
| `->smooth(bool = true)` | `false` | Smooth the line with cubic Bezier curves. |
| `->axes(bool = true)` | `false` | Show axis lines and tick labels (rare on a sparkline). |
| `->grid(bool = true)` | `false` | Show horizontal grid lines. |
| `->points(bool = true)` | `false` | Draw a dot at each data point. |
| `->ticks(int)` | `5` | Number of y-axis ticks (only used when axes are on; clamped to ≥ 2). |
| `->aspect(float)` | `4.0` | Width-to-height ratio. |
| `->cssClass(?string)` | `null` | Extra class to append to the wrapper. |
| `->theme(Theme)` | `Theme::default()` | Theme used for colors, typography, hover. |
| `->animate(bool = true)` | `false` | Draw-on entrance animation. |

## Filled smoothed sparkline

```php
Chart::sparkline([14, 22, 18, 27, 31, 24, 33, 41, 36, 48])
    ->smooth()
    ->stroke('#10b981')
    ->fillBelow('#10b981', 0.25);
```

![Sparkline with fill](../images/sparkline-fill.svg)

## Notes

- The default 4:1 aspect ratio is intentionally flat for inline use.
  For a taller summary chart, use `->aspect(2.5)` or switch to
  [`LineChart`](line.md).
- Fill is enabled by default at `opacity: 0.15`; call
  `->fillBelow(null, 0.0)` if you only want the line.
- Multi-series is supported (the parent class accepts `addSeries()`),
  but most uses are single-series.

## Related

- [Line chart](line.md) — full-size variant with axes, multi-series, etc.
- [Theming](../theming.md)

# Line / area

Line charts plot one or more series across a shared x-axis, with optional
smoothing, area fills, axes, grid, and per-point markers.

![Line chart hero](../images/line-hero.svg)

## Quickstart

```php
use Noeka\Svgraph\Chart;

echo Chart::line([
    ['Mon', 12], ['Tue', 27], ['Wed', 18],
    ['Thu', 41], ['Fri', 33], ['Sat', 52],
])->axes()->grid()->stroke('#3b82f6');
```

![Basic line chart](../images/line-basic.svg)

## Accepted data

Line charts accept every shape from [Data formats](../data-formats.md):
plain lists, `[label, value]` tuples, label=>value maps, and `Point`
objects. Multi-series via [`addSeries()`](#multi-series).

## Options

| Method | Default | Description |
|--------|---------|-------------|
| `->data($data)` | `[]` | Set the primary series. Replaces any existing data. |
| `->series($data)` | `[]` | Alias of `data()`. |
| `->addSeries(Series)` | — | Append a series for multi-series charts. |
| `->stroke(color, ?width)` | theme stroke | Line color (and optional width) for series 0. |
| `->strokeWidth(float)` | theme value | Stroke width (viewBox units). |
| `->fillBelow(?color, opacity = 0.15)` | off | Shade the area under the line. Pass `null` color to use the stroke color. |
| `->smooth(bool = true)` | `false` | Cubic-Bezier smoothing. |
| `->axes(bool = true)` | `false` | Show y/x axis lines and tick labels. |
| `->grid(bool = true)` | `false` | Show horizontal grid lines. |
| `->points(bool = true)` | `false` | Render a marker dot at each data point. |
| `->crosshair(bool = true)` | `false` | Hover crosshair: vertical guide + multi-series tooltip on the nearest x. |
| `->ticks(int)` | `5` | Number of y-axis ticks (clamped to ≥ 2). |
| `->aspect(float)` | `2.5` | Width-to-height ratio. |
| `->cssClass(?string)` | `null` | Extra class on the wrapper. |
| `->theme(Theme)` | `Theme::default()` | Colors, typography, hover styling. |
| `->animate(bool = true)` | `false` | Draw-on entrance animation. |

## Smooth + points

Add markers at each data point and smooth the line with cubic Bezier
curves.

```php
Chart::line([
    ['Mon', 12], ['Tue', 27], ['Wed', 18],
    ['Thu', 41], ['Fri', 33], ['Sat', 52],
])->axes()->grid()->smooth()->points()->stroke('#3b82f6');
```

![Smooth line with points](../images/line-smooth-points.svg)

## Filled area

`fillBelow()` shades the area between the line and the chart bottom.
Combine with `smooth()` for a classic area chart.

```php
Chart::line([
    ['Jan', 120], ['Feb', 145], ['Mar', 132],
    ['Apr', 178], ['May', 196], ['Jun', 224],
])->axes()->grid()->smooth()->fillBelow('#8b5cf6', 0.2)->stroke('#8b5cf6');
```

![Filled area chart](../images/line-fill-below.svg)

## Multi-series

Append additional series with `addSeries()`. Each series carries its
own optional name and color, and the y-axis auto-extends to fit them
all.

```php
use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;

Chart::line(['Jan' => 12, 'Feb' => 27, 'Mar' => 18, 'Apr' => 33, 'May' => 41])
    ->addSeries(Series::of('Costs', ['Jan' => 6, 'Feb' => 14, 'Mar' => 9, 'Apr' => 18, 'May' => 22], '#ef4444'))
    ->axes()->grid()->points()->smooth();
```

![Multi-series line chart](../images/line-multi-series.svg)

Tooltips on multi-series charts prefix the series name, e.g.
`Costs — Mar: 9`.

## Crosshair

Opt in with `->crosshair()` to add a hover-activated vertical guide. Moving
the pointer anywhere along the chart snaps to the nearest x, reveals a
dashed guide line, and opens every series' tooltip stacked at that
column.

```php
use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;

Chart::line(['Mon' => 12, 'Tue' => 27, 'Wed' => 18, 'Thu' => 41, 'Fri' => 33, 'Sat' => 52, 'Sun' => 38])
    ->addSeries(Series::of('Costs', ['Mon' => 6, 'Tue' => 14, 'Wed' => 9, 'Thu' => 22, 'Fri' => 18, 'Sat' => 30, 'Sun' => 21], '#ef4444'))
    ->axes()->grid()->points()->crosshair();
```

![Line chart with hover crosshair](../images/line-crosshair.svg)

The image above is a static rendering — open it in a browser via the live
example to see the column hover effect.

Notes:

- Pure CSS; works with the same `:has(...)` support that powers tooltips.
  Browsers without `:has()` show the chart without the column hover (the
  rest of the chart still works).
- Implies marker emission. If `->points()` isn't also enabled, markers
  stay invisible until a column is hovered, then fade in for that column
  only.
- Keyboard users get the same effect: tabbing onto a marker opens its
  column.
- Sparkline charts inherit this method but axes/grid normally aren't on
  sparklines, so the experience is best on full line charts.

## Color resolution

For a given series, the package picks a color in this order:

1. The `Series` instance's `color` (set via `Series::of(...,$color)`).
2. For series 0 only: the chart-level `->stroke(color, ?width)`.
3. The theme palette at `index % count(palette)`.

This means a single-series chart's `->stroke('#hex')` call still works
ergonomically, while explicit per-series colors win for multi-series.

## Notes

- Empty data renders an empty wrapper (no error).
- Non-finite values (`NAN`, `±INF`) are silently dropped — see
  [Data formats](../data-formats.md).
- The y-axis adds 10% padding above and below the data domain so lines
  don't run flush with the plot edges.

## Related

- [Sparkline](sparkline.md) — compact inline variant
- [Theming](../theming.md)
- [Animations](../animations.md)
- [Accessibility](../accessibility.md) (for clickable points via `Link`)

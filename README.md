# svgraph

JavaScript-free SVG chart rendering for PHP. Sparkline, line/area, bar, pie/donut, and progress charts as static markup — no canvas, no JS, no build step.

## Requirements

- PHP 8.3+

## Installation

```bash
composer require noeka/svgraph
```

## Quick start

```php
use Noeka\Svgraph\Chart;

echo Chart::line([
    ['Mon', 12], ['Tue', 27], ['Wed', 18], ['Thu', 41],
])->axes()->grid()->smooth()->stroke('#3b82f6');
```

Each chart is a `Stringable` — cast it with `(string)` or drop it directly into a template with `<?= ?>`.

---

## Chart types

### Sparkline

A compact inline chart for use behind a metric value. No axes or labels by default.

```php
Chart::sparkline([240, 312, 280, 410, 495])
    ->stroke('#3b82f6')
    ->fillBelow('#3b82f6', 0.18)
```

### Line / area

```php
// Line with axes, grid, smooth curve, and data-point dots
Chart::line($data)
    ->axes()
    ->grid()
    ->smooth()
    ->points()
    ->stroke('#3b82f6')

// Filled area
Chart::line($data)
    ->axes()
    ->grid()
    ->smooth()
    ->fillBelow('#8b5cf6', 0.2)
    ->stroke('#8b5cf6')
```

**Data format:** an array of `[label, value]` pairs, or a plain list of numeric values.

| Method | Description |
|--------|-------------|
| `->axes()` | Show axis lines and tick labels |
| `->grid()` | Show horizontal grid lines |
| `->smooth()` | Smooth the line with cubic Bezier curves |
| `->points()` | Draw a dot at each data point |
| `->stroke(color, ?width)` | Line color and optional width |
| `->fillBelow(?color, opacity)` | Shade the area under the line |
| `->ticks(int)` | Number of y-axis ticks (default 5) |
| `->aspect(float)` | Width-to-height ratio (default 2.5) |

### Bar

```php
// Vertical with axes and grid
Chart::bar(['Jan' => 120, 'Feb' => 180, 'Mar' => 90])
    ->axes()
    ->grid()
    ->rounded(2)
    ->color('#10b981')

// Horizontal with per-bar colors
Chart::bar($data)->horizontal()->rainbow()->rounded(1)
```

| Method | Description |
|--------|-------------|
| `->horizontal()` | Render bars horizontally |
| `->color(string)` | Single fill color for all bars |
| `->rainbow()` | Color each bar from the theme palette |
| `->rounded(float)` | Corner radius in viewBox units |
| `->gap(float)` | Gap between bars as a fraction of slot width (default 0.2) |
| `->axes()` | Show axis line and tick labels |
| `->grid()` | Show grid lines |

### Multi-series

Line and bar charts accept multiple series. Each series carries its own
optional name and colour, and the y-axis auto-extends to fit them all.

```php
use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;

// Two-line chart, distinct colours, hover tooltips include the series name.
Chart::line(['Jan' => 12, 'Feb' => 27, 'Mar' => 18])
    ->addSeries(Series::of('Costs', ['Jan' => 6, 'Feb' => 14, 'Mar' => 9], '#ef4444'))
    ->axes()->grid()->points()
```

For bar charts, two extra modes pick how multiple series share each x-tick:

```php
// Grouped (default for multi-series): bars sit side-by-side per slot.
Chart::bar(['Q1' => 10, 'Q2' => 20])
    ->addSeries(Series::of('Costs', ['Q1' => 5, 'Q2' => 8]))
    ->grouped()
    ->axes()

// Stacked: bars stack atop each other; y-axis grows to the cumulative sum.
Chart::bar(['Q1' => 10, 'Q2' => 20])
    ->addSeries(Series::of('Costs', ['Q1' => 5, 'Q2' => 8]))
    ->stacked()
    ->axes()
```

Each rendered shape carries a `.series-{N}` class so external CSS can target
individual series.

### Pie

```php
Chart::pie(['Stripe' => 1240, 'PayPal' => 432, 'Bank' => 312])
    ->legend()
```

### Donut

```php
Chart::donut($data)
    ->thickness(0.5)   // inner/outer radius ratio: 0 = pie, 1 = hairline
    ->gap(1.5)         // gap between slices in degrees
    ->legend()
```

| Method | Description |
|--------|-------------|
| `->thickness(float)` | Inner radius as a fraction of outer (0 = solid pie, 1 = hairline ring) |
| `->gap(float)` | Degrees of padding between slices |
| `->startAngle(float)` | Rotation offset in degrees clockwise from 12 o'clock |
| `->legend()` | Render a colour-swatch legend below the chart |

### Progress

```php
Chart::progress(value: 7400, target: 10000)
    ->color('#f59e0b')
    ->showValue()
```

| Method | Description |
|--------|-------------|
| `->color(string)` | Fill color |
| `->trackColor(string)` | Background track color |
| `->rounded(float)` | Corner radius as % of bar height (default 50 = fully rounded) |
| `->showValue(?label)` | Show percentage (or custom label) beside the bar |
| `->aspect(float)` | Width-to-height ratio (default 20) |

---

## Entrance animations

All chart types support opt-in CSS entrance animations via `->animate()`. Animations use pure `@keyframes` — no JavaScript required — and are always wrapped in `@media (prefers-reduced-motion: no-preference)` so users who request reduced motion always receive a static chart.

```php
// Line: the stroke draws on from left to right
Chart::line($data)->axes()->grid()->smooth()->stroke('#3b82f6')->animate()

// Bar: bars grow from the baseline
Chart::bar(['Jan' => 120, 'Feb' => 180, 'Mar' => 90])->axes()->color('#10b981')->animate()

// Horizontal bar: bars extend from the axis
Chart::bar($data)->horizontal()->rainbow()->animate()

// Pie/donut: slices sweep in using the stroke-circle technique
Chart::pie(['Stripe' => 1240, 'PayPal' => 432, 'Bank' => 312])->legend()->animate()
Chart::donut($data)->thickness(0.5)->animate()
```

**Animation details by chart type:**

| Chart | Technique | Notes |
|-------|-----------|-------|
| Line / Sparkline | `stroke-dashoffset` draw-on using `pathLength="1"` | Full line draws from left to right |
| Bar (vertical) | `scaleY` from baseline (`transform-origin` set per bar) | Negative bars grow from top |
| Bar (horizontal) | `scaleX` from axis | Negative bars grow from right |
| Pie / Donut | `stroke-dasharray` sweep on stroke-circles | Slices stagger by 80 ms each |

Duration and easing are theme-tokenised and can be customised via `Theme::withAnimation()`:

```php
Chart::pie($data)->animate()->theme(
    Theme::default()->withAnimation('0.4s', 'cubic-bezier(0.4,0,0.2,1)')
)
```

| Method | Description |
|--------|-------------|
| `->animate(bool)` | Enable (or disable) entrance animations (default off) |
| `Theme::withAnimation(duration, easing)` | Override `--svgraph-anim-dur` and `--svgraph-anim-ease` |

The CSS custom properties `--svgraph-anim-dur` and `--svgraph-anim-ease` are emitted on the wrapper element and can also be overridden from your own stylesheet.

---

## Theming

All charts use `Theme::default()` out of the box. Pass a different theme or build your own:

```php
use Noeka\Svgraph\Theme;

// Built-in dark theme
Chart::line($data)->theme(Theme::dark())

// Custom palette on top of the default theme
Chart::pie($data)->theme(
    Theme::default()->withPalette('#6366f1', '#f43f5e', '#0ea5e9', '#84cc16')
)

// Fully custom theme
$theme = new Theme(
    palette:     ['#6366f1', '#f43f5e', '#0ea5e9'],
    stroke:      '#6366f1',
    strokeWidth: 2.0,
    fill:        '#6366f1',
    textColor:   '#1e293b',
    fontFamily:  'inherit',
    fontSize:    '0.75rem',
    gridColor:   '#e2e8f0',
    axisColor:   '#94a3b8',
    trackColor:  '#e2e8f0',
);
```

---

## License

MIT — see [LICENSE](LICENSE).

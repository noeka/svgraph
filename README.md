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

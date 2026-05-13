# Data formats

Every chart accepts the same data shapes for series-style data
(`LineChart`, `SparklineChart`, `BarChart`) — and a slightly different
set for partition charts (`PieChart`, `DonutChart`). `ProgressChart` is
configured with two scalars (`value` and `target`) and accepts no series.

## Series-style data (line, sparkline, bar)

The `data()` factory and `Chart::line()`/`Chart::bar()`/`Chart::sparkline()`
helpers all delegate to `Series::from()`, which accepts:

```php
// 1. List of values (no labels)
[10, 24, 18, 33]

// 2. Tuples of [label, value]
[['Mon', 10], ['Tue', 24], ['Wed', 18]]

// 3. Tuples of [label, value, Link] — links make the point clickable
[['Mon', 10, new Link('/days/mon')]]

// 4. Label => value map
['Mon' => 10, 'Tue' => 24, 'Wed' => 18]

// 5. List of Point objects (full control)
[new Point(10, 'Mon'), new Point(24, 'Tue', new Link('/days/tue'))]

// 6. Tuples of [DateTimeImmutable, value] — drives a time x-axis when
//    paired with LineChart::timeAxis(). The datetime is parked on
//    Point::$time and used to position the point in time.
[
    [new DateTimeImmutable('2026-01-01'), 10],
    [new DateTimeImmutable('2026-02-01'), 24],
]

// 7. Tuples of [label, value, low, high] — attaches an uncertainty range
//    to each point. Surfaces as I-bars or a confidence band when the
//    series opts in with `withErrorBars()` / `withConfidenceBand()`.
[['Mon', 10, 8, 12], ['Tue', 24, 20, 28]]

// 8. Tuples of [label, value, Link, low, high] — links + range combined.
[['Mon', 10, new Link('/days/mon'), 8, 12]]
```

Non-finite values (`NAN`, `±INF`) are silently dropped — they would
otherwise propagate through the scale calculations and produce a flat
chart of zeros.

See [Line chart › Time / date axis](charts/line.md#time--date-axis) for the
full `timeAxis()` API.

### Multi-series

To add more than one series to a line or bar chart, build a `Series` and
append it with `addSeries()`:

```php
use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;

Chart::line(['Jan' => 12, 'Feb' => 27, 'Mar' => 18])
    ->addSeries(Series::of('Costs', ['Jan' => 6, 'Feb' => 14, 'Mar' => 9], '#ef4444'))
    ->addSeries(Series::of('Tax',   ['Jan' => 2, 'Feb' => 4,  'Mar' => 3], '#f59e0b'))
    ->axes()->grid();
```

`Series::of(name, data, color)` is shorthand for
`new Series(Series::from($data)->points, $name, $color)`. The `name` is
used as a tooltip prefix; the `color` overrides the per-series palette
colour.

### Assigning a series to a secondary Y axis

For dual-axis line charts, mark a series for the right-hand axis with
`->onAxis('right')` (or `Axis::Right`). Series default to the left axis;
the chart's `->secondaryAxis()` enables rendering of the right-side
ticks. See [Line chart › Dual Y axis](charts/line.md#dual-y-axis) for
the full reference.

```php
use Noeka\Svgraph\Data\Series;

Series::of('Conversion %', ['Jan' => 1.4, 'Feb' => 1.8])->onAxis('right');
```

## Partition data (pie, donut)

Pie and donut charts use `Slice::listFrom()`, which accepts:

```php
// 1. Label => value map
['Stripe' => 1240, 'PayPal' => 432, 'Bank' => 312]

// 2. Tuples of [label, value]
[['Stripe', 1240], ['PayPal', 432]]

// 3. Tuples of [label, value, color]
[['Stripe', 1240, '#10b981'], ['PayPal', 432, '#3b82f6']]

// 4. Tuples of [label, value, color, Link]
[['Stripe', 1240, '#10b981', new Link('/stripe')]]

// 5. List of Slice objects
[new Slice('Stripe', 1240, '#10b981'), new Slice('PayPal', 432)]
```

Two safety behaviours to be aware of:

- Tuples shorter than two elements throw `InvalidArgumentException` —
  this surfaces typos at construction time rather than producing a
  silent zero-value slice.
- Non-finite values are dropped (same rule as series data).

## `Point`, `Series`, `Slice`, `Link`

Direct construction gives you the most control:

```php
use Noeka\Svgraph\Data\{Point, Series, Slice, Link};

new Point(value: 42.0, label: 'Mon', link: new Link('/days/mon'));
new Point(value: 42.0, time: new DateTimeImmutable('2026-01-15'));
new Point(value: 42.0, label: 'Mon', low: 38.0, high: 46.0); // confidence range
new Series(points: [new Point(10, 'Mon'), new Point(20, 'Tue')], name: 'Sales', color: '#3b82f6');
new Slice(label: 'Stripe', value: 1240, color: '#10b981', link: new Link('/stripe'));
```

### Point ranges

Both `low` and `high` are optional — setting one without the other has no
effect. The chart's Y axis automatically extends to include the range,
and `low` / `high` are surfaced in tooltips and the screen-reader
[data table](accessibility.md) regardless of whether an overlay is
rendered. See [Line chart › Error bars / confidence bands](charts/line.md#error-bars--confidence-bands)
for opting into the visual overlay.

### `Link` safety

`Link` rejects `javascript:` URLs at construction. When `target` is
`_blank`, `rel` defaults to `noopener noreferrer`. You can override by
passing an explicit `rel`.

```php
new Link('/internal');                       // safe internal link
new Link('https://x.test', target: '_blank'); // rel auto-set to "noopener noreferrer"
new Link('javascript:alert(1)');             // throws InvalidArgumentException
```

When a point has a `Link`, the rendered marker is wrapped in an SVG `<a>`
element — natively keyboard-activatable via Enter, no JS required.

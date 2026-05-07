# Accessibility

svgraph aims to render charts that work without JavaScript and remain
usable with assistive technologies, keyboards, and reduced-motion settings.

## Chart-level labelling

Every chart's root `<svg>` carries `role="img"` plus an `aria-labelledby`
pointing at a `<title>` child and an `aria-describedby` pointing at a
`<desc>` child. Assistive technologies read these in order, so users hear
"Line chart, Line chart with 1 series of 4 points. Range: 10 to 35."
before encountering the data points themselves.

```html
<svg role="img" aria-labelledby="svgraph-1-title" aria-describedby="svgraph-1-desc" …>
  <title id="svgraph-1-title">Line chart</title>
  <desc id="svgraph-1-desc">Line chart with 1 series of 4 points. Range: 10 to 35.</desc>
  …
</svg>
```

Override either string per chart:

```php
Chart::line($revenue)
    ->title('Quarterly revenue, 2026')
    ->description('Revenue (in $k) for Q1–Q3 2026, post-launch.');
```

When you call neither, svgraph derives a default summary from the data
(point count, series count, value range — or "no data" for empty inputs).

## Screen-reader data table

Alongside every non-empty chart, svgraph appends a visually-hidden
`<table class="svgraph-sr-only">` that lists the underlying values with
`<th scope="col">` headers and `<th scope="row">` row labels. Sighted
users see the chart; screen-reader and keyboard-table-mode users get a
fully navigable representation of the same data.

```html
<table class="svgraph-sr-only">
  <thead><tr><th scope="col">Label</th><th scope="col">Series 1</th></tr></thead>
  <tbody>
    <tr><th scope="row">Mon</th><td>12</td></tr>
    <tr><th scope="row">Tue</th><td>27</td></tr>
    …
  </tbody>
</table>
```

The hiding rule (`position:absolute; clip:rect(0,0,0,0)`) keeps the table
in the accessibility tree without affecting layout.

## Native tooltips

Every interactive element carries an SVG `<title>` child. Browsers and
screen readers expose this as the element's accessible name and visual
tooltip — no hover script required.

```html
<rect ...><title>Mon: 12</title></rect>
```

The tooltip text combines the series name (when set) with the point
label and value, e.g. `Costs — Mon: 12`.

## Keyboard navigation

Data points (line markers, bars, pie slices, progress bars) carry
`tabindex="0"` so they participate in the document's tab order. Hover
and focus styles are wired together via `:hover, :focus, :focus-within`
so keyboard users see the same emphasis as mouse users.

Focus order follows the natural document order — series 0 first, then
series 1, etc.

## Links and `<a>` activation

When a `Point` or `Slice` has a `Link`, the visual element is wrapped
in an SVG `<a href="...">`. SVG `<a>` is natively keyboard-activatable
via Enter, so no extra `tabindex` is needed.

```php
use Noeka\Svgraph\Data\{Link, Point};

Chart::line([
    new Point(12, 'Mon', new Link('/days/mon')),
    new Point(27, 'Tue', new Link('/days/tue')),
]);
```

### `javascript:` URLs are blocked

`Link` rejects `javascript:` URLs at construction with an
`InvalidArgumentException`. There is no way to render a chart that
ships a `javascript:` href — even if user input is forwarded into the
chart unchanged.

### `target="_blank"` defaults to `noopener noreferrer`

```php
new Link('https://example.test', target: '_blank');
// rel is automatically "noopener noreferrer"
```

Override with an explicit `rel:` argument if you need different values.

## Reduced motion

Entrance animations only run when the user's OS does not request
reduced motion. The animation rules are wrapped in
`@media (prefers-reduced-motion: no-preference)`, so users who set
"reduce motion" in their accessibility preferences see the chart in its
final state immediately. See [Animations](animations.md) for details.

## Color contrast

Pie/donut legends, axis labels, and progress text use `theme.textColor`.
The default theme's `#374151` clears WCAG AA contrast against white;
the dark theme's `#e5e7eb` clears AA against `#0f172a`. If you build a
custom theme, verify your `textColor` against the surface you embed
the chart on:

| Token              | Default theme | Dark theme | WCAG AA target              |
|--------------------|---------------|------------|-----------------------------|
| `textColor`        | `#374151`     | `#e5e7eb`  | 4.5:1 against background    |
| `axisColor`        | `#9ca3af`     | `#6b7280`  | 3:1 against background      |
| `gridColor`        | `#e5e7eb`     | `#374151`  | decorative — no minimum     |
| `tooltipBackground`| `#1f2937`     | `#111827`  | 4.5:1 with `tooltipTextColor` |
| `tooltipTextColor` | `#f9fafb`     | `#f3f4f6`  | 4.5:1 with `tooltipBackground` |

Run any custom palette through a contrast checker (e.g.
[WebAIM](https://webaim.org/resources/contrastchecker/)) before
shipping. The series colors themselves only need to be distinguishable
from each other and from the background — they are not text — but
keeping a 3:1 ratio against the page background helps low-vision users
trace each line or bar.

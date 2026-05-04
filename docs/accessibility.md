# Accessibility

svgraph aims to render charts that work without JavaScript and remain
usable with assistive technologies, keyboards, and reduced-motion settings.

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

## Decorative SVG vs interactive content

The wrapper `<svg>` carries `aria-hidden="true"` and `focusable="false"`
because the meaningful content lives in the interactive elements
inside. Each interactive element exposes its own accessible name via
`<title>`.

## Color contrast

Pie/donut legends, axis labels, and progress text use `theme.textColor`.
The default theme's `#374151` clears WCAG AA contrast against white;
the dark theme's `#e5e7eb` clears AA against `#0f172a`. If you build a
custom theme, verify your `textColor` against the surface you embed
the chart on.

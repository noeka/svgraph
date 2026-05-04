# Animations

All chart types support opt-in CSS entrance animations via `->animate()`.
Animations use pure `@keyframes` — no JavaScript — and are wrapped in
`@media (prefers-reduced-motion: no-preference)` so users who request
reduced motion always receive a static chart.

```php
Chart::line($data)->axes()->grid()->smooth()->animate();
```

## Per-chart technique

| Chart | Technique | Notes |
|-------|-----------|-------|
| Line / Sparkline | `stroke-dashoffset` draw-on using `pathLength="1"` | Line draws from left to right. |
| Bar (vertical) | `scaleY` from baseline | `transform-origin` is set per bar; negative bars grow from top. |
| Bar (horizontal) | `scaleX` from axis | Negative bars grow from right. |
| Pie / Donut | `stroke-dasharray` sweep on stroke-circles | Slices stagger by 80 ms each. |
| Progress | (no entrance animation) | Track + bar render statically. |

## Customising timing

Duration and easing are theme tokens — override via `Theme::withAnimation()`:

```php
Chart::pie($data)->animate()->theme(
    Theme::default()->withAnimation('0.4s', 'cubic-bezier(0.4,0,0.2,1)'),
);
```

You can also override the underlying CSS custom properties from your own
stylesheet:

```css
.svgraph {
    --svgraph-anim-dur: 0.4s;
    --svgraph-anim-ease: cubic-bezier(0.4, 0, 0.2, 1);
}
```

## Reduced motion

Every animation block lives inside `@media (prefers-reduced-motion: no-preference)`.
If the user has requested reduced motion at the OS level, the chart
appears static immediately with no transforms applied. There is nothing
to configure on the package side — the behaviour is built in.

## Disabling animation

Animation is opt-in and off by default. To disable an animation that
was enabled earlier in a chain (e.g. by a default factory), pass `false`:

```php
$chart->animate(false);
```

## Bar-stagger timing

When a bar chart is animated, bars stagger in by 80 ms each in render
order. The stagger delay is hard-coded and not currently configurable
via the theme; override `--svgraph-bar-delay` on individual `<rect>`
elements with your own CSS if you need a different curve.

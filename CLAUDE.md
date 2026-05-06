# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project values (non-negotiable)

These three rules govern almost every decision — violating any of them is grounds for rejecting a change:

1. **Zero runtime dependencies.** `composer require noeka/svgraph` may only pull in PHP itself. New entries in `require` need a strong justification; default to inlining or rejecting. `require-dev` is fair game.
2. **PHP 8.3+ only.** Use modern idioms: readonly classes, constructor property promotion, enums, first-class callables.
3. **No JavaScript.** Charts render as static SVG markup. Hover, focus, animation, and legend toggles are CSS-only. Nothing produced by this package may require a JS runtime.

## Common commands

```bash
composer check              # Gating bundle: lint + cs + test (matches CI)
composer test               # PHPUnit
composer lint               # PHPStan level 10 (src + tests, no baseline)
composer cs                 # PHP-CS-Fixer check
composer cs:fix             # PHP-CS-Fixer apply
composer rector             # Rector dry-run (fails CI if any rule would change a file)
composer rector:fix         # Rector apply
composer mutate             # Infection mutation testing — local only, requires pcov/xdebug
composer docs:images        # Regenerate docs/images/*.svg from examples/

# Run a single test class:
vendor/bin/phpunit tests/Charts/ChartRenderingTest.php

# Coverage (requires pcov/xdebug); CI gates on 90% line coverage of src/:
vendor/bin/phpunit --coverage-html build/coverage
```

CI runs steps 1–5 of `composer check` plus coverage on PHP 8.3, 8.4, 8.5. Mutation testing and `docs:images` are **not** in CI.

## Architecture

### Entry point and chart hierarchy

`Noeka\Svgraph\Chart` (`src/Chart.php`) is a thin static factory — `Chart::line()`, `::sparkline()`, `::bar()`, `::pie()`, `::donut()`, `::progress()`. Each returns a concrete chart class in `src/Charts/` extending `AbstractChart`.

`AbstractChart` is `Stringable`; `__toString()` calls `render()`. Every concrete chart implements `render(): string` returning the full HTML/SVG fragment. Chart options are fluent setters (`->axes()`, `->grid()`, `->stroke()`, …) returning `static`.

Each chart instance gets a monotonically increasing `instanceId` from a static counter, exposed as `chartId()` → `svgraph-{n}`. This ID is the prefix for every internal SVG element ID and CSS selector, ensuring multiple charts on the same page do not collide. **Snapshot tests must reset this counter in `setUp()`** via reflection on `AbstractChart::$nextId`, otherwise test order leaks into snapshots.

### The Wrapper envelope

`src/Svg/Wrapper.php` composes the responsive Rich Harris-style envelope every chart returns:

- Outer `<div class="svgraph svgraph--{variant}">` with `padding-bottom:{1/aspect}%` for fluid aspect ratio.
- Inner absolutely-positioned `<svg viewBox="0 0 100 100" preserveAspectRatio="none">` — the SVG is **always** a 100×100 logical box that gets stretched. All geometry code can assume that coordinate system.
- Absolutely-positioned `<div class="svgraph__labels">` for HTML labels (which stay readable at any aspect ratio, unlike stretched `<text>`).
- A `<style>` block emitted only when the chart needs hover/tooltip/animation/crosshair/legend CSS.

The wrapper owns all interactive CSS. Chart classes register intent (`markHasSeriesElements()`, `enableAnimation()`, `enableCrosshair($cols)`, `setLegend($entries)`, `tooltip($t)`) and the wrapper emits the matching CSS rules. **Don't write per-chart CSS in chart classes** — funnel it through the wrapper so themes and CSS custom properties stay consistent.

CSS custom properties on the wrapper (`--svgraph-tt-bg`, `--svgraph-hover-brightness`, `--svgraph-anim-dur`, `--svgraph-pie-pop-distance`, …) are public API: users may override them in their own stylesheets. Theme tokens flow through `Theme` → `Css::*()` formatters → these custom properties.

### Interaction without JS

All interactive behavior relies on CSS-only patterns; preserve them when editing markup or styles:

- **Tooltips:** every interactive element gets `id="{chartId}-..."` and a sibling hidden `<div class="svgraph-tooltip" data-for="{id}">`. CSS `.svgraph:has(#id:hover) [data-for=id]{display:block}` reveals it. Wrapped in `@supports selector(:has(a))` for graceful degradation.
- **Crosshair (line charts):** every column shares a `data-x="N"` attribute across the marker `<g>`, the `.svgraph-crosshair` line, and a wide transparent `.svgraph-x-hit` rect. CSS rules generated per column activate on `:has([data-x="N"]:hover)`.
- **Legend toggles:** hidden checkboxes precede the chart and legend (sibling combinator `~`); `#{id}:not(:checked)~.svgraph__chart .series-{n}{display:none}` hides the matching series. State is page-local — no JS = no persistence. Axes do **not** rescale when a series is toggled off.
- **Linkable elements:** `AbstractChart::buildLink()` wraps an SVG element in `<a class="svgraph-linked">` when a `Link` is provided; otherwise it sets `tabindex="0"` directly on the inner element. The `<a>` is the focus carrier when present (no inner `tabindex`); inner elements only get visual styling.
- **Animations:** all entrance animations sit inside `@media (prefers-reduced-motion:no-preference)`. There is also a `@media (prefers-reduced-motion:reduce)` block that overrides the initial hidden state (e.g. `stroke-dashoffset`) so reduced-motion users see the final rendered state, not nothing.

### Subsystems

- `src/Data/` — input value objects (`Point`, `Series`, `SeriesCollection`, `Slice`, `Link`). `Series` precomputes `values`/`min`/`max`/`sum` at construction; render code reads aggregates without re-walking. `Series::from()` / `Series::of()` normalise the many accepted input shapes documented in `docs/data-formats.md`.
- `src/Geometry/` — `Viewport` (the 100×100 logical box plus padding for axis labels), `Scale` (linear value→coord), `Path` (SVG path-d builders, including the smooth-line cubic interpolation).
- `src/Svg/` — `Tag` (attribute-safe element builder, the only thing that emits markup), `Wrapper` (envelope above), `Label` (HTML labels), `Tooltip` (data carrier for tooltip emission), `Css` (theme-token formatters that validate values before injecting them into inline styles). **All HTML/SVG output goes through `Tag`** to keep escaping uniform.
- `src/Theme.php` — readonly value object holding palette + per-token strings. `Theme::default()` and `Theme::dark()` are the two builtins; `withPalette()`/`withAnimation()` etc. produce modified copies.

### Testing patterns

Tests under `tests/` mirror the `src/` namespace layout. Three styles:

1. **`assertStringContainsString` / regex** for narrow per-attribute or per-fragment checks (most of `tests/Charts/`).
2. **Snapshot assertions** (`spatie/phpunit-snapshot-assertions`) for whole-SVG shape — see `tests/Snapshots/ChartSnapshotTest.php` and `tests/Snapshots/__snapshots__/`. Snapshots are the contract for downstream output: review diffs as carefully as code. Delete a `.txt` snapshot to regenerate it on next run. **Always reset `AbstractChart::$nextId`** in `setUp()` for any snapshot test.
3. **Mutation testing** (`composer mutate`, local only) — run when changing `src/Geometry/`, `src/Data/Series.php`, `src/Svg/Wrapper.php`, or any chart class. Surviving mutators flag assertions that don't actually constrain behavior; strengthen those tests rather than ratcheting thresholds down.

Reach for snapshots when testing the shape of the whole SVG. Use `assertStringContainsString` for narrow attribute checks where surrounding markup is incidental.

### Documentation images

SVGs in `docs/images/` are generated from runnable scripts in `examples/<chart>/<feature>.php` via `examples/run.php`. Each example file `return`s a chart instance; the runner converts the responsive HTML wrapper into a standalone SVG (with native `<text>` labels instead of `<foreignObject>`) suitable for GitHub's `<img>`-based markdown rendering. Regenerate after any change that affects rendered output and commit the result alongside the code change.

## Conventions worth preserving

- Strict types, PER-CS 2.0, level 10 PHPStan, Rector clean — `composer check` enforces all four. Don't add baseline entries.
- New runtime dependencies are rejected by default (see project value #1).
- When Rector flags new code, the standard flow is `composer rector:fix && composer cs:fix && composer check`.
- Element IDs are built from `chartId()` plus an alphanumeric suffix only — no characters that need CSS escaping. Several `<style>` rules rely on this and embed the ID directly.
- `Tag::formatFloat()` and the `Css::*()` formatters validate/normalise values before they hit inline styles or attributes; route new theme tokens through them rather than concatenating strings directly.

# Recipes

Common integration patterns.

## Laravel / Blade

Charts are `Stringable`, so they drop straight into Blade with `{!! !!}`
to skip HTML escaping (the markup is the chart):

```blade
{{-- resources/views/dashboard.blade.php --}}
<div class="card">
    <h2>Weekly traffic</h2>
    {!! \Noeka\Svgraph\Chart::line($traffic)->axes()->grid() !!}
</div>
```

For a reusable component:

```blade
{{-- resources/views/components/chart.blade.php --}}
@props(['chart'])
<div {{ $attributes }}>{!! $chart !!}</div>
```

```blade
<x-chart :chart="$chart" class="card" />
```

## Twig / Symfony

```twig
{# Allow raw output — the chart markup is trusted #}
{{ chart|raw }}
```

If you build the chart in PHP and want a Twig function that constructs
it, register a function on your `Twig\Environment` that returns the
chart instance.

## Plain PHP / strings

```php
$markup = (string) Chart::pie($payments)->legend();
file_put_contents('public/chart.svg', $markup);
```

Note: the rendered output is an HTML `<div>` wrapper around an SVG
element, not a standalone SVG document. If you need a `.svg` file you
can serve directly, render only the inner `<svg>` (see "Inline SVG"
below).

## Inline SVG only (no wrapper)

The wrapper exists for responsive sizing in HTML. To produce a
standalone SVG that you can save as `.svg` or embed in PDFs, strip
the wrapper:

```php
$html = (string) Chart::line($data)->axes()->grid();
$svg  = preg_replace('#^<div[^>]*>|</div>$#', '', $html);
file_put_contents('chart.svg', $svg);
```

A cleaner, future-proof approach is to call the underlying renderer
directly — but the public API is the chart object, and the wrapper is
small (one `<div>`), so the regex above is usually fine.

## Email-safe SVG

Most modern email clients (Apple Mail, recent Outlook, Gmail web)
render inline SVG. svgraph's output is JS-free and uses no external
references, so it's compatible. Two cautions for email:

- Avoid CSS hover effects in email — the inline `<style>` rules are
  cosmetic and will simply not trigger.
- Some clients strip `<style>`. If you need deep email compatibility,
  pin colors via `->theme()` so the chart looks correct without the
  inline stylesheet.

## Caching

Rendered charts are deterministic given the same input data and
configuration. Cache the string output in your application's cache
to avoid re-rendering on every request:

```php
$svg = $cache->remember("chart:traffic:{$weekId}", 3600, function () use ($data) {
    return (string) Chart::line($data)->axes()->grid()->smooth();
});
```

## Server-side image conversion

If you need a PNG or JPEG (e.g. for an image-only context like an
older email client), pipe the SVG through ImageMagick or a headless
browser:

```bash
echo "$svg" | convert svg:- chart.png
```

The exact toolchain is out of scope for this package — but because
the output is plain SVG, anything that converts SVG works.

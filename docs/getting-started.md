# Getting started

## Requirements

- PHP 8.3 or newer

## Install

```bash
composer require noeka/svgraph
```

No JavaScript or build step is required. The package emits plain SVG markup
that browsers render natively.

## Your first chart

```php
use Noeka\Svgraph\Chart;

echo Chart::line([
    ['Mon', 12], ['Tue', 27], ['Wed', 18], ['Thu', 41],
])->axes()->grid()->smooth()->stroke('#3b82f6');
```

Every chart implements `Stringable`, so you can:

- echo it directly: `echo $chart;`
- cast it: `$markup = (string) $chart;`
- drop it into a template: `<?= $chart ?>`

## Output target

The rendered markup is a `<div>` wrapper containing an `<svg>` element plus
a small inline `<style>` block. It's safe to embed anywhere SVG is allowed
(HTML pages, MJML/email templates that allow SVG, PDFs via wkhtmltopdf, etc.).

## Where to next

- Pick a chart from the [chart reference](index.md#chart-reference) — each
  page lists every option with rendered examples.
- Learn the [accepted data shapes](data-formats.md) — lists, tuples,
  label=>value maps, or full `Point`/`Series`/`Slice` objects.
- Customize colors and typography with [themes](theming.md).

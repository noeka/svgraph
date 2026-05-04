<?php

declare(strict_types=1);

/**
 * Generate documentation images from runnable example scripts.
 *
 * Each examples/<chart>/<feature>.php file returns a chart instance.
 * This script renders every example, converts the chart's HTML output
 * (a <div> wrapper with absolute-positioned <svg> + HTML labels) into a
 * standalone SVG document with native <text> labels, and writes it to
 * docs/images/<chart>-<feature>.svg.
 *
 * Standalone SVGs are required for GitHub's <img>-based markdown image
 * rendering — HTML wrappers and <foreignObject> HTML labels don't render
 * in image contexts.
 *
 * Run via Composer:
 *   composer docs:images
 *
 * Or directly:
 *   php examples/run.php
 */
require __DIR__ . '/../vendor/autoload.php';

const RENDER_WIDTH = 800;

$root = dirname(__DIR__);
$examplesDir = $root . '/examples';
$outputDir = $root . '/docs/images';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0o755, true);
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($examplesDir));
$count = 0;

foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if ($path === __FILE__) {
        continue;
    }

    $relative = substr($path, strlen($examplesDir) + 1);
    $name = str_replace(['/', '.php'], ['-', ''], $relative);
    $output = $outputDir . '/' . $name . '.svg';

    $chart = require $path;
    if (!is_object($chart) || !method_exists($chart, 'render')) {
        fwrite(STDERR, "Skipped {$relative}: did not return a chart instance\n");
        continue;
    }

    $html = (string) $chart;
    $svg = htmlChartToStandaloneSvg($html, RENDER_WIDTH);
    file_put_contents($output, $svg);
    echo "  wrote {$name}.svg\n";
    $count++;
}

echo "\nGenerated {$count} image(s) in docs/images/\n";

/**
 * Convert the chart's HTML output into a standalone SVG document.
 *
 * The chart renders to:
 *   <div ... padding-bottom:X%>
 *     <style>...</style>
 *     <svg viewBox="0 0 100 100" preserveAspectRatio="none">...</svg>
 *     <div class="svgraph__labels" style="...">...spans...</div>
 *     <div class="svgraph-tooltip" ...>...</div>
 *   </div>
 *
 * We produce an outer <svg width="W" height="H"> sized in pixels so it
 * displays at a sensible size when embedded as a markdown image, with
 * the original chart shapes nested as a stretched inner <svg> and HTML
 * labels converted to native SVG <text> (and <rect> for legend swatches).
 */
function htmlChartToStandaloneSvg(string $html, int $width): string
{
    // Aspect ratio from the wrapper's padding-bottom.
    $aspectInverse = 0.4; // 2.5:1 default
    if (preg_match('/padding-bottom:([\d.]+)%/', $html, $m)) {
        $aspectInverse = (float) $m[1] / 100;
    }
    $height = (int) round($width * $aspectInverse);

    // Inner <svg> opening tag and children.
    if (!preg_match('/<svg\b([^>]*)>(.*?)<\/svg>/s', $html, $m)) {
        return $html; // no SVG to extract — return as-is
    }
    $svgAttrs = $m[1];
    $svgChildren = $m[2];

    preg_match('/viewBox="([^"]+)"/', $svgAttrs, $vbMatch);
    preg_match('/preserveAspectRatio="([^"]+)"/', $svgAttrs, $paMatch);
    $viewBox = $vbMatch[1] ?? '0 0 100 100';
    $preserveAR = $paMatch[1] ?? 'none';

    // Labels overlay (optional).
    $labelsHtml = '';
    $labelsFontFamily = 'inherit';
    $labelsFontSize = 12.0;
    $labelsColor = '#374151';
    if (preg_match('/<div class="svgraph__labels"\s+style="([^"]+)">(.*?)<\/div>\s*(?=<div class="svgraph-tooltip"|<\/div>\s*$)/s', $html, $m)) {
        $labelsStyle = $m[1];
        $labelsHtml = $m[2];
        if (preg_match('/font-family:([^;]+)/', $labelsStyle, $f)) {
            $labelsFontFamily = trim($f[1]);
        }
        if (preg_match('/font-size:([^;]+)/', $labelsStyle, $f)) {
            $labelsFontSize = parseCssLengthToPx(trim($f[1]));
        }
        if (preg_match('/color:(#[0-9a-fA-F]+|rgba?\([^)]+\)|[a-z]+)/', $labelsStyle, $c)) {
            $labelsColor = $c[1];
        }
    }

    $svgLabels = $labelsHtml !== ''
        ? convertLabelsToSvg($labelsHtml, $width, $height, $labelsFontFamily, $labelsFontSize, $labelsColor)
        : '';

    return sprintf(
        '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
                . '<svg x="0" y="0" width="%d" height="%d" viewBox="%s" preserveAspectRatio="%s">%s</svg>'
                . '%s'
            . '</svg>',
        $width, $height, $width, $height,
        $width, $height, htmlspecialchars($viewBox, ENT_QUOTES), htmlspecialchars($preserveAR, ENT_QUOTES),
        $svgChildren,
        $svgLabels,
    );
}

function parseCssLengthToPx(string $length): float
{
    if (preg_match('/^([\d.]+)rem$/', $length, $m)) {
        return (float) $m[1] * 16.0; // assume 16px root font size
    }
    if (preg_match('/^([\d.]+)em$/', $length, $m)) {
        return (float) $m[1] * 16.0;
    }
    if (preg_match('/^([\d.]+)px$/', $length, $m)) {
        return (float) $m[1];
    }
    if (preg_match('/^([\d.]+)$/', $length, $m)) {
        return (float) $m[1];
    }
    return 12.0;
}

function convertLabelsToSvg(
    string $labelsHtml,
    int $width,
    int $height,
    string $fontFamily,
    float $fontSize,
    string $color,
): string {
    // Walk top-level <span> children of the labels overlay using DOM parsing
    // so nested swatch <span>s in pie legends are handled correctly.
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><body>' . $labelsHtml . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body === null) {
        return '';
    }

    $out = '';
    foreach ($body->childNodes as $node) {
        if (!$node instanceof DOMElement || $node->nodeName !== 'span') {
            continue;
        }
        $out .= convertLabelSpan($node, $width, $height, $fontFamily, $fontSize, $color);
    }
    return $out;
}

function convertLabelSpan(
    DOMElement $span,
    int $width,
    int $height,
    string $fontFamily,
    float $fontSize,
    string $color,
): string {
    $style = $span->getAttribute('style');
    $pos = parseLabelStyle($style);

    $x = ($pos['xFrac'] ?? 0.0) * $width;
    $y = ($pos['yFrac'] ?? 0.0) * $height;

    $textAnchor = match ($pos['align']) {
        'center' => 'middle',
        'end' => 'end',
        default => 'start',
    };
    $domBaseline = match ($pos['valign']) {
        'middle' => 'middle',
        'bottom' => 'text-after-edge',
        default => 'hanging', // 'top' and 'baseline' both use hanging — text top at y
    };

    $textColor = $pos['color'] ?? $color;

    // Detect pie legend swatch: first child <span> with background:#hex.
    $firstChild = null;
    foreach ($span->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $firstChild = $child;
            break;
        }
    }
    $swatchColor = null;
    $textContent = '';
    if ($firstChild instanceof DOMElement && $firstChild->nodeName === 'span') {
        $childStyle = $firstChild->getAttribute('style');
        if (preg_match('/background:(#[0-9a-fA-F]+|rgba?\([^)]+\)|[a-z]+)/', $childStyle, $cm)) {
            $swatchColor = $cm[1];
            // Text content is everything except the swatch span.
            foreach ($span->childNodes as $child) {
                if ($child === $firstChild) {
                    continue;
                }
                $textContent .= $child->textContent;
            }
            $textContent = trim($textContent);
        }
    }
    if ($swatchColor === null) {
        $textContent = $span->textContent;
    }

    if ($swatchColor !== null) {
        $swatchSize = $fontSize * 0.6;
        $swatchGap = $fontSize * 0.4;
        return sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="%s"/>'
                . '<text x="%s" y="%s" font-family="%s" font-size="%s" fill="%s" text-anchor="start" dominant-baseline="middle">%s</text>',
            fmtFloat($x),
            fmtFloat($y + ($fontSize - $swatchSize) / 2),
            fmtFloat($swatchSize),
            fmtFloat($swatchSize),
            fmtFloat($swatchSize / 4),
            htmlspecialchars($swatchColor, ENT_QUOTES),
            fmtFloat($x + $swatchSize + $swatchGap),
            fmtFloat($y + $fontSize / 2),
            htmlspecialchars($fontFamily, ENT_QUOTES),
            fmtFloat($fontSize),
            htmlspecialchars($textColor, ENT_QUOTES),
            htmlspecialchars($textContent, ENT_QUOTES),
        );
    }

    return sprintf(
        '<text x="%s" y="%s" font-family="%s" font-size="%s" fill="%s" text-anchor="%s" dominant-baseline="%s">%s</text>',
        fmtFloat($x),
        fmtFloat($y),
        htmlspecialchars($fontFamily, ENT_QUOTES),
        fmtFloat($fontSize),
        htmlspecialchars($textColor, ENT_QUOTES),
        $textAnchor,
        $domBaseline,
        htmlspecialchars($textContent, ENT_QUOTES),
    );
}

/**
 * @return array{xFrac: float|null, yFrac: float|null, align: string, valign: string, color: ?string}
 */
function parseLabelStyle(string $style): array
{
    $result = ['xFrac' => null, 'yFrac' => null, 'align' => 'start', 'valign' => 'baseline', 'color' => null];

    if (preg_match('/left:([\d.]+)%/', $style, $m)) {
        $result['xFrac'] = (float) $m[1] / 100;
    } elseif (preg_match('/right:([\d.]+)%/', $style, $m)) {
        $result['xFrac'] = 1.0 - (float) $m[1] / 100;
    }
    if (preg_match('/top:([\d.]+)%/', $style, $m)) {
        $result['yFrac'] = (float) $m[1] / 100;
    } elseif (preg_match('/bottom:([\d.]+)%/', $style, $m)) {
        $result['yFrac'] = 1.0 - (float) $m[1] / 100;
    }

    if (preg_match('/transform:translate\(([^,]+),([^)]+)\)/', $style, $m)) {
        $tx = trim($m[1]);
        $ty = trim($m[2]);
        if ($tx === '-50%') {
            $result['align'] = 'center';
        } elseif ($tx === '-100%') {
            $result['align'] = 'end';
        }
        if ($ty === '-50%') {
            $result['valign'] = 'middle';
        } elseif ($ty === '-100%') {
            $result['valign'] = 'bottom';
        } elseif ($ty === '0' && str_contains($style, 'transform:')) {
            // explicit translate(0,0) is only emitted when one axis is non-zero;
            // a Label with verticalAlign=top + align=start emits no transform at all
            $result['valign'] = 'top';
        }
    }

    if (preg_match('/color:(#[0-9a-fA-F]+|rgba?\([^)]+\)|[a-z]+)/', $style, $m)) {
        $result['color'] = $m[1];
    }

    return $result;
}

function fmtFloat(float $v): string
{
    if ($v === floor($v)) {
        return (string) (int) $v;
    }
    return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
}

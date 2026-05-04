<?php

declare(strict_types=1);

/**
 * Generate documentation images from runnable example scripts.
 *
 * Each examples/<chart>/<feature>.php file returns a chart instance.
 * This script renders every example to docs/images/<chart>-<feature>.svg
 * so the docs always show real, current chart output.
 *
 * Run via Composer:
 *   composer docs:images
 *
 * Or directly:
 *   php examples/run.php
 */
require __DIR__ . '/../vendor/autoload.php';

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

    file_put_contents($output, (string) $chart);
    echo "  wrote {$name}.svg\n";
    $count++;
}

echo "\nGenerated {$count} image(s) in docs/images/\n";

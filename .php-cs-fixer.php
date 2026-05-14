<?php

require __DIR__ . '/tools/cs-fixer/BlankLineAfterControlStructureFixer.php';

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests']);

return (new PhpCsFixer\Config())
    ->registerCustomFixers([
        new Noeka\Svgraph\Tools\CsFixer\BlankLineAfterControlStructureFixer(),
    ])
    ->setRules([
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try', 'if', 'foreach', 'for', 'while', 'do', 'switch'],
        ],
        'no_extra_blank_lines' => true,
        'Noeka/blank_line_after_control_structure' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);

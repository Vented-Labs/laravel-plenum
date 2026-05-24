<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/config',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'global_namespace_import' => ['import_classes' => true, 'import_functions' => false],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_line_empty_body' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);

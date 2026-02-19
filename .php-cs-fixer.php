<?php

$finder = (new PhpCsFixer\Finder())
    ->in('src')
    ->in('tests')
    ->notPath('bootstrap.php')
    ->notPath('var')
    ->notPath('vendor');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => false,
        'linebreak_after_opening_tag' => true,
        'logical_operators' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],
        'php_unit_method_casing' => ['case' => 'camel_case'],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_separation' => false,
        'yoda_style' => false,
        'single_line_throw' => false,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
    ])
    ->setFinder($finder);

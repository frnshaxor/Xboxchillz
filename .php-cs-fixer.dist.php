<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/controllers',
        __DIR__ . '/routes',
    ])
    ->exclude([
        'vendor',
        'storage',
        'media',
        'sandbox',
        'node_modules',
        'cli',
        'tests',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@PER-CS2.0' => true,
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => ['statements' => ['return']],
        'cast_spaces' => true,
        'concat_space' => ['spacing' => 'one'],
        'declare_equal_normalize' => true,
        'function_typehint_space' => true,
        'include' => true,
        'lowercase_keywords' => true,
        'method_argument_space' => true,
        'no_extra_blank_lines' => true,
        'no_whitespace_before_comma_in_array' => true,
        'normalize_index_brace' => true,
        'single_line_after_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
        'trim_array_spaces' => true,
        'whitespace_after_comma_in_array' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);

<?php // file ".php_cs"

use PhpCsFixer\Config;
use Symfony\Component\Finder\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return Config::create()
    ->setRules(
        [
            /*
             * PSR12
             */
            '@PSR2' => true,
            'blank_line_after_opening_tag' => true,
            'braces' => ['allow_single_line_closure' => true],
            'compact_nullable_typehint' => true,
            'concat_space' => ['spacing' => 'one'],
            'declare_equal_normalize' => ['space' => 'none'],
            'function_typehint_space' => true,
            'new_with_braces' => true,
            'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
            'no_empty_statement' => true,
            'no_leading_import_slash' => true,
            'no_leading_namespace_whitespace' => true,
            'no_whitespace_in_blank_line' => true,
            'return_type_declaration' => ['space_before' => 'none'],
            'single_trait_insert_per_statement' => true,

            /*
             * Custom additional
             */
            'no_blank_lines_after_class_opening' => true,
            'array_indentation' => true,
            'array_syntax' => ['syntax' => 'short'],
            'binary_operator_spaces' => true,
            'blank_line_after_namespace' => true,
            'blank_line_before_return' => true,
            'class_definition' => true,
            'method_chaining_indentation' => true,
            'no_extra_blank_lines' => true,
            'no_short_echo_tag' => true,
            'no_spaces_around_offset' => true,
            'no_unused_imports' => true,
            'no_whitespace_before_comma_in_array' => true,
            'not_operator_with_successor_space' => true,
            'ordered_imports' => [
                'sort_algorithm' => 'alpha',
                'imports_order' => [
                    'class',
                    'function',
                    'const',
                ],
            ],
            'trailing_comma_in_multiline_array' => true,
            'trim_array_spaces' => true,
            'single_quote' => true,
        ]
    )
    ->setFinder($finder);


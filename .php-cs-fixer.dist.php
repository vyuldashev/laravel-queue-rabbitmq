<?php

$finder = PhpCsFixer\Finder::create()
    ->in(['config', 'src', 'tests']);

return PhpCsFixer\Config::create()
    ->setUsingCache(false)
    ->setFinder($finder)
    ->setRules([
        'psr_autoloading' => false,
        '@PSR2' => true,
        'blank_line_after_namespace' => true,
        'braces' => true,
        'class_definition' => true,
        'concat_space' => ['spacing' => 'none'],
        'elseif' => true,
        'function_declaration' => true,
        'indentation_type' => true,
        'line_ending' => true,
        'constant_case' => true,
        'lowercase_keywords' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'no_break_comment' => true,
        'no_closing_tag' => true,
        'no_spaces_after_function_name' => true,
        'no_spaces_inside_parenthesis' => true,
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'single_blank_line_at_eof' => true,
        'single_class_element_per_statement' => [
            'elements' => ['property'],
        ],
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,
        'visibility_required' => true,
        'encoding' => true,
        'full_opening_tag' => true,
        'blank_line_before_statement' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'trailing_comma_in_multiline_array' => true,
        'array_indentation' => true,
        'binary_operator_spaces' => [
            'operators' => [
                '=' => 'single_space',
            ],
        ],
        'fully_qualified_strict_types' => true,
        'void_return' => true,
        'cast_spaces' => [
            'space' => 'single',
        ],
        'not_operator_with_successor_space' => true,
    ]);

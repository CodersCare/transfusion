<?php
$config = new PhpCsFixer\Config();
$config->setRules([
    '@DoctrineAnnotation' => true,
    '@PSR2' => true,
    'array_syntax' => ['syntax' => 'short'],
    'blank_line_after_opening_tag' => true,
    'braces_position' => true,
    'cast_spaces' => ['space' => 'none'],
    'compact_nullable_type_declaration' => true,
    'concat_space' => ['spacing' => 'one'],
    'control_structure_braces' => true,
    'control_structure_continuation_position' => true,
    'declare_equal_normalize' => ['space' => 'none'],
    'declare_parentheses' => true,
    'type_declaration_spaces' => true,
    'lowercase_cast' => true,
    'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
    'native_function_casing' => true,
    'new_with_parentheses' => true,
    'no_blank_lines_after_phpdoc' => true,
    'no_empty_phpdoc' => true,
    'no_empty_statement' => true,
    'no_extra_blank_lines' => true,
    'no_leading_import_slash' => true,
    'no_leading_namespace_whitespace' => true,
    'no_multiple_statements_per_line' => true,
    'no_null_property_initialization' => true,
    'no_short_bool_cast' => true,
    'no_singleline_whitespace_before_semicolons' => true,
    'no_superfluous_elseif' => true,
    'no_trailing_comma_in_singleline' => true,
    'no_unneeded_control_parentheses' => true,
    'no_unused_imports' => true,
    'no_useless_else' => true,
    'no_whitespace_in_blank_line' => true,
    'ordered_imports' => true,
    'phpdoc_no_access' => true,
    'phpdoc_no_empty_return' => true,
    'phpdoc_no_package' => true,
    'phpdoc_scalar' => true,
    'phpdoc_trim' => true,
    'phpdoc_types' => true,
    'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
    'return_type_declaration' => ['space_before' => 'none'],
    'single_quote' => true,
    'single_line_comment_style' => ['comment_types' => ['hash']],
    'single_space_around_construct' => true,
    'single_trait_insert_per_statement' => true,
    'statement_indentation' => true,
    'whitespace_after_comma_in_array' => true,]);
$config->getFinder(__DIR__);
return $config;

<?php
/**
 * Configuration for command
 *
 * @package sublime
 * @subpackage config
 */

/**
 * This filter is documented in lib/class-command.php
 */
add_filter( 'sublime_create_phpdoc_output_cache', '__return_true' );

/**
 * This filter is documented in lib/class-command.php
 */
add_filter( 'sublime_delete_phpdoc_output_cache', '__return_false' );

/**
 * This filter is documented in lib/class-command.php
 */
add_filter( 'sublime_delete_import_before_create', '__return_false' );

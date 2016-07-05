<?php
/**
 * @package sublime
 *
 * @since 1.1
 */

/**
 * This filter is documented in lib/class-command.php
 */
add_filter( 'sublime_create_phpdoc_output_cache', '__return_true' );

/**
 * This filter is documented in lib/class-command.php
 */
add_filter( 'sublime_delete_phpdoc_output_cache', '__return_true' );

/**
 * This filter is documented in lib/class-command.php
 */
add_filter( 'sublime_delete_import_before_create', '__return_true' );

/**
 * This filter is documented in lib/import/class-importer.php
 */
add_filter( 'sublime_import_exclude_constant', function ( $bool, $name ) {
	$exclude = array(
		'object',    // Backcompat used OBJECT
		'$GUIDname', // Dynamic Constants generated in wp-includes/ID3/module.audio-video.asf.php
	);

	return in_array( $name, $exclude );
}, 10, 2 );

/**
 * This filter is documented in lib/import/class-parser.php
 */
add_filter( 'sublime_include_plugins', function ( $plugins ) {
	return array( 'akismet' );
} );

/**
 * This filter is documented in lib/import/class-parser.php
 */
add_filter( 'sublime_exclude_files', function ( $exclude_files ) {
	$exclude_files = array_merge( $exclude_files, array(
		'wp-config.php',        // Exclude in case exists personal data
		'wp-config-sample.php', // Exclude in case exists
		'wp-config-backup.php', // plugin wp-viewer-log generate copy of wp-config.php
	) );

	return $exclude_files;
} );

/**
 * This filter is documented in lib/import/class-importer.php
 */
add_filter( 'sublime_exclude_PHP7_back_compat', '__return_true' );

/**
 * This filter is documented in vendor/wordpress/phpdoc-parser/lib/class-importer.php
 */
add_filter( 'wp_parser_skip_duplicate_hooks', '__return_true' );

/**
 * This filter is documented in lib/export/class-export-functions.php
 */
add_filter( 'sublime_export_exclude_functions', function( $exclude, $name ) {
	return $exclude;
}, 10, 2 );

/**
 * This filter is documented in lib/export/class-export-functions.php
 */
add_filter( 'sublime_export_exclude_private_functions', function ( $exclude, $post ) {
	return $exclude;
}, 10, 2 );

/**
 * This filter is documented in lib/export/class-export-functions.php
 */
add_filter( 'sublime_export_default_arguments', function( $arguments, $name ) {
	/**
	 * fix required arguments domain, email and site_name
	 * because this function add error if empty or if not email
	 */
	if ( 'populate_network' === $name ) {
		foreach ( $arguments as &$argument ) {
			if ( in_array( $argument['name'], array( '$domain', '$email', '$site_name' ) ) )
				unset( $argument['default_value'] );
		}

		return $arguments;
	}

	// Add default __FILE__ constant for speed completion :)
	if ( in_array( $name, array( 'register_activation_hook', 'register_deactivation_hook', 'register_uninstall_hook' ) ) ) {
		foreach ( $arguments as &$argument ) {
			if ( '$file' === $argument['name'] ) {
				$argument['default_value'] = '__FILE__';
				break;
			}
		}

		return $arguments;
	}

	// Add array() to default value because WordPress convert to array
	if ( in_array( $name, array( 'post_class', 'get_post_class' ) ) ) {
		foreach ( $arguments as &$argument ) {
			if ( '$class' === $argument['name'] ) {
				$argument['default_value'] = 'array()';
				break;
			}

		}

		return $arguments;
	}

	return $arguments;
}, 10, 2 );

/**
 * This filter is documented in lib/export/class-export-functions.php
 */
add_filter( 'sublime_export_function_content_completion', function( $completion, $name, $arguments ) {
	if ( empty( $arguments ) ) {
		if ( 0 === strpos( $name, '__return_' ) ) {
			return sprintf( '%s${1:();}', $name );
		}

		return sprintf( '%s();', $name );
	}

	return $completion;
}, 10, 3 );

/**
 * This filter is documented in lib/export/class-export-functions.php
 */
add_filter( 'sublime_export_function_arguments_completion', function( $arguments, $name, $data ) {
	// Add default constant __FILE__ for speed :)
	if ( in_array( $name, array( 'plugin_dir_path', 'plugin_dir_url', 'plugin_basename' ) ) )
		return ' ${1:__FILE__} ';

	// Add default constant __FILE__ for speed :)
	if ( 'plugins_url' === $name )
		return str_replace( '${4:\'\'}', '${4:__FILE__}', $arguments );

	// Replace completions for speed and more compressible :)
	if ( in_array( $name, array( 'post_class', 'get_post_class' ) ) )
		return str_replace( array( 'array()', '${3:, ${4:null' ), array( 'array( ${3:\$class} )', '${4:, ${5:\$post_id' ), $arguments );

	return $arguments;
}, 10, 3 );


/**
 * This filter is documented in lib/import/class-importer.php
 */
add_filter( 'wp_parser_pre_import_item', function( $import, $data, $parent_post_id, $import_ignored, $arg_overrides ) {
	// stripos - php4 compatibility - file: wp-includes/class-pop3.php
	if ( 'stripos' === $data['name'] )
		return false;

	return $import;
}, 10, 5 );

/**
 * This filter is documented in lib/export/class-export-base.php
 */
add_filter( 'sublime_export_change_scope', function ( $scope, $name ) {
	return $scope;
}, 10, 2 );

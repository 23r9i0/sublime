<?php
/**
 * Configuration for export
 *
 * @package sublime
 * @subpackage config
 */

/**
 * This filter is documented in lib/export/class-generator.php
 */
add_filter( 'sublime_export_change_scope', function ( $scope, $name ) {
	return $scope;

}, 10, 2 );

/**
 * This filter is documented in lib/export/class-generator.php
 */
add_filter( 'sublime_readme_table_sort_themes', function ( $sort ) {
	return array(
		'Twenty Ten',
		'Twenty Eleven',
		'Twenty Twelve',
		'Twenty Thirteen',
		'Twenty Fourteen',
		'Twenty Fifteen',
		'Twenty Sixteen',
	);

}, 10, 2 );

/**
 * This filter is documented in lib/export/class-functions.php
 */
add_filter( 'sublime_export_exclude_functions', function( $exclude, $name ) {
	return $exclude;

}, 10, 2 );

/**
 * This filter is documented in lib/export/class-functions.php
 */
add_filter( 'sublime_export_exclude_private_functions', function ( $exclude, $post ) {
	return $exclude;

}, 10, 2 );

/**
 * This filter is documented in lib/export/class-functions.php
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
	$functions_to_constant_file = array(
		'plugin_basename', // $file
		'plugin_dir_path', // $file
		'plugin_dir_url', // $file
		'plugins_url', // $plugin
		'register_activation_hook', // $file
		'register_deactivation_hook', // $file
		'register_uninstall_hook', // $file
	);
	if ( in_array( $name, $functions_to_constant_file ) ) {
		$variable_name = ( 'plugins_url' === $name ) ? '$plugin' : '$file';
		foreach ( $arguments as &$argument ) {
			if ( $variable_name === $argument['name'] ) {
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

	/**
	 * Add default Path on load_plugin_textdomain for speed completion :)
	 *
	 * @see https://codex.wordpress.org/Function_Reference/load_plugin_textdomain#Examples
	 */
	if ( 'load_plugin_textdomain' === $name ) {
		foreach ( $arguments as &$argument ) {
			if ( '$plugin_rel_path' === $argument['name'] ) {
				$argument['default_value'] = "dirname( plugin_basename( __FILE__ ) ) . '/languages'";
				break;
			}
		}

		return $arguments;
	}

	/**
	 * Add Default Path on load_theme_textdomain for speed completion :)
	 *
	 * @see https://codex.wordpress.org/Function_Reference/load_theme_textdomain#Examples
	 */
	if ( 'load_theme_textdomain' === $name ) {
		foreach ( $arguments as &$argument ) {
			if ( '$path' === $argument['name'] ) {
				$argument['default_value'] = "get_template_directory() . '/languages'";
				break;
			}
		}

		return $arguments;
	}

	return $arguments;

}, 10, 2 );

/**
 * This filter is documented in lib/export/class-functions.php
 */
add_filter( 'sublime_export_function_content_completion', function( $completion, $name, $arguments ) {
	if ( empty( $arguments ) ) {
		if ( 0 === strpos( $name, '__return_' ) ) {
			return sprintf( '%s${1:();}', $name );
		}
	}

	return $completion;

}, 10, 3 );

/**
 * This filter is documented in lib/export/class-functions.php
 */
add_filter( 'sublime_export_function_arguments_completion', function( $arguments, $name, $data ) {
	// Replace completions for speed and more compressible :)
	if ( in_array( $name, array( 'post_class', 'get_post_class' ) ) )
		return str_replace( array( 'array()', '${3:, ${4:null' ), array( 'array( ${3:\$class} )', '${4:, ${5:\$post_id' ), $arguments );

	return $arguments;

}, 10, 3 );

/**
 * This filter is documented in lib/export/class-classes.php
 */
add_filter( 'sublime_export_method_is_global_variable', function( $is_global_variable, $class_name ) {
	return in_array( $class_name, array( 'wpdb' ) );
}, 10, 2 );

<?php
/**
 * Plugin Name: Sublime
 */

/**
 * Only is available if exists Composer Autoloader
 *
 * @package sublime
 *
 * @since 1.0
 */
if ( false !== stream_resolve_include_path( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';

	/**
	 * Initialize this plugin
	 */
	global $sublime;
	$sublime = new Sublime\Plugin();

	add_filter( 'wp_parser_skip_duplicate_hooks', '__return_true' );

	/**
	 * Exclude some Constants for various reasons
	 *
	 * @see  Importer::import_constant()
	 * @return  bool simple check $name exists in $exclude
	 */
	add_filter( 'sublime_exclude_constant', function ( $bool, $name ) {
		$exclude = array(
			'object', // Backcompat used OBJECT
			'$GUIDname', // Dynamic Constants generated in wp-includes\ID3\module.audio-video.asf.php
		);

		return in_array( $name, $exclude );

	}, 10, 2 );

	/**
	 * Exclude Plugins Folder
	 */
	add_filter( 'sublime_exclude_plugins', '__return_true' );

	/**
	 * Include Plugins by name folder, by default Exclude Plugins Folder
	 *
	 * @return array array( 'akismet', ... )
	 */
	add_filter( 'sublime_include_plugins', function ( $plugins ) {
		return array( 'akismet' );
	} );

	/**
	 * Used for change defaults arguments on export
	 *
	 * @param array  $arguments
	 * @param string $name
	 *
	 * @return array $arguments
	 */
	add_filter( 'sublime_parse_function_args', function( $arguments, $name ) {
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

		// Add default constant file for speed :)
		if ( in_array( $name, array( 'register_activation_hook', 'register_deactivation_hook', 'register_uninstall_hook' ) ) ) {
			foreach ( $arguments as &$argument ) {
				if ( '$file' === $argument['name'] )
					$argument['default_value'] = '__FILE__';
					break;
			}

			return $arguments;
		}

		return $arguments;
	}, 10, 2 );

	/**
	 * Used for change data in function arguments on export
	 *
	 * @param string $arguments all arguments with format snippet
	 * @param string $name      function name
	 * @param array  $data      array of arguments
	 *
	 * @return string all arguments with format snippet
	 */
	add_filter( 'sublime_parse_function_contents', function( $arguments, $name, $data ) {
		// Add default constant file for speed :)
		if ( in_array( $name, array( 'plugin_dir_path', 'plugin_dir_url', 'plugin_basename' ) ) )
			return ' ${1:__FILE__} ';

		return $arguments;
	}, 10, 3 );

	/**
	 * Used for change function completion on export
	 *
	 * @param string $completion      function completion with format snippet
	 * @param string $name            function name
	 * @param array  $arguments       array of arguments
	 *
	 * @return string function completion with format snippet
	 */
	add_filter( 'sublime_parse_function', function( $completion, $name, $arguments ) {
		return $completion;
	}, 10, 3 );

	/**
	 * Used for exclude some functions on export
	 */
	add_filter( 'sublime_exclude_functions', function( $exclude, $name ) {
		return $exclude;
	}, 10, 2 );

	/**
	 * Used for exclude private functions on export
	 */
	add_filter( 'sublime_exclude_private_functions', function ( $exclude, $post ) {
		return $exclude;
	}, 10, 2 );
}

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
	 * Used for change data in function arguments
	 */
	add_filter( 'sublime_parse_function_args', function( $completions, $name, $arguments ) {
		return $completions;
	}, 10, 3 );

	add_filter( 'sublime_exclude_functions', function( $exclude, $name ) {
		return $exclude;
	}, 10, 2 );

	add_filter( 'sublime_exclude_private_functions', function ( $exclude, $post ) {
		return false;

		if ( 0 === strpos( $post->post_title, '_' ) ) {
			$params_tags = get_post_meta( $post->ID, '_wp-parser_tags', true );
			$access      = array_column( wp_list_filter( $params_tags, array( 'name' => 'access' ) ), 'content', 'name' );

			if ( ! empty( $access ) ) {
				if ( in_array( 'private', array_values( $access ) ) )
					return true;
			}
		}

		return $exclude;
	}, 10, 2 );
}
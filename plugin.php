<?php
/**
 * Plugin Name: Sublime
 * Plugin URI: https://github.com/kallookoo/sublime
 * Description: Helper to generate sublime text completions
 * Version: 2.0
 * Author: Sergio ( kallookoo )
 * Author URI: http://dsergio.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Only is available if exists Composer Autoloader
 */
if ( false !== stream_resolve_include_path( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';

	/**
	 * Initialize this plugin
	 */
	new \Sublime\Plugin();

	if ( ! function_exists( '_subl_json_encode' ) ) {
		function _subl_json_encode( $data ) {
			return json_encode( $data, JSON_PRETTY_PRINT );
		}
	}

	if ( ! function_exists( '_subl_file_put_contents' ) ) {
		function _subl_file_put_contents( $name, $data, $append = false ) {
			$ext = ( false !== strpos( $name, '.' ) ? '' : ( is_string( $data ) ? '.txt' : '.json' ) );
			file_put_contents( __DIR__ . "/{$name}{$ext}", ( is_string( $data ) ? $data : _subl_json_encode( $data ) ), ( $append ? FILE_APPEND : 0 ) );
		}
	}
}

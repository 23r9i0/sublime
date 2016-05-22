<?php
/**
 * Plugin Name: Sublime
 * Plugin URI: https://github.com/23r9i0/sublime
 * Description: Helper to generate sublime text completions
 * Version: 1.1
 * Author: Sergio P.A. ( 23r9i0 )
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
	new Sublime\Plugin();

	/**
	 * Add Config file to apply filters
	 */
	if ( false !== stream_resolve_include_path( __DIR__ . '/config.php' ) )
		require_once __DIR__ . '/config.php';
}

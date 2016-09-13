<?php
/**
 * Configuration for import
 *
 * @package sublime
 * @subpackage config
 */

/**
 * This filter is documented in lib/class-importer.php
 */
add_filter( 'sublime_import_exclude_constant', function ( $bool, $name ) {
	$exclude = array(
		'object',    // Backcompat used OBJECT
		'$GUIDname', // Dynamic Constants generated in wp-includes/ID3/module.audio-video.asf.php
	);

	return in_array( $name, $exclude );

}, 10, 2 );

/**
 * This filter is documented in lib/class-importer.php
 */
add_filter( 'sublime_include_plugins', function ( $plugins ) {
	return array( 'akismet' );

} );

/**
 * This filter is documented in lib/class-importer.php
 */
add_filter( 'sublime_exclude_files', function ( $exclude_files ) {
	return array(
		'wp-config.php',        // Exclude in case exists personal data
		'wp-config-backup.php', // plugin wp-viewer-log generate copy of wp-config.php
		'wp-config-sample.php', // Exclude in case exists all definitions already exists in others files
	);

} );

/**
 * This filter is documented in lib/class-importer.php
 */
add_filter( 'sublime_skip_duplicate_by_name', function ( $skip, $data ) {
	if ( isset( $data['doc']['description'] ) ) {
		if ( 'akismet_comment_nonce' === $data['name'] ) {
			if ( 0 === strpos( $data['doc']['description'], 'See filter documentation' ) )
				return true;
		}

		if ( 'link_category' === $data['name'] ) {
			if ( false !== strpos( $data['doc']['description'], 'OPML' ) )
				return true;
		}

		if ( 'pre_user_login' === $data['name'] ) {
			if ( false !== strpos( $data['doc']['description'], 'Filter a username after it has been sanitized.' ) )
				return true;
		}
	}

	return false;

}, 10, 2 );

/**
 * This filter is documented in lib/class-importer.php
 */
add_filter( 'wp_parser_pre_import_item', function( $import, $data, $parent_post_id, $import_ignored, $arg_overrides ) {
	// stripos - php4 compatibility - file: wp-includes/class-pop3.php
	if ( 'stripos' === $data['name'] )
		return false;

	return $import;

}, 10, 5 );

/**
 * This filter is documented in lib/class-importer.php
 */
add_filter( 'wp_parser_skip_duplicate_hooks', '__return_true' );

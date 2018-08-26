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
	return $plugins;

} );

/**
 * This filter is documented in lib/class-importer.php
 */
add_filter( 'sublime_include_themes', function ( $themes ) {
	return $themes;

} );

/**
 * This filter is documented in lib/class-importer.php
 *
 * Not remove wp-config files, all constants inside this files already exists inside Core files or in /missing/constants.php
 */
add_filter( 'sublime_exclude_files', function ( $exclude_files ) {
	return array(
		'wp-config(.*)', // Exclude for privacy
		'deprecated(.*)', // Deprecated files
		'wp-admin/admin-functions.php', // Deprecated file
		'wp-admin/includes/noop.php', // Ignore file
		'wp-includes/(spl-autoload-)?compat.php', // Ignore PHP package
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
add_filter( 'wp_parser_import_item_post_data', function( $post_data, $existing_post_id ) {
	switch ( $post_data['post_type'] ) {
		case 'wp-parser-constant':
			if ( 'MEDIA_TRASH' === $post_data['post_title'] ) {
				$post_data['post_excerpt'] = 'Enable trash for media.';
			}
			break;

		default:
			break;
	}

	return $post_data;
}, 10, 2 );

/**
 * This filter is documented in lib/class-importer.php
 */
add_filter( 'wp_parser_deprecated_files', function( $deprecated_regex_files ) {
	$deprecated_regex_files = array(
		'deprecated.php', // wp deprecated files
		'wp-admin/includes/noop.php', // ?; Some functions, not is defined explications but if defined in others files or if phpdoc tag @ignore
		'wp-includes/(spl-autoload-)?compat.php', // Ignore PHP package
		'wp-includes/random_compat/.*', // PHP 7 support
		'wp-includes/theme-compat/.*', // wp themes compat files
		'wp-content/themes/([^\/]+)/inc/back-compat.php', // Themes wp ( functions|actions for internal uses, developers not use )
		'wp-includes/js/tinymce/wp-tinymce.php', // Disabled import get_file function, developers not use
	);

	return $deprecated_regex_files;
} );

/**
 * This filter is documented in lib/class-importer.php
 */
add_filter( 'wp_parser_skip_duplicate_with_empty_phpdoc', function( $skip, $name, $file ) {
	if ( 'wp-admin/admin.php' === $file && 0 === strpos( $name, 'load-' ) ) {
		return false;
	}

	// Valid Hooks without valid PHPDoc aka empty PHPDoc
	// Last check 4.6.1 Version
	$list = array(
		'auth_cookie_bad_session_token',
		'content_save_pre',
		'custom_menu_order',
		'excerpt_save_pre',
		'title_save_pre',
		'wp_maybe_auto_update',
		'wp_refresh_nonces',
		'wp_save_post_revision_check_for_changes',
		'post_{$field}',
		'pre_post_{$field}',
		'edit_post_{$field}',
	);

	if ( in_array( $name, $list ) ) {
		return false;
	}

	return $skip;
}, 10, 3 );

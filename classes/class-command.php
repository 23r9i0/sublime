<?php
/**
 *
 */

namespace Sublime;

use \WP_CLI;
use \WP_CLI\Utils;
use \WP_Error;
use \Sublime\Capabilities;
use \Sublime\Classes;
use \Sublime\Constants;
use \Sublime\Functions;
use \Sublime\Hooks;
use \Sublime\Importer;
use \Sublime\Methods;

class Command extends \WP_Parser\Command {

	/**
	 * Generate Packages files inside package folder or custom folder
	 *
	 * ## OPTIONS
	 *
	 * [--directory=<directory>]
	 * : Specify the directory on generate WordPress completions, this completions is create inside of the completions folder on directory.
	 *
	 * [--type=<all|constants|capabilities|functions|hooks|actions|filters|classes|methods>]
	 * : Specify the type of the completions.
	 *
	 * [--update-readme]
	 * : Update Readme.md for WordPress Completions Sublime Package and Update /wiki/Home.md if exists
	 */
	public function generate( $args, $assoc_args ) {
		$classes   = array();
		$directory = \WP_CLI\Utils\get_flag_value( $assoc_args, 'directory', '' );
		$type      = \WP_CLI\Utils\get_flag_value( $assoc_args, 'type', 'all' );
		$types     = array( 'functions', 'actions', 'filters', 'classes', 'methods', 'constants', 'capabilities' );

		if ( 'all' === $type ) {
			foreach ( $types as $type )
				$classes[] = $type;

		} elseif ( 'hooks' === $type ) {
			foreach ( array( 'actions', 'filters' ) as $type )
				$classes[] = $type;

		} elseif ( in_array( $type, $types ) ) {
			$classes[] = $type;
		} else {
			WP_CLI::error( sprintf( 'Type %s is undefined.', $type ) );
		}

		foreach ( $classes as $class ) {
			$class = "\\Sublime\\" . ucfirst( $class );
			call_user_func( array( $class, 'run' ), $directory, isset( $assoc_args['update-readme'] ) );
		}
	}

	/**
	 * Generate the data from the PHPDoc markup.
	 *
	 * @param string $path   Directory or file to scan for PHPDoc
	 * @param string $format What format the data is returned in: [json|array].
	 *
	 * @return string|array
	 */
	protected function _get_phpdoc_data( $path, $format = 'json' ) {
		$output_cache_file = dirname( __DIR__ ) . '/cache-phpdoc.json';

		/**
		 * Force delete last cache of phpdoc
		 * Compare directory and WordPress Version for detecting
		 */
		$delete_output_cache_file  = false;
		$wp_parser_root_import_dir = get_option( 'wp_parser_root_import_dir', '' );
		$directory_to_compare      = wp_normalize_path( __DIR__ );

		if ( false !== strpos( $wp_parser_root_import_dir, $directory_to_compare ) ) {
			$current_wp_version            = get_bloginfo( 'version' );
			$wp_parser_imported_wp_version = get_option( 'wp_parser_imported_wp_version', $current_wp_version );
			$delete_output_cache_file      = ( $wp_parser_imported_wp_version != $current_wp_version );
		}

		/**
		 * Delete last cache of phpdoc, true for deleting or false to skip
		 *
		 * Default: false or compare wordpress version see above
		 */
		$delete_output_cache_file = apply_filters( 'sublime_delete_phpdoc_output_cache', $delete_output_cache_file );
		if ( $delete_output_cache_file ) {
			if ( false !== stream_resolve_include_path( $output_cache_file ) )
				unlink( $output_cache_file );
		}

		if ( false !== stream_resolve_include_path( $output_cache_file ) ) {
			if ( $output = file_get_contents( $output_cache_file ) )
				$output = ( 'json' == $format ) ? $output : json_decode( $output, true );
		} else {
			WP_CLI::line( sprintf( 'Extracting PHPDoc from %s. This may take a few minutes...', $path ) );
			$is_file = is_file( $path );
			$files   = $is_file ? array( $path ) : Importer::set_parser_phpdoc( $path );
			$path    = $is_file ? dirname( $path ) : $path;

			if ( $files instanceof WP_Error ) {
				WP_CLI::error( sprintf( 'Problem with %1$s: %2$s', $path, $files->get_error_message() ) );
				exit;
			}

			$output = Importer::get_parser_phpdoc( $files, $path );

			/**
			 * Generate cache file from phpdoc, true for generate or false to skip
			 *
			 * Default: true
			 */
			if ( apply_filters( 'sublime_create_phpdoc_output_cache', true ) )
				file_put_contents( $output_cache_file, json_encode( $output, JSON_PRETTY_PRINT ) );

			if ( 'json' == $format )
				$output = json_encode( $output, JSON_PRETTY_PRINT );
		}

		if ( $helper_directory = realpath( dirname( __DIR__ ) . '/missing' ) ) {
			$helpers = glob( $helper_directory . '/*.php' );
			if ( is_array( $helpers ) )
				$output = array_merge( $output, Importer::get_parser_phpdoc( $helpers, $helper_directory ) );
		}

		return $output;
	}

	/**
	 * Import the PHPDoc $data into WordPress posts and taxonomies
	 *
	 * @param array $data
	 * @param bool  $skip_sleep     If true, the sleep() calls are skipped.
	 * @param bool  $import_ignored If true, functions marked `@ignore` will be imported
	 *                              Disabled, not remove to prevent PHP Warning
	 */
	protected function _do_import( array $data, $skip_sleep = false, $import_ignored = false ) {
		if ( ! wp_get_current_user()->exists() )
			WP_CLI::error( 'Please specify a valid user: --user=<id|login>' );

		/**
		 * Delete posts before import
		 * Warning!!! This filter takes a long time to complete,
		 *
		 * Default: false
		 */
		if ( apply_filters( 'sublime_delete_import_before_create', false ) ) {
			global $wpdb;
			$posts_id = $wpdb->get_col( "SELECT DISTINCT ID FROM $wpdb->posts WHERE post_type LIKE '%wp-parser-%'" );
			if ( $posts_id ) {
				$total_count = count( $posts_id );
				$progress = \WP_CLI\Utils\make_progress_bar( sprintf( 'Deleting %s posts.', $total_count ), $total_count );
				$deleted_with_error = array();
				foreach ( $posts_id as $count => $post_id ) {
					if ( ! wp_delete_post( (int) $post_id, true ) )
						$deleted_with_error[] = $post_id;

					$progress->tick();

					if ( ! $skip_sleep && 0 == $count % 10 ) { // TODO figure our why are we still doing this
						sleep( 3 );
					}
				}
				$progress->finish();

				if ( $deleted_with_error )
					WP_CLI::error( sprintf( 'Not deleting %s of the %s. This import not continue, please try again.', count( $deleted_with_error ), $total_count ) );
			}
		}

		// Run the importer
		Importer::run( $data, $skip_sleep );
	}
}

<?php

namespace Sublime;

use \WP_CLI;
use \WP_CLI\Utils;

class Command extends \WP_Parser\Command {

	/**
	 * Generate JSON containing the PHPDoc markup, convert it into WordPress posts, and insert into DB.
	 *
	 * @subcommand create
	 * @synopsis   <directory> [--user] [--quick]
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function create( $args, $assoc_args ) {
		parent::create( $args, $assoc_args );
	}

	/**
	 * Read a JSON file containing the PHPDoc markup, convert it into WordPress posts, and insert into DB.
	 *
	 * @synopsis <file> [--quick]
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function import( $args, $assoc_args ) {
		parent::import( $args, $assoc_args );
	}

	/**
	 * Generate Packages files inside package folder or custom folder
	 *
	 * ## OPTIONS
	 *
	 * [--directory=<directory>]
	 * : Specify the directory on generate WordPress completions.
	 *
	 * [--type=<all|constants|capabilities|functions|hooks|actions|filters|classes|methods>]
	 * : Specify the type of the completions.
	 *
	 */
	public function generate( $args, $assoc_args ) {
		$directory = \WP_CLI\Utils\get_flag_value( $assoc_args, 'directory', '' );
		$type      = isset( $assoc_args['type'] ) && ! empty( $assoc_args['type'] ) ? $assoc_args['type'] : 'all';

		$export = new Export( $directory, $type );
		$export->process();

		if ( count( $export->get_errors() ) ) {
			foreach ( $export->get_errors() as $error ) {
				WP_CLI::line( $error );
			}
			exit;
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
		 * Delete last cache of phpdoc, true for deleting or false to skip
		 *
		 * Default: false
		 */
		if ( apply_filters( 'sublime_delete_phpdoc_output_cache', false ) ) {
			if ( false !== stream_resolve_include_path( $output_cache_file ) )
				unlink( $output_cache_file );
		}

		if ( false !== stream_resolve_include_path( $output_cache_file ) ) {
			if ( $output = file_get_contents( $output_cache_file ) )
				$output = ( 'json' == $format ) ? $output : json_decode( $output, true );
		} else {
			WP_CLI::line( sprintf( 'Extracting PHPDoc from %s. This may take a few minutes...', $path ) );
			$is_file = is_file( $path );
			$files   = $is_file ? array( $path ) : Parser::set( $path );
			$path    = $is_file ? dirname( $path ) : $path;

			if ( $files instanceof \WP_Error ) {
				WP_CLI::error( sprintf( 'Problem with %1$s: %2$s', $path, $files->get_error_message() ) );
				exit;
			}

			$output = Parser::get( $files, $path );

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
				$output = array_merge( $output, Parser::get( $helpers, $helper_directory ) );
		}

		file_put_contents( __DIR__ . '/output.json', json_encode( $output, JSON_PRETTY_PRINT ) );
		return $output;
	}

	/**
	 * Import the PHPDoc $data into WordPress posts and taxonomies
	 *
	 * @param array $data
	 * @param bool  $skip_sleep     If true, the sleep() calls are skipped.
	 * @param bool  $import_ignored If true, functions marked `@ignore` will be imported.
	 */
	protected function _do_import( array $data, $skip_sleep = false, $import_ignored = false ) {
		if ( ! wp_get_current_user()->exists() ) {
			WP_CLI::error( 'Please specify a valid user: --user=<id|login>' );
			exit;
		}

		/**
		 * Delete posts before import
		 * Warning!!! This filter takes a long time to complete,
		 *
		 * Default: false
		 */
		if ( apply_filters( 'sublime_delete_import_before_create', false ) ) {
			global $wpdb;
			$posts_id = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type LIKE '%wp-parser-%'" );
			if ( $posts_id ) {
				WP_CLI::line( sprintf( 'Deleting %s posts. This may take a few minutes...', count( $posts_id ) ) );
				foreach ( $posts_id as $count => $post_id ) {
					if ( $post = wp_delete_post( (int) $post_id, true ) ) {
						WP_CLI::line( sprintf( 'Deleted %s', $post->post_title ) );
					}

					if ( ! $skip_sleep && 0 == $count % 10 ) { // TODO figure our why are we still doing this
						sleep( 3 );
					}
				}
			}
		}

		// Run the importer
		$importer = new Importer;
		$importer->setLogger( new \WP_Parser\WP_CLI_Logger() );
		$importer->import( $data, $skip_sleep, $import_ignored );

		WP_CLI::line();
	}
}

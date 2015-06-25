<?php

namespace Sublime;

use WP_CLI;

class Command extends \WP_Parser\Command {

	/**
	 * Generate Packages files inside package folder or custom folder
	 *
	 * @subcommand pkg
	 * @synopsis <directory> [--type]
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function pkg( $args, $assoc_args ) {
		list( $directory ) = $args;

		$type = isset( $assoc_args['type'] ) && ! empty( $assoc_args['type'] ) ? $assoc_args['type'] : 'all';

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
		if ( false !== stream_resolve_include_path( dirname( __DIR__ ) . '/phpdoc.json' ) ) {
			if ( 'json' == $format ) {
				return file_get_contents( dirname( __DIR__ ) . '/phpdoc.json' );
			}

			return json_decode( file_get_contents( dirname( __DIR__ ) . '/phpdoc.json' ), true );
		}

		WP_CLI::line( sprintf( 'Extracting PHPDoc from %1$s. This may take a few minutes...', $path ) );
		$is_file = is_file( $path );
		$files   = $is_file ? array( $path ) : Parser::set( $path );
		$path    = $is_file ? dirname( $path ) : $path;

		if ( $files instanceof \WP_Error ) {
			WP_CLI::error( sprintf( 'Problem with %1$s: %2$s', $path, $files->get_error_message() ) );
			exit;
		}

		$output = Parser::get( $files, $path );

		if ( 'json' == $format ) {
			return json_encode( $output, JSON_PRETTY_PRINT );
		}

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

		// Run the importer
		$importer = new Importer;
		$importer->setLogger( new \WP_Parser\WP_CLI_Logger() );
		$importer->import( $data, $skip_sleep, $import_ignored );

		WP_CLI::line();
	}
}

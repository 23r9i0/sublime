<?php
/**
 * Generate Snippets list from directory
 *
 * @package sublime
 */
namespace Sublime;

use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \WP_CLI;
use \WP_CLI\Utils;

class Snippets {

	public static function generate( $directory ) {
		$directory = realpath( $directory );

		if ( ! is_dir( $directory ) )
			WP_CLI::error( sprintf( '%s: is not a directory.', $directory ) );

		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) );
		$list  = array();

		try {
			foreach ( $files as $file ) {
				if ( 'sublime-snippet' !== $file->getExtension() )
					continue;

				$list[] = wp_normalize_path( $file->getPathname() );
			}

		} catch ( \UnexceptedValueException $e ) {
			WP_CLI::error( sprintf( 'Directory [%s] contained a directory we can not recurse into', $directory ) );
		}

		return self::_generate( $directory, $list );
	}

	protected static function _generate( $directory, $list ) {
		$wiki = trailingslashit( $directory ) . 'wiki';
		if ( ! is_dir( $wiki ) ) {
			WP_CLI::warning( 'Not exists wiki directory' );
			return false;
		}

		$output   = array( "## Snippets List\n\n" );
		$snippets = array();
		$progress = \WP_CLI\Utils\make_progress_bar( "Generating wiki Snippets:", count( $list ) );

		foreach ( $list as $file ) {
			if ( $content = file_get_contents( $file ) ) {
				if ( preg_match_all( '/(?:\<)(description|tabTrigger)(?:\>)([^\<]+)/', $content, $matches, PREG_SET_ORDER ) ) {
					try {
						$snippets[] = sprintf( "* [%s](%s)\n", $matches[0][2], str_replace( $directory, '../blob/master', $file ) );
						$snippets[] = sprintf( "\tTrigger: `%s`\n", $matches[1][2] );
					} catch ( Expection $e ) {
						WP_CLI::warning( sprintf( 'Error on exporting this item: %s', basename( $file ) ) );
					}
				} else {
					WP_CLI::warning( sprintf( 'Incorrect format for: %s', $file ) );
				}
			}
			$progress->tick();
		}
		$progress->finish();

		$output = implode( "\n", array_merge( $output, $snippets ) );

		if ( file_put_contents( trailingslashit( $directory ) . 'wiki/Snippets.md', $output ) ) {
			return ( count( $snippets ) / 2 );
		} else {
			WP_CLI::error( 'There was a problem created the file.' );
		}
	}
}
<?php
/**
 *
 */

namespace Sublime;

use WP_CLI;

class Export_Hooks extends Export_Base {

	private $_post_type = 'wp-parser-hook';

	public function __construct( $directory = '', $_args = array() ) {
		if ( ! post_type_exists( $this->_post_type ) ) {
			WP_CLI::error( 'Unknown Post Type on Export Hooks' );
			exit;
		}

		$args = array(
			'template'  => array( 'comment' => 'WordPress Hooks' ),
			'post_type' => $this->_post_type,
			'name'      => 'Hooks',
		);

		if ( count( $_args ) ) {
			$args = wp_parse_args( $_args, $args );
		}

		if ( ! empty( $directory ) )
			$args['directory'] = $directory;

		parent::__construct( $args );
	}

	public function generate_completions() {
		$completions = array();

		if ( count( $this->elements ) ) {
			global $post;
			foreach ( $this->elements as $post ) {
				if ( $completion = $this->generate_completion( $post ) ) {
					foreach ( $completion as $c ) {
						$completions[] = $c;
					}
				}
			}
		}

		return $completions;
	}

	public function generate_completion( $post ) {
		return false;
	}

	public function parse_contents( $post, $args, $type = 'filter' ) {
		if ( false !== strpos( $post->post_title, '{$' ) ) {
			$parse_name = $this->parse_name( $post->post_title );
			$name       = $parse_name['name'];
			$index      = $parse_name['index'];
		} else {
			$name  = sprintf( "'%s'", $post->post_title );
			$index = 0;
		}

		$output = sprintf( '%s, ${%d:\$function_to_add}', $name, ++$index );
		$accepted_args = count( $args );
		if ( 1 < $accepted_args ) {
			$output .= sprintf( '${%d:, ${%d:10}${%d:, ${%d:%d}}}', ++$index, ++$index, ++$index, ++$index, $accepted_args );
		} else {
			$output .= sprintf( '${%d:, ${%d:10}}', ++$index, ++$index );
		}

		return sprintf( 'add_%s( %s );', $type, $output );
	}

	public function parse_name( $post_title ) {
		preg_match_all('/(\{\$)?[^\{\$\}]+(\})?/', $post_title, $matches );
		$matches = array_map( function( $a ) {
			return str_replace( array( '$', '{', '}' ), array( '\$', '\{', '\}' ), $a );
		}, $matches[0] );

		$index = 0;
		$name = '';
		foreach ( $matches as $match ) {
			if ( false !== strpos( $match, '\\' ) ) {
				$name .= sprintf( '${%d:%s}', ++$index, $match );
			} else {
				$name .= $match;
			}
		}

		return array( 'name' => sprintf( '"%s"', $name ), 'matches' => $matches, 'index' => $index );
	}
}

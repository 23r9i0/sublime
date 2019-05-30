<?php
/**
 * Export Methods
 *
 * @package sublime
 */
namespace Sublime;

use \Sublime\Functions;

class Methods extends Functions {

	public $post_type = 'wp-parser-method';

	public function __construct( $directory = '' ) {
		parent::__construct( $directory );
	}

	public function generate_completion( $post ) {
		$post_id = isset( $post->ID ) ? $post->ID : 0;
		if ( $post_id && ! empty( $post->post_title ) ) {
			if ( 'public' === strtolower( get_post_meta( $post_id, '_wp-parser_visibility', true ) ) ) {
				list( $class, $method ) = explode( '::', $post->post_title );

				if ( '__construct' === $method ) {
					$name = sprintf( '${1:\$${2:class} = }new %s', $class );
				} elseif ( get_post_meta( $post_id, '_wp-parser_static', true ) ) {
					$name = sprintf( '${1:%s}::%s', $class, $method );
				} else {
					$name = sprintf( '${1:%s}->%s', $class, $method );
				}

				$arguments = get_post_meta( $post_id, '_wp-parser_args', true );
				$arguments = ( $arguments ? map_deep( $arguments, 'maybe_unserialize' ) : array() );
				$arguments = ( $arguments ? $this->parse_arguments( $arguments ) : array() );
				$contents  = $this->contents( $name, $arguments );

				if ( ! in_array( $method, array( '__construct', '__destruct' ), true ) && ( 0 === strpos( $method, '_' ) ) ) {
					$contents = sprintf(
						'${0:/** This method seems not public, check it. */}%s',
						$contents
					);
				}
				return array(
					'trigger' => sprintf( "%s-%s\tWP Class Method", $class, $method ),
					'contents' => $contents,
				);
			}
		}

		return false;
	}

	public function contents( $name, $data, $return = null ) {
		$arguments = '';
		$index     = substr_count( $name, '${' );
		if ( 1 === count( $data ) && ! isset( $data[0]['childrens'] ) ) {
			if ( false === strpos( $data[0]['name'], 'deprecated' ) ) {
				if ( isset( $data[0]['default_value'] ) ) {
					$arguments = sprintf(
						'${%s: ${%s:%s} }',
						++$index,
						++$index,
						$this->parse_argument_name( $data[0]['default_value'], $index )
					);
				} else {
					$arguments = sprintf( ' ${%s:\\%s} ', ++$index, $data[0]['name'] );
				}
			}
		} elseif ( count( $data ) ) {
			$first_children = isset( $data[0]['childrens'] );
			$arguments      = $first_children ? sprintf( '${%s: ', ++$index ) : ' ';

			foreach ( $data as $key => $arg )
				list( $index, $arguments ) = $this->argument( $arguments, $arg, $index );
			$arguments .= $first_children ? ' }' : ' ';
		}

		/**
		 * Used for change method arguments on export
		 *
		 * @param string $arguments all arguments with format snippet
		 * @param string $name      method name
		 * @param array  $data      array of arguments
		 *
		 * @return string all arguments with format snippet
		 */
		$arguments  = apply_filters( 'sublime_export_method_arguments_completion', $arguments, $name, $data );
		$last_index = substr_count( $arguments, '${' );
		$last_index = ( $return ? sprintf( '${%d:;}', ( empty( $last_index ) ? 1 : ++$last_index ) ) : ';' );
		$completion = sprintf( '%s(%s)%s', $name, $arguments, $last_index );

		/**
		 * Used for change method completion on export
		 *
		 * @param string $completion      method completion with format snippet
		 * @param string $name            method name
		 * @param array  $arguments       array of arguments
		 *
		 * @return string method completion with format snippet
		 */
		return apply_filters( 'sublime_export_method_content_completion', $completion, $name, $arguments );
	}

}

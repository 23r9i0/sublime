<?php
/**
 * Export Methods
 *
 * @package sublime
 * @subpackage export
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
		if ( $meta = get_post_custom( $post_id ) ) {
			$meta = wp_parse_args( $meta, array(
				'_wp-parser_args'       => array(),
				'_wp-parser_visibility' => '',
				'_wp-parser_static'     => false,
				'_wp-parser_tags'       => array(),
			) );

			$data = array( 'args' => array(), 'visibility' => '', 'static' => false );
			foreach ( $meta as $meta_key => $meta_value ) {
				$meta_key = str_replace( '_wp-parser_', '', $meta_key );

				if ( ! isset( $data[ $meta_key ] ) )
					continue;

				if ( in_array( $meta_key, array( 'visibility', 'static' ) ) ) {
					$data[ $meta_key ] = current( $meta_value );
				} else {
					$data[ $meta_key ] = current( array_map( 'maybe_unserialize', maybe_unserialize( $meta_value ) ) );
				}
			}


			if ( 'public' === strtolower( $data['visibility'] ) ) {
				/**
				 * This filter is documented in lib/export/class-classes.php
				 */
				if ( ! apply_filters( 'sublime_export_method_is_global_variable', false, current( explode( '::', $post->post_title ) ) ) ) {
					if ( false === strpos( $post->post_title, '__construct' ) ) {
						$name = sprintf( '${1:%s', str_replace( '::', ( $data['static'] ? '}::' : '}->' ), $post->post_title ) );
					} else {
						$name = sprintf( '${1:\$${2:class} = }new %s', preg_replace( '/^([^:]+)(.*)$/', '$1', $post->post_title ) );
					}
				} else {
					if ( false === strpos( $post->post_title, '__construct' ) ) {
						$name = '\$' . str_replace( '::', ( $data['static'] ? '::' : '->' ), $post->post_title );
					} else {
						return false;
					}
				}

				return array(
					'trigger' => sprintf( "%s\tWP Class Method", str_replace( '::', '-', $post->post_title ) ),
					'contents' => $this->contents( $name, $this->parse_arguments( $data['args'] ) ),
				);
			}
		}

		return false;
	}

	public function contents( $name, $data, $return = null ) {
		$index = substr_count( $name, '${' );
		if ( 1 === count( $data ) && ! isset( $data[0]['childrens'] ) ) {
			if ( false === strpos( $data[0]['name'], 'deprecated' ) ) {
				if ( isset( $data[0]['default_value'] ) ) {
					$arguments = sprintf( '${%s: ${%s:%s} }', ++$index, ++$index, $this->parse_argument_name( $data[0]['default_value'], $index ) );
				} else {
					$arguments = sprintf( ' ${%s:\\%s} ', ++$index, $data[0]['name'] );
				}
			} else {
				$arguments = '';
			}
		} elseif ( count( $data ) ) {
			$first_children = isset( $data[0]['childrens'] );
			$arguments      = $first_children ? sprintf( '${%s: ', ++$index ) : ' ';
			// $index          = $first_children ? 1 : 0;

			foreach ( $data as $key => $arg )
				list( $index, $arguments ) = $this->argument( $arguments, $arg, $index );

			$arguments .= $first_children ? ' }' : ' ';
		} else {
			$arguments = '';
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
		$arguments = apply_filters( 'sublime_export_method_arguments_completion', $arguments, $name, $data );

		$last_index = substr_count( $arguments, '${' );
		$completion = sprintf( '%s(%s)%s', $name, $arguments, ( $return ? sprintf( '${%d:;}', ( empty( $last_index ) ? 1 : ++$last_index ) ) : ';' ) );
		/**
		 * Used for change method completion on export
		 *
		 * @param string $completion      method completion with format snippet
		 * @param string $name            method name
		 * @param array  $arguments       array of arguments
		 *
		 * @return string method completion with format snippet
		 */
		$completion = apply_filters( 'sublime_export_method_content_completion', $completion, $name, $arguments );

		return $completion;
	}

}

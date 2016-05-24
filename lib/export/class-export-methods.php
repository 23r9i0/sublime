<?php
/**
 *
 */

namespace Sublime;

use \WP_CLI;
use \Sublime\Export_Functions as Helper;

class Export_Methods extends Export_Base {

	private $_post_type = 'wp-parser-method';

	public function __construct( $directory = '' ) {
		if ( ! post_type_exists( $this->_post_type ) ) {
			WP_CLI::error( 'Unknown Post Type on Export Classes Methods' );
			exit;
		}

		$args = array(
			'template'  => array( 'comment' => 'WordPress Classes Methods' ),
			'post_type' => $this->_post_type,
			'name'      => 'Methods',
		);

		if ( ! empty( $directory ) )
			$args['directory'] = $directory;

		parent::__construct( $args );
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


			$is_public = ( 'public' === strtolower( $data['visibility'] ) ) ? true : false;
			if ( $is_public ) {
				return array(
					'trigger' => sprintf( "%s\tWP Class Method", str_replace( '::', '-', $post->post_title ) ),
					'contents' => $this->contents(
						sprintf( '${1:%s', str_replace( '::', ( $data['static'] ? '}::' : '}->' ), $post->post_title ) ),
						$this->parse_args( $data['args'] )
					),
				);
			}
		}

		return false;
	}

	public function contents( $name, $data ) {
		if ( 1 === count( $data ) && ! isset( $data[0]['childrens'] ) ) {
			if ( false === strpos( $data[0]['name'], 'deprecated' ) ) {
				if ( isset( $data[0]['default'] ) ) {
					$arguments = sprintf( '${2: ${3:%s} }', $data[0]['default'] );
				} else {
					$arguments = sprintf( ' ${2:\\%s} ', $data[0]['name'] );
				}
			} else {
				$arguments = '';
			}
		} elseif ( count( $data ) ) {
			$arguments = isset( $data[0]['childrens'] ) ? '${2: ' : ' ';

			$index = isset( $data[0]['childrens'] ) ? 2 : 1;
			foreach ( $data as $key => $arg ) {
				$arguments = $this->argument( $arguments, $arg, $index );
				$index++;
			}

			$arguments .= isset( $data[0]['childrens'] ) ? ' }' : ' ';
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

		/**
		 * Used for change method completion on export
		 *
		 * @param string $completion      method completion with format snippet
		 * @param string $name            method name
		 * @param array  $arguments       array of arguments
		 *
		 * @return string method completion with format snippet
		 */
		$completion = apply_filters( 'sublime_export_method_content_completion', sprintf( '%s(%s);', $name, $arguments ), $name, $arguments );

		return $completion;
	}

	public function argument( $arguments, $data, $index ) {
		$name = isset( $data['default'] ) ? $data['default'] : sprintf( '\\%s', $data['name'] );

		if ( false === stripos( $data['name'], 'deprecated' ) ) {
			if ( isset( $data['childrens'] ) ) {
				if ( '${2: ' === $arguments ) {
					$arguments .= sprintf( '${%d:%s}', ++$index, $name );
				} else {
					$arguments .= sprintf( '${%d:, ${%d:%s}', ++$index, ++$index, $name );
				}
			} else {
				if ( ' ' === $arguments ) {
					$arguments .= sprintf( '${%d:%s}', ++$index, $name );
				} else {
					if ( $this->is_optional( $data ) ) {
						$arguments .= sprintf( '${%d:, ${%d:%s}}', ++$index, ++$index, $name );
					} else {
						$arguments .= sprintf( ', ${%d:%s}', ++$index, $name );

					}
				}
			}
		} else {
			$arguments .= sprintf( '%s%s', ( preg_match( '/^(\$\{2: | )/', $arguments ) ? ', ' : '' ), $name );
		}

		if ( isset( $data['childrens'] ) ) {
			foreach ( $data['childrens'] as $children ) {
				$arguments = $this->argument( $arguments, $children, $index );
				$index++;
			}
		} elseif ( $c = preg_match_all('/\$\{([0-9]+):, /', $arguments, $matches, PREG_SET_ORDER ) ) {
			$arguments .= str_repeat( '}', ( $c - 1 ) );
		}

		return $arguments;
	}

	public function parse_args( $arguments, $args = array() ) {
		while ( $current = array_shift( $arguments ) ) {
			if ( $this->is_optional( $current ) ) {
				if ( $last = array_pop( $args ) ) {
					if ( $this->is_optional( $last ) ) {
						if ( isset( $last['childrens'] ) ) {
							$last['childrens'] = $this->parse_args( array( $current ), $last['childrens'] );
							$args[] = $last;
						} else {
							$last['childrens'][] = $current;
							$args[] = $last;
						}
					} else {
						$args[] = $last;
						$args[] = $current;
					}
				} else {
					$args[] = $current;
				}
			} elseif ( $this->is_deprecated( $current ) ) {
				if ( $last = array_pop( $args ) ) {
					if ( isset( $last['childrens'] ) ) {
						$childrens = $last['childrens'];
						$last['no_optional'] = true;
						unset( $last['childrens'] );
						$args = array_merge( $args, array( $last ), $this->revert_childrens( $childrens ), array( $current ) );
					} else {
						$args[] = $last;
						$args[] = $current;
					}
				} else {
					$args[] = $current;
				}
			} else {
				$args[] = $current;
			}
		}

		return $args;
	}

	public function revert_childrens( $childrens ) {
		$args = array();
		foreach ( $childrens as $arg ) {
			if ( isset( $arg['childrens'] ) ) {
				$x = $arg['childrens'];
				unset( $arg['childrens'] );
			}
			$arg['no_optional'] = true;
			$args[] = $arg;

			if ( isset( $x ) )
				$args = array_merge( $args, $this->revert_childrens( $x ) );
		}

		return $args;
	}

	public function is_deprecated( $arg ) {
		return ( false !== stripos( $arg['name'], 'deprecated' ) || ( isset( $arg['desc'] ) && 0 === stripos( $arg['desc'], 'deprecated' ) ) );
	}

	public function is_optional( $arg ) {
		return (
			( isset( $arg['default'] ) || ( isset( $arg['desc'] ) && 0 === stripos( $arg['desc'], 'optional' ) ) ) &&
			! $this->is_deprecated( $arg ) && ! isset( $arg['no_optional'] )
		);
	}
}

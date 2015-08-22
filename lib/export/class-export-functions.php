<?php
/**
 *
 */

namespace Sublime;

use WP_CLI;

class Export_Functions extends Export_Base {

	private $_post_type = 'wp-parser-function';

	public function __construct( $directory = '' ) {
		if ( ! post_type_exists( $this->_post_type ) ) {
			WP_CLI::error( 'Unknown Post Type on Export Functions' );
			exit;
		}

		$args = array(
			'template'  => array( 'comment' => 'WordPress Functions' ),
			'post_type' => $this->_post_type,
			'name'      => 'Functions',
		);

		if ( ! empty( $directory ) )
			$args['directory'] = $directory;

		parent::__construct( $args );
	}

	public function generate_completion( $post ) {
		if ( in_array( $post->post_title, ( array ) apply_filters( 'sublime_exclude_functions', array(), $post->post_title ) ) )
			return false;

		if ( apply_filters( 'sublime_exclude_private_functions', false, $post ) )
			return false;

		$arguments = array();
		setup_postdata( $post );
		$arguments = \WP_Parser\get_arguments();
		wp_reset_postdata();
		$arguments = apply_filters( 'sublime_parse_function_args', $arguments, $post->post_title );
		return array(
			'trigger'   => $post->post_title . "\tWP Function",
			'contents'  => $this->contents( $post->post_title, $this->parse_args( $arguments ), end( $arguments ) ),
			// 'parse'     => $this->parse_args( $arguments ),
			// 'arguments' => $arguments,
		);
	}

	public function contents( $name, $arguments, $last_argument ) {
		if ( empty( $arguments ) ) {
			if ( 0 === strpos( $name, '__return_' ) )
				return apply_filters( 'sublime_parse_function', sprintf( '%s${1:();}', $name ), $name, $arguments );

			return apply_filters( 'sublime_parse_function', sprintf( '%s();', $name ), $name, $arguments );
		}

		return $this->parse_contents( $arguments, $name, $last_argument );
	}

	public function parse_contents( $data, $name, $last_argument ) {
		if ( 1 === count( $data ) && ! isset( $data[0]['childrens'] ) ) {
			if ( false === strpos( $data[0]['name'], 'deprecated' ) ) {
				if ( isset( $data[0]['default_value'] ) ) {
					$arguments = sprintf( '${1: ${2:%s} }', $data[0]['default_value'] );
				} else {
					$arguments = sprintf( ' ${1:\\%s} ', $data[0]['name'] );
				}
			} else {
				$arguments = '';
			}
		} else {
			if ( isset( $data[0]['childrens'] ) ) {
				$arguments = '${1: ';
				$index = 2;
			} else {
				$arguments = ' ';
				$index = 1;
			}
			foreach ( $data as $key => $arg ) {
				$arguments = $this->argument( $arguments, $arg, $index, 0, isset( $data[0]['childrens'] ), $last_argument );
				$index++;
			}

			if ( ! isset( $data[0]['childrens'] ) )
				$arguments .= ' ';
		}

		$arguments = apply_filters( 'sublime_parse_function_contents', $arguments, $name, $data );

		return apply_filters( 'sublime_parse_function', sprintf( '%s(%s);', $name, $arguments ), $name, $arguments );
	}

	public function argument( $arguments, $data, $index, $repeat = 0, $wrap = false, $last_argument ) {
		$name          = isset( $data['default_value'] ) ? $data['default_value'] : sprintf( '\\%s', $data['name'] );
		$first_element = $wrap ? 2 : 1;

		if ( false === stripos( $data['name'], 'deprecated' ) ) {
			if ( isset( $data['childrens'] ) ) {
				if ( $first_element !== $index ) {
					$arguments .= sprintf( '${%d:, ${%d:%s}', $index, ++$index, $name );
				} else {
					$arguments .= sprintf( '${%d:%s}', $index, $name );
				}
			} else {
				if ( $first_element !== $index ) {
					if ( isset( $data['default_value'] ) ) {
						$arguments .= sprintf( '${%d:, ${%d:%s}}', $index, ++$index, $name );
					} else {
						$arguments .= sprintf( ', ${%d:%s}', $index, $name );
					}
				} else {
					$arguments .= sprintf( '${%d:%s}', $index, $name );
				}
			}
		} else {
			if ( $data['name'] !== $last_argument['name'] ) {
				$arguments .= sprintf( '%s%s', ( $first_element !== $index ? ', ' : '' ), $name );
				++$index;
			}
		}

		if ( isset( $data['childrens'] ) ) {
			foreach ( $data['childrens'] as $key => $children ) {
				$arguments = $this->argument( $arguments, $children, ++$index, ++$repeat, $wrap, $last_argument );
			}
		} else {
			if ( $repeat ) {
				if ( $wrap && $data['name'] !== $last_argument['name'] ) {
					$arguments .= str_repeat( '}', $repeat );
				} elseif ( $wrap ) {
					$arguments .= ' }';
				} elseif ( $data['name'] === $last_argument['name'] ) {
					$arguments .= str_repeat( '}', $repeat );
				}
			}
		}

		return $arguments;
	}

	public function parse_args( $arguments, $args = array() ) {
		while ( $current = array_shift( $arguments ) ) {
			$arg = array(
				'name' => $current['name'],
			);

			if ( isset( $current['default_value'] ) )
				$arg['default_value'] = $current['default_value'];

			if ( isset( $current['childrens'] ) )
				$arg['childrens'] = $current['childrens'];

			if ( isset( $arg['default_value'] ) && false === stripos( $arg['name'], 'deprecated' ) ) {
				if ( $last = array_pop( $args ) ) {
					if ( isset( $last['default_value'] ) ) {
						if ( isset( $last['childrens'] ) ) {
							$last['childrens'] = $this->parse_args( array( $arg ), $last['childrens'] );
							$args[] = $last;
						} else {
							$last['childrens'][] = $arg;
							$args[] = $last;
						}
					} else {
						$args[] = $last;
						$args[] = $arg;
					}
				} else {
					$args[] = $arg;
				}
			} else {
				$args[] = $arg;
			}
		}

		return $args;
	}
}

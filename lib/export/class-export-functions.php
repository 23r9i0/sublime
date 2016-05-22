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
		/**
		 * Used for exclude functions
		 *
		 * @param array  list to exclude
		 * @param string current function name
		 */
		$exclude_functions = apply_filters( 'sublime_export_exclude_functions', array(), $post->post_title );
		$exclude_functions = is_array( $exclude_functions ) ? $exclude_functions : array();
		if ( in_array( $post->post_title, $exclude_functions ) )
			return false;

		/**
		 * Used for exclude private functions
		 *
		 * @param bool   true or false for exclude
		 * @param object post
		 *
		 */
		if ( apply_filters( 'sublime_export_exclude_private_functions', false, $post ) )
			return false;

		$arguments = array();
		setup_postdata( $post );
		$arguments = \WP_Parser\get_arguments();
		wp_reset_postdata();

		/**
		 * Used for change defaults arguments
		 *
		 * @param array  $arguments
		 * @param string $name
		 *
		 * @return array $arguments
		 */
		$arguments = apply_filters( 'sublime_export_default_arguments', $arguments, $post->post_title );

		if ( $last = array_pop( $arguments ) ) {
			if ( ! $this->is_deprecated( $last ) ) {
				$arguments[] = $last;
			}
		}

		return array(
			'trigger'   => $post->post_title . "\tWP Function",
			'contents'  => $this->contents( $post->post_title, $this->parse_args( $arguments ) ),
		);
	}

	public function contents( $name, $data ) {
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
		} elseif ( count( $data ) ) {
			$arguments = isset( $data[0]['childrens'] ) ? '${1: ' : ' ';

			$index = isset( $data[0]['childrens'] ) ? 1 : 0;
			foreach ( $data as $key => $arg ) {
				$arguments = $this->argument( $arguments, $arg, $index );
				$index++;
			}

			$arguments .= isset( $data[0]['childrens'] ) ? ' }' : ' ';
		} else {
			$arguments = '';
		}

		/**
		 * Used for change function arguments on export
		 *
		 * @param string $arguments all arguments with format snippet
		 * @param string $name      function name
		 * @param array  $data      array of arguments
		 *
		 * @return string all arguments with format snippet
		 */
		$arguments = apply_filters( 'sublime_export_function_arguments_completion', $arguments, $name, $data );

		/**
		 * Used for change function completion on export
		 *
		 * @param string $completion      function completion with format snippet
		 * @param string $name            function name
		 * @param array  $arguments       array of arguments
		 *
		 * @return string function completion with format snippet
		 */
		$completion = apply_filters( 'sublime_export_function_content_completion', sprintf( '%s(%s);', $name, $arguments ), $name, $arguments );

		return $completion;
	}

	public function argument( $arguments, $data, $index ) {
		$name = isset( $data['default_value'] ) ? $data['default_value'] : sprintf( '\\%s', $data['name'] );

		if ( false === stripos( $data['name'], 'deprecated' ) ) {
			if ( isset( $data['childrens'] ) ) {
				if ( '${1: ' === $arguments ) {
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
			$arguments .= sprintf( '%s%s', ( preg_match( '/^(\$\{1: | )/', $arguments ) ? ', ' : '' ), $name );
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
			( isset( $arg['default_value'] ) || ( isset( $arg['desc'] ) && 0 === stripos( $arg['desc'], 'optional' ) ) ) &&
			! $this->is_deprecated( $arg ) && ! isset( $arg['no_optional'] )
		);
	}
}

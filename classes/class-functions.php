<?php
/**
 * Export Functions
 *
 * @package sublime
 * @subpackage export
 */
namespace Sublime {

	use \Sublime\Exporter;

	class Functions extends Exporter {

		public $post_type = 'wp-parser-function';

		public function __construct( $directory = '' ) {
			parent::__construct( $directory );
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

			$arguments = $this->get_arguments( $post->ID );
			$return    = wp_list_filter( get_post_meta( $post->ID, '_wp-parser_tags', true ), array( 'name' => 'return' ) );

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
				'trigger'  => "{$post->post_title}\tWP Function",
				'contents' => $this->contents( $post->post_title, $this->parse_arguments( $arguments ), ! empty( $return ) ),
			);
		}

		public function contents( $name, $data, $return ) {
			if ( 1 === count( $data ) && ! isset( $data[0]['childrens'] ) ) {
				if ( false === strpos( $data[0]['name'], 'deprecated' ) ) {
					if ( isset( $data[0]['default_value'] ) ) {
						$arguments = sprintf( '${1: ${2:%s} }', $this->parse_argument_name( $data[0]['default_value'], 2 ) );
					} else {
						$arguments = sprintf( ' ${1:\\%s} ', $data[0]['name'] );
					}
				} else {
					$arguments = '';
				}
			} elseif ( count( $data ) ) {
				$first_children = isset( $data[0]['childrens'] );
				$arguments      = $first_children ? '${1: ' : ' ';
				$index          = $first_children ? 1 : 0;

				foreach ( $data as $argument )
					list( $index, $arguments ) = $this->argument( $arguments, $argument, $index );

				$arguments .= $first_children ? ' }' : ' ';
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

			$last_index = substr_count( $arguments, '${' );
			$completion = sprintf( '%s(%s)%s', $name, $arguments, ( $return ? sprintf( '${%d:;}', ( empty( $last_index ) ? 1 : ++$last_index ) ) : ';' ) );
			/**
			 * Used for change function completion on export
			 *
			 * @param string $completion      function completion with format snippet
			 * @param string $name            function name
			 * @param array  $arguments       array of arguments
			 *
			 * @return string function completion with format snippet
			 */
			$completion = apply_filters( 'sublime_export_function_content_completion', $completion, $name, $arguments );

			return $completion;
		}

		public function argument( $arguments, $data, $index ) {
			$name = isset( $data['default_value'] ) ? $data['default_value'] : sprintf( '\\%s', $data['name'] );
			if ( false === stripos( $data['name'], 'deprecated' ) ) {
				if ( isset( $data['childrens'] ) ) {
					if ( '${1: ' === $arguments ) {
						$arguments .= sprintf( '${%d:%s}', ++$index, $this->parse_argument_name( $name, ++$index ) );
					} else {
						$arguments .= sprintf( '${%d:, ${%d:%s}', ++$index, ++$index, $this->parse_argument_name( $name, ++$index ) );
					}
				} else {
					if ( ' ' === $arguments ) {
						$arguments .= sprintf( '${%d:%s}', ++$index, $this->parse_argument_name( $name, ++$index ) );
					} else {
						if ( $this->is_optional( $data ) ) {
							$arguments .= sprintf( '${%d:, ${%d:%s}}', ++$index, ++$index, $this->parse_argument_name( $name, ++$index ) );
						} else {
							$arguments .= sprintf( ', ${%d:%s}', ++$index, $this->parse_argument_name( $name, ++$index ) );
						}
					}
				}

				// Fix index
				if ( $name === $this->parse_argument_name( $name ) )
					$index = ( $index - 1 );

			} else {
				$arguments .= sprintf( '%s%s', ( preg_match( '/^(\$\{1: | )/', $arguments ) ? ', ' : '' ), $name );
			}

			if ( isset( $data['childrens'] ) ) {
				foreach ( $data['childrens'] as $children ) {
					list( $index, $arguments ) = $this->argument( $arguments, $children, $index );
					$index++;
				}
			} elseif ( $repeat = preg_match_all('/\$\{([0-9]+):, /', $arguments, $matches, PREG_SET_ORDER ) ) {
				$arguments .= str_repeat( '}', absint( $repeat - 1 ) );
			}

			return array( $index, $arguments );
		}

		public function parse_argument_name( $value, $index = 0 ) {
			if ( 'array()' === $value ) {
				$value = sprintf( 'array( ${%s:} )', $index );
			} elseif ( false !== strpos( $value, "'" ) ) {
				$value = sprintf( "'\${%s:%s}'", $index, str_replace( "'", '', $value ) );
			}

			return $value;
		}

		public function parse_arguments( $arguments, $args = array() ) {
			while ( $current = array_shift( $arguments ) ) {
				if ( $this->is_optional( $current ) ) {
					if ( $last = array_pop( $args ) ) {
						if ( $this->is_optional( $last ) ) {
							if ( isset( $last['childrens'] ) ) {
								$last['childrens'] = $this->parse_arguments( array( $current ), $last['childrens'] );
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

}

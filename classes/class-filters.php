<?php
/**
 * Export Filters
 *
 * @package sublime
 * @subpackage export
 */
namespace Sublime {

	use \Sublime\Exporter;

	class Filters extends Exporter {

		public $type = 'filter';

		public $post_type = 'wp-parser-hook';

		public function __construct( $directory = '' ) {
			parent::__construct( $directory );
		}

		public function generate_completions() {
			$completions = array();

			if ( $list = parent::generate_completions() ) {
				foreach ( $list as $elements )
					$completions = array_merge( $completions, $elements );
			}

			return $completions;
		}

		public function generate_completion( $post ) {
			$type = get_post_meta( $post->ID, '_wp-parser_hook_type', true );
			if ( empty( $type ) )
				return false;

			if ( 0 !== strncmp( $this->type, $type, strlen( $this->type ) ) )
				return false;

			$arguments  = $this->get_arguments( $post->ID );
			$name       = ucfirst( $this->type );
			$completion = array( array(
				'trigger'   => sprintf( 'add_%s-%s', $this->type, "{$post->post_title}\tWP {$name}" ),
				'contents'  => $this->parse_contents( $post, $arguments ),
			) );

			if ( false === strpos( $post->post_title, '{$' ) ) {
				$completion[] = array(
					'trigger'  => sprintf( "%s\tWP %s Name", $post->post_title, $name ),
					'contents' => $post->post_title,
				);
			}

			return $completion;
		}

		public function parse_contents( $post, $args ) {
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
				// $output .= sprintf( '${%d:, ${%d:10}${%d:, ${%d:%d}}}', ++$index, ++$index, ++$index, ++$index, $accepted_args );
				$output .= sprintf( ', ${%d:10}, %d', ++$index, $accepted_args );
			} else {
				// $output .= sprintf( '${%d:, ${%d:10}}', ++$index, ++$index );
				$output .= sprintf( ', ${%d:10}', ++$index );
			}

			return sprintf( 'add_%s( %s );', $this->type, $output );
		}

		public function parse_name( $post_title ) {
			preg_match_all('/(\{\$)?[^\{\$\}]+(\})?/', $post_title, $matches );
			$matches = array_map( function( $a ) {
				return str_replace( array( '$', '{', '}' ), array( '\$', '' ), $a );
			}, $matches[0] );

			$index = 0;
			$name = '';
			foreach ( $matches as $match ) {
				if ( false !== strpos( $match, '\\' ) ) {
					$name .= sprintf( '${%d:\{${%d:%s}\}}', ++$index, ++$index, $match );
				} else {
					$name .= $match;
				}
			}

			return array( 'name' => sprintf( '"%s"', $name ), 'matches' => $matches, 'index' => $index );
		}

		/**
		 * Return the current arguments.
		 *
		 * @return array array( [0] => array( 'name' => '', 'type' => '', 'desc' => '' ), ... )
		 */
		public function get_arguments( $post_id = 0 ) {
			$args      = get_post_meta( $post_id, '_wp-parser_args', true );
			$params    = wp_list_filter( get_post_meta( $post_id, '_wp-parser_tags', true ), array( 'name' => 'param' ) );
			$arguments = array();

			if ( empty( $args ) ) {
				$args = array();
			}

			foreach ( $args as $arg ) {
				$tag   = array_shift( $params );
				$param = array(
					'name'  => '$(unnamed)',
					'types' => array(),
					'value' => $arg,
				);

				if ( ! empty( $tag['variable'] ) ) {
					$param['name'] = $tag['variable'];
				} elseif ( 0 === strpos( $arg, '$' ) ) {
					$param['name'] = $arg;
				}

				if ( ! empty( $tag['types'] ) ) {
					$param['types'] = $tag['types'];
				}

				if ( ! empty( $tag['content'] ) ) {
					$param['desc'] = $tag['content'];
				}

				$arguments[] = $param;
			}

			return apply_filters( 'wp_parser_get_hook_arguments', $arguments );
		}
	}

}



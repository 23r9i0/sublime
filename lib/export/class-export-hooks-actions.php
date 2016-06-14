<?php
/**
 *
 */

namespace Sublime;

class Export_Hooks_Actions extends Export_Hooks {

	public function __construct( $directory = '' ) {
		$args = array(
			'template' => array( 'comment' => 'WordPress Actions' ),
			'name'     => 'Actions',
		);

		parent::__construct( $directory, $args );
	}

	public function generate_completion( $post ) {
		$hook_type = get_post_meta( $post->ID, '_wp-parser_hook_type', true );
		if ( empty( $hook_type ) )
			return false;

		if ( 0 !== strncmp( 'action', $hook_type, 6 ) )
			return false;

		$arguments = array();
		setup_postdata( $post );
		$arguments = \WP_Parser\get_hook_arguments();
		wp_reset_postdata();

		$completion = array( array(
			'trigger'   => sprintf( 'add_action-%s', $post->post_title . "\tWP Action" ),
			'contents'  => $this->parse_contents( $post, $arguments, 'action' ),
			// 'hook_type' => $hook_type,
			// 'arguments' => $arguments,
		) );

		if ( false === strpos( $post->post_title, '{$' ) ) {
			$completion[] = array(
				'trigger'  => sprintf( "%s\tWP Action Name", $post->post_title ),
				'contents' => $post->post_title,
			);
		}

		++$this->count;

		return $completion;
	}
}

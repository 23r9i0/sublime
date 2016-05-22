<?php
/**
 *
 */

namespace Sublime;

use WP_CLI;

class Export_Classes extends Export_Base {

	private $_post_type = 'wp-parser-class';

	public function __construct( $directory = '' ) {
		if ( ! post_type_exists( $this->_post_type ) ) {
			WP_CLI::error( 'Unknown Post Type on Export Classes' );
			exit;
		}

		$args = array(
			'template'  => array( 'comment' => 'WordPress Classes' ),
			'post_type' => $this->_post_type,
			'name'      => 'Classes',
		);

		if ( ! empty( $directory ) )
			$args['directory'] = $directory;

		parent::__construct( $args );
	}

	public function generate_completion( $post ) {
		return array(
				'trigger' => sprintf( '%s\tWP Class', $post->post_title ),
				'contents' => $post->post_title
			);
	}
}

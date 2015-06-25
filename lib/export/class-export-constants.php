<?php
/**
 *
 */

namespace Sublime;

use WP_CLI;

class Export_Constants extends Export_Base {

	private $_post_type = 'wp-parser-constant';

	public function __construct( $directory = '' ) {
		if ( ! post_type_exists( $this->_post_type ) ) {
			WP_CLI::error( 'Unknown Post Type on Export Constants' );
			exit;
		}

		$args = array(
			'template' => array( 'comment' => 'WordPress Constants' ),
			'post_type' => $this->_post_type,
			'name' => 'Constants',
		);

		if ( ! empty( $directory ) )
			$args['directory'] = $directory;

		parent::__construct( $args );
	}

	public function generate_completion( $post ) {
		return array( 'trigger' => "{$post->post_title}\tWP Constant", 'contents' => $post->post_title );
	}
}
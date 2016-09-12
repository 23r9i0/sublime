<?php
/**
 * Export Constants
 *
 * @package sublime
 * @subpackage export
 */
namespace Sublime {

	use \Sublime\Exporter;

	class Constants extends Exporter {

		public $post_type = 'wp-parser-constant';

		public function __construct( $directory = '' ) {
			parent::__construct( $directory );
		}

		public function generate_completion( $post ) {
			return array( 'trigger' => "{$post->post_title}\tWP Constant", 'contents' => $post->post_title );
		}
	}

}

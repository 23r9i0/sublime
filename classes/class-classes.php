<?php
/**
 * Export Classes
 *
 * @package sublime
 * @subpackage export
 */
namespace Sublime {

	use \Sublime\Exporter;

	class Classes extends Exporter {

		public $post_type = 'wp-parser-class';

		public function __construct( $directory = '' ) {
			parent::__construct( $directory );
		}

		public function generate_completion( $post ) {
			return array(
				'trigger' => sprintf( "%s\tWP Class Name", $post->post_title ),
				'contents' => $post->post_title
			);
		}
	}

}

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
			/**
			 * Use for define class if global variable
			 *
			 * e.g.: $wpdb
			 */
			if ( ! apply_filters( 'sublime_export_method_is_global_variable', false, current( explode( '::', $post->post_title ) ) ) ) {
				$name = $post->post_title;
			} else {
				$name = sprintf( 'global %s;', $post->post_title );
			}

			return array(
				'trigger' => sprintf( "%s\tWP Class Name", $post->post_title ),
				'contents' => $name
			);
		}
	}

}

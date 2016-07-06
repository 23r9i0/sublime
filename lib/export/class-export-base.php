<?php
/**
 *
 */

namespace Sublime;

use WP_CLI;

class Export_Base {

	public $count = 0;

	public $base_template = array(
		'scope'       => 'source.php - variable.other.php',
		'comment'     => '',
		'completions' => array(),
	);

	protected $elements = array();

	public function __construct( array $args ) {
		foreach ( $args as $name => $value )
			$this->{$name} = $value;

		if ( ! isset( $this->name ) )
			$this->name = 'Undefined';

		$this->template = wp_parse_args( $this->template, $this->base_template );

		if ( ! empty( $this->directory ) ) {
			$this->directory = trailingslashit( $this->directory );
		} else {
			$this->directory = plugin_dir_path( dirname( __DIR__ ) ) . 'package/';
		}

		if ( ! is_dir( $this->directory ) )
			mkdir( $this->directory, 0777, true );

		if ( isset( $this->post_type ) )
			$this->elements = $this->_get_posts_data( $this->post_type );
	}

	public function generate() {
		$completions = $this->generate_completions();

		if ( count( $completions ) ) {
			$this->template['comment'] = sprintf( '%s %s', $this->count, $this->template['comment'] );
			$this->template['completions'] = $completions;
			/**
			 * Used for change default scope
			 */
			$this->template['scope'] = apply_filters( 'sublime_export_change_scope', $this->template['scope'], $this->name );
			$file = $this->generate_completions_file( array(
				'name' => $this->name,
				'data' => $this->template
			) );

			if ( $file )
				return $this->count;
		}

		return false;
	}

	private function _get_posts_data( $post_type ) {
		return get_posts( array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'wp-parser-package',
					'field'    => 'slug',
					'terms'    => array(
						'php'
					),
					'operator' => 'NOT IN',
				)
			),
		) );
	}

	public function generate_completions() {
		$completions = array();
		$posts       = array();

		if ( count( $this->elements ) ) {
			global $post;
			foreach ( $this->elements as $post ) {
				if ( ! isset( $posts[ $post->post_type ] ) )
					$posts[ $post->post_type ] = array();

				if ( ! in_array( $post->post_title, $posts[ $post->post_type ] ) ) {
					$posts[ $post->post_type ][] = $post->post_title;
					if ( $completion = $this->generate_completion( $post ) ) {
						$completions[] = $completion;
						++$this->count;
					}
				} else {
					WP_CLI::line( sprintf( 'Duplicate element: %s', $post->post_title ) );
				}
			}
		}

		return $completions;
	}

	public function generate_completion( $data ) {
		return false;
	}

	public function generate_completions_file( array $args ) {
		$defaults = array(
			'data' => array(),
			'name' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['name'] ) || empty( $args['data'] ) || ! is_array( $args['data'] ) )
			return false;

		if ( ! isset( $args['data']['completions'] ) )
			return false;

		$data = json_encode( $args['data'], JSON_PRETTY_PRINT );

		if ( ! $data )
			return false;

		$filename = $this->directory . strtolower( $args['name'] ) . '.sublime-completions';
		if ( file_put_contents( $filename, $data ) ) {
			return true;
		}

		return false;
	}
}

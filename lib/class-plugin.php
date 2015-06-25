<?php

namespace Sublime;

class Plugin extends \WP_Parser\Plugin {

	public function __construct() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'subl', __NAMESPACE__ . '\\Command' );
		}

		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ), 12 );
		add_action( 'init', array( $this, 'register_metaboxes' ), 13 );
	}

	public function register_post_types() {
		parent::register_post_types();

		$supports = array(
			'comments',
			'custom-fields',
			'editor',
			'excerpt',
			'revisions',
			'title',
		);

		if ( ! post_type_exists( 'wp-parser-constant' ) ) {

			register_post_type(
				'wp-parser-constant',
				array(
					'has_archive' => 'constants',
					'label'       => __( 'Constants' ),
					'public'      => true,
					'rewrite'     => array(
						'feeds'      => false,
						'slug'       => 'constant',
						'with_front' => false,
					),
					'supports'    => $supports,
				)
			);
		}
	}

	public function register_taxonomies() {
		parent::register_taxonomies();

		foreach ( get_object_taxonomies( 'wp-parser-function' ) as $taxonomy ) {
			register_taxonomy_for_object_type( $taxonomy, 'wp-parser-constant' );
		}
	}

	public function register_metaboxes() {
		$post_types = get_post_types( array( '_builtin' => false ) );
		$supports_to_remove = array(
			'comments',
			'custom-fields',
		);
		foreach ( $post_types as $post_type ) {
			foreach ( $supports_to_remove as $support ) {
				remove_post_type_support( $post_type, $support );
			}

			add_action( "add_meta_boxes_{$post_type}", array( $this, 'add_meta_boxes' ) );
		}
	}

	public function add_meta_boxes( $post ) {
		add_meta_box(
			'sublime_meta',
			__( 'Informations' ),
			array( $this, 'render_meta_boxes_content' ),
			$post->post_type
		);
	}

	public function render_meta_boxes_content( $post ) {
		if ( in_array( $post->post_type, array( 'wp-parser-function', 'wp-parser-hook' ) ) )
			$arguments = ( 'wp-parser-hook' === $post->post_type ) ? \WP_Parser\get_hook_arguments() : \WP_Parser\get_arguments();

		echo '<pre>';
		var_dump($arguments);
		echo '</pre>';
	}
}
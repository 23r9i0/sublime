<?php
/**
 * Main Plugin Class
 *
 * - Register Post types
 * - Register Taxonomies
 * - Remove unneccesary post type support
 *
 * @package sublime
 */
namespace Sublime {

	use \WP_CLI;

	class Plugin extends \WP_Parser\Plugin {

		public function __construct() {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::add_command( 'subl', __NAMESPACE__ . '\\Command' );
			}

			add_action( 'init', array( $this, 'register_post_types' ), 10 );
			add_action( 'init', array( $this, 'register_taxonomies' ), 10 );
			add_action( 'init', array( $this, 'remove_post_type_support' ), 10 );
		}

		public function register_post_types() {
			parent::register_post_types();

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
						'supports'    => array( 'editor', 'excerpt', 'title' ),
					)
				);
			}
		}

		public function register_taxonomies() {
			parent::register_taxonomies();

			foreach ( get_object_taxonomies( 'wp-parser-function' ) as $taxonomy )
				register_taxonomy_for_object_type( $taxonomy, 'wp-parser-constant' );
		}

		public function remove_post_type_support() {
			foreach ( get_post_types( array( '_builtin' => false ) ) as $post_type ) {
				if ( 0 === strpos( $post_type, 'wp-parser-' ) ) {
					remove_post_type_support( $post_type, 'comments' );
					remove_post_type_support( $post_type, 'custom-fields' );
					remove_post_type_support( $post_type, 'revisions' );
				}
			}
		}
	}

}



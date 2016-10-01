<?php
/**
 * Base Class for generate export
 *
 * @package sublime
 * @subpackage export
 */
namespace Sublime {

	use \WP_CLI;
	use \WP_CLI\Utils;

	class Exporter {

		public $count = 0;

		public $directory;

		public $elements = array();

		public $name;

		public $post_type;

		public $messages;

		public $template = array();


		public static function run( $directory = '', $update_readme = false ) {
			$export = new static( $directory );
			if ( $output = $export->generate() ) {
				WP_CLI::success( str_replace( array( '**', "\n" ), '', current( $output ) ) );
				if ( $update_readme )
					$export->update_readme( $directory, $export->name, $output );
			} else {
				WP_CLI::error( sprintf( 'Processing %s', $export->name ) );
			}
		}

		public function __construct( $directory = '' ) {
			$this->name = ucfirst( str_replace( array( __NAMESPACE__, '\\' ), '', get_called_class() ) );

			if ( isset( $this->post_type ) && ! post_type_exists( $this->post_type ) ) {
				WP_CLI::error( sprintf( 'Unknown Post Type %s on Export %s', $this->post_type, $this->name ) );
				exit;
			}

			$this->template = wp_parse_args( $this->template, array(
				'scope'       => 'source.php - variable.other.php',
				'comment'     => sprintf( 'WordPress - %s', $this->name ),
				'completions' => array(),
			) );

			$directory = empty( $directory ) ? plugin_dir_path( dirname( __DIR__ ) ) : $directory;
			$this->directory = trailingslashit( $directory ) . 'completions/';

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
					return $this->get_messages();
			}

			return false;
		}

		protected function _get_posts_data( $post_type ) {
			return get_posts( array(
				'post_type' => $post_type,
				'nopaging'  => true,
				'orderby'   => 'title',
				'order'     => 'ASC',
			) );
		}

		public function generate_completions() {
			$completions = $posts = array();

			if ( count( $this->elements ) ) {
				$progress = \WP_CLI\Utils\make_progress_bar( "Generating {$this->name} completions:", count( $this->elements ) );
				foreach ( $this->elements as $post ) {
					if ( ! isset( $posts[ $post->post_type ] ) )
						$posts[ $post->post_type ] = array();

					if ( ! in_array( $post->post_title, $posts[ $post->post_type ] ) ) {
						$posts[ $post->post_type ][] = $post->post_title;
						if ( $completion = $this->generate_completion( $post ) ) {
							$completions[] = $completion;
							$this->update_messages( $post->ID );
							++$this->count;
						}
					}

					$progress->tick();
				}
				$progress->finish();
			}

			return $completions;
		}

		public function generate_completion( $data ) {
			return false;
		}

		public function generate_completions_file( array $args ) {
			$args = wp_parse_args( $args, array( 'data' => array(), 'name' => '' ) );

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
				$tag = wp_list_filter( $params, array( 'variable' => $arg['name'] ) );
				$tag = array_shift( $tag );
				$param     = array(
					'name'  => $arg['name'],
					'types' => array(),
				);

				if ( ! empty( $arg['default'] ) ) {
					$param['default_value'] = $arg['default'];
				}

				if ( ! empty( $arg['type'] ) ) {
					$param['types'] = array( $arg['type'] );
				} else if ( ! empty( $tag['types'] ) ) {
					$param['types'] = $tag['types'];
				}

				if ( ! empty( $tag['content'] ) ) {
					$param['desc'] = $tag['content'];
				}

				$arguments[] = $param;
			}

			return apply_filters( 'wp_parser_get_arguments', $arguments );
		}

		public function update_messages( $post_id ) {
			$object_terms = wp_get_object_terms( $post_id, 'wp-parser-source-file' );
			if ( is_wp_error( $object_terms ) )
				return;

			foreach ( $object_terms as $term ) {
				if ( preg_match( '/^wp-content\/(plugins|themes)\/([^\/]+)(.*)$/', $term->name, $matches ) ) {
					list( $name, $version ) = $this->get_data_from_file( $matches[1], $matches[2] );
				} else {
					$name = 'WordPress';
					$version = get_option( 'wp_parser_imported_wp_version', 'unknown' );
				}

				$this->messages[ $name ] = array(
					'type'        => ( isset( $matches[1] ) ? ucfirst( substr( $matches[1], 0, -1 ) ) : 'Core' ),
					'package'     => $name,
					'version'     => $version,
					'completions' => ( empty( $this->messages[ $name ]['completions'] ) ? 1 : ( $this->messages[ $name ]['completions'] + 1 ) ),
				);

				if ( isset( $matches ) )
					unset( $matches );
			}
		}

		public function get_messages() {
			$messages_header = array( 'Total' => "{$this->name} Completions: **{$this->count}**" );

			return array_merge( $messages_header, $this->messages );
		}

		public function get_data_from_file( $type, $name ) {
			if ( 'plugins' === $type ) {
				static $plugins = null;
				if ( is_null( $plugins ) )
					$plugins = get_plugins();

				foreach ( $plugins as $plugin_file => $plugin_data ) {
					if ( 0 === strpos( $plugin_file, $name ) && isset( $plugin_data['Name'], $plugin_data['Version'] ) ) {
						return array( $plugin_data['Name'], $plugin_data['Version'] );
					}
				}
			} elseif ( 'themes' === $type ) {
				static $themes = null;
				if ( is_null( $themes ) )
					$themes = wp_get_themes();

				if ( isset( $themes[ $name ] ) && $themes[ $name ] instanceof \WP_Theme ) {
					return array( $themes[ $name ]->get( 'Name' ), $themes[ $name ]->get( 'Version' ) );
				}
			}

			return array( $name, 'unknown' );
		}

		public function update_readme( $directory = '', $name = '', $output = array() ) {
			if ( empty( $directory ) || empty( $name ) || empty( $output ) )
				WP_CLI::error( 'Not is possible update Readme.md' );

			if ( ! is_dir( $directory ) )
				WP_CLI::error( "Directory not exists" );

			$readme = trailingslashit( $directory ) . 'Readme.md';

			if ( false === stream_resolve_include_path( $readme ) )
				WP_CLI::error( 'The Readme.md file not exists' );

			if ( false === ( $content = file_get_contents( $readme ) ) )
				WP_CLI::error( 'Some problem occurred when extracting the file contents on Readme file.' );

			$content = explode( '### ', $content );
			foreach ( $content as $kc => $section ) {
				if ( 0 === strpos( $section, 'Completions List' ) ) {
					$sub_content = explode( "\n", $section );
					foreach ( $sub_content as $ksc => $sub_section ) {
						if ( 0 === strpos( $sub_section, "| {$name}" ) ) {
							$length = array_map( function( $l ) {
								return strlen( $l );
							}, explode( ' | ', $sub_section ) );
							$row = explode( '|', str_replace( 'Completions:', '|', current( $output ) ) );
							foreach ( $row as $kr => $r ) {
								if ( $length[ $kr ] > strlen( $r ) )
									$row[ $kr ] = $r . str_repeat( ' ', ( $length[ $kr ] - strlen( $r ) - 1 ) );
							}

							$sub_content[ $ksc ] = '| ' . implode( '|', $row ) . ' |';
						}
					}
					$content[ $kc ] = implode( "\n", $sub_content );
				}
			}

			file_put_contents( $readme, implode( '### ', $content ) );
			return;

			$wiki = trailingslashit( $directory ) . 'wiki/Home.md';
			if ( false === stream_resolve_include_path( $wiki ) )
				WP_CLI::error( 'The Home.md file not exists' );

			if ( false === ( $content = file_get_contents( $wiki ) ) )
				WP_CLI::error( 'Some problem occurred when extracting the file contents on wiki page.' );

			$content = preg_split( '/^## /m', $content );
			foreach ( $content as $kc => $section ) {
				if ( 0 === strpos( $section, 'Completions List' ) ) {
					$sub_content = explode( '### ', $section );
					foreach ( $sub_content as $ksc => $sub_section ) {
						if ( 0 === strpos( $sub_section, $name ) ) {
							$sub_content[ $ksc ] = implode( "\n", $this->generate_wiki( $output ) );
						}
					}
					$content[ $kc ] = implode( '### ', $sub_content );
				}
			}

			file_put_contents( $wiki, implode( '## ', $content ) );

		}

		public function generate_wiki( $data ) {

			$total = array_shift( $data );
			$length = $headers = array();

			$data = array_map( function( $d ) {
				if ( isset( $d['completions'] ) )
					$d['completions'] = "**{$d['completions']}**";

				return $d;
			}, $data );

			foreach ( $data as $d ) {
				foreach ( $d as $header => $content ) {
					if ( empty( $headers[ $header ] ) )
						$headers[ $header ] = ucwords( $header );

					if ( empty( $length[ $header ] ) )
						$length[ $header ] = strlen( $header );
				}
			}

			foreach ( $data as $d ) {
				foreach ( $d as $header => $content ) {
					$len = strlen( $content );
					if ( $len > $length[ $header ] )
						$length[ $header ] = $len;
				}
			}


			foreach ( $data as $k => $d ) {
				foreach ( $d as $header => $content ) {
					$len = strlen( $content );
					if ( $length[ $header ] > $len )
						$data[ $k ][ $header ] = $content . str_repeat( ' ', ( $length[ $header ] - $len ) );
				}
			}

			$separator = array();
			foreach ( $headers as $header => $content ) {
				$len = strlen( $content );
				if ( $length[ $header ] > $len )
					$headers[ $header ] = $content . str_repeat( ' ', ( $length[ $header ] - $len ) );

				$separator[ $header ] = str_repeat( '-', $length[ $header ] );
			}

			$readme = array( $total, '' );
			if ( ! empty( $headers ) ) {
				$readme[] = '| ' . implode( ' | ', $headers ) . ' |';
				$readme[] = '| ' . implode( ' | ', $separator ) . ' |';
			}

			/**
			 * Because php not sorting WordPress Themes
			 * this filter does it
			 *
			 * Note: use WordPress Themes name, e.g Twenty Ten
			 *
			 * @var array
			 */
			$sort = apply_filters( 'sublime_readme_table_sort_themes', array() );
			$sort = is_array( $sort ) ? $sort : array();

			// if ( ! empty( $sort ) ) {
			// 	$new_order = array();

			// 	foreach ( $data as $order => $content ) {
			// 		if ( ! in_array( $order, $sort ) )
			// 			$new_order[ $order ] = $content;
			// 	}

			// 	foreach ( $sort as $order ) {
			// 		foreach ( $data as $name => $content ) {
			// 			if ( $order === $name )
			// 				$new_order[ $name ] = $content;
			// 		}
			// 	}

			// 	$data = $new_order;
			// }


			foreach ( $data as $d ) {
				$readme[] = '| ' . implode( ' | ', $d ) . ' |';
			}

			if ( ! empty( $headers ) )
				$readme[] = "\n";

			return $readme;
		}
	}

}

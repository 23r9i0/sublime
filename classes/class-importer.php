<?php
/**
 *
 */
namespace Sublime {

	use \RecursiveDirectoryIterator;
	use \RecursiveIteratorIterator;
	use \WP_Error;
	use \WP_Parser\File_Reflector;
	use \WP_Parser\WP_CLI_Logger;

	class Importer extends \WP_Parser\Importer {

		public $post_type_constant;

		public static function run( array $data, $skip_sleep = false, $import_ignored = false ) {
			$importer = new Importer;
			$importer->setLogger( new WP_CLI_Logger );
			$importer->import( $data, $skip_sleep, $import_ignored );
		}

		public static function set_parser_phpdoc( $directory ) {
			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) );
			$list  = array();
			/**
			 * Include Plugins by name folder, by default Exclude Plugins Folder
			 */
			$include_plugins = apply_filters( 'sublime_include_plugins', array() );
			$include_plugins = is_array( $include_plugins ) ? $include_plugins : array( $include_plugins );

			/**
			 * Exclude files to import
			 *
			 * Posible values:
			 * File name, eg. wp-config.php
			 * Path, eg. /wp-admin/includes/noop.php
			 */
			$exclude_files = apply_filters( 'sublime_exclude_files', array() );
			$exclude_files = is_array( $exclude_files ) ? $exclude_files : array( $exclude_files );

			try {
				foreach ( $files as $file ) {
					if ( 'php' !== $file->getExtension() )
						continue;

					$path = wp_normalize_path( $file->getPathname() );

					$exclude = array_filter( $exclude_files, function( $exclude ) use ( $path ) {
						return preg_match( '/' . preg_quote( $exclude, '/' ) . '$/', $path );
					} );

					if ( $exclude )
						continue;

					if ( false !== strpos( $path, 'wp-content/plugins/sublime/missing' ) ) {
						$list[] = $path;
					} elseif ( false !== strpos( $path, 'wp-content/plugins' ) ) {
						foreach ( $include_plugins as $include_plugin ) {
							if ( false !== strpos( $path, $include_plugin ) ) {
								$list[] = $path;
							}
						}
					} else {
						$list[] = $path;
					}
				}

			} catch ( \UnexceptedValueException $e ) {
				return new WP_Error(
					'unexcepted_value_exception',
					sprintf( 'Directory [%s] contained a directory we can not recurse into', $directory )
				);
			}

			return $list;
		}

		public static function get_parser_phpdoc( $files, $root ) {
			$output = array();

			// Fix WordPress Themes constants
			// Move all files inside wp-content folder to first elements
			$list = array();
			foreach ( $files as $k => $file ) {
				if ( false !== strpos( $file, 'wp-content' ) ) {
					$list[] = $file;
					unset( $files[ $k ] );
				}
			}
			$files = array_merge( $list, $files );

			foreach ( $files as $filename ) {
				$file = new File_Reflector( $filename );

				$file->setFilename( ltrim( substr( $filename, strlen( $root ) ), '/' ) );
				$file->process();

				// TODO proper exporter
				$out = array(
					'file' => \WP_Parser\export_docblock( $file ),
					'path' => $file->getFilename(),
					'root' => $root,
				);

				if ( ! empty( $file->uses ) ) {
					$out['uses'] = \WP_Parser\export_uses( $file->uses );
				}

				foreach ( $file->getIncludes() as $include ) {
					$out['includes'][] = array(
						'name' => $include->getName(),
						'line' => $include->getLineNumber(),
						'type' => $include->getType(),
					);
				}

				foreach ( $file->getConstants() as $constant ) {
					$out['constants'][] = array(
						'name'  => $constant->getShortName(),
						'line'  => $constant->getLineNumber(),
						'value' => $constant->getValue(),
						'doc'   => \WP_Parser\export_docblock( $constant ),
					);
				}

				if ( ! empty( $file->uses['hooks'] ) ) {
					$out['hooks'] = \WP_Parser\export_hooks( $file->uses['hooks'] );
				}

				foreach ( $file->getFunctions() as $function ) {
					$func = array(
						'name'      => $function->getShortName(),
						'line'      => $function->getLineNumber(),
						'end_line'  => $function->getNode()->getAttribute( 'endLine' ),
						'arguments' => \WP_Parser\export_arguments( $function->getArguments() ),
						'doc'       => \WP_Parser\export_docblock( $function ),
						'hooks'     => array(),
					);

					if ( ! empty( $function->uses ) ) {
						$func['uses'] = \WP_Parser\export_uses( $function->uses );

						if ( ! empty( $function->uses['hooks'] ) ) {
							$func['hooks'] = \WP_Parser\export_hooks( $function->uses['hooks'] );
						}
					}

					$out['functions'][] = $func;
				}

				foreach ( $file->getClasses() as $class ) {
					$class_data = array(
						'name'       => $class->getShortName(),
						'line'       => $class->getLineNumber(),
						'end_line'   => $class->getNode()->getAttribute( 'endLine' ),
						'final'      => $class->isFinal(),
						'abstract'   => $class->isAbstract(),
						'extends'    => $class->getParentClass(),
						'implements' => $class->getInterfaces(),
						'properties' => \WP_Parser\export_properties( $class->getProperties() ),
						'methods'    => \WP_Parser\export_methods( $class->getMethods() ),
						'doc'        => \WP_Parser\export_docblock( $class ),
					);

					$out['classes'][] = $class_data;
				}

				$output[] = $out;
			}

			return $output;
		}

		public function __construct( array $args = array() ) {
			parent::__construct( wp_parse_args( $args, array( 'post_type_constant' => 'wp-parser-constant' ) ) );
		}

		/**
		 * Import the PHPDoc $data into WordPress posts and taxonomies
		 *
		 * @param array $data
		 * @param bool  $skip_sleep               Optional; defaults to false. If true, the sleep() calls are skipped.
		 * @param bool  $import_ignored_functions Optional; defaults to false. If true, functions marked `@ignore` will be imported.
		 */
		public function import( array $data, $skip_sleep = false, $import_ignored_functions = false ) {
			parent::import( $data, $skip_sleep, $import_ignored_functions );

			// Delete empty taxonomies
			global $wpdb;

			if ( $terms = $wpdb->get_results( "SELECT tt.term_id, tt.taxonomy FROM wp_term_taxonomy AS tt WHERE tt.taxonomy LIKE '%wp-parser-%' AND tt.count = 0" ) ) {
				$progress = \WP_CLI\Utils\make_progress_bar( 'Deleting empty taxonomies.', count( $terms ) );
				foreach ( $terms as $term ) {
					wp_delete_term( $term->term_id, $term->taxonomy );
					$progress->tick();
				}
				$progress->finish();
			}
		}

		/**
		 * For a specific file, go through and import the file, functions, and classes.
		 *
		 * @param array $file
		 * @param bool  $skip_sleep     Optional; defaults to false. If true, the sleep() calls are skipped.
		 * @param bool  $import_ignored Optional; defaults to false. If true, functions and classes marked `@ignore` will be imported.
		 */
		public function import_file( array $file, $skip_sleep = false, $import_ignored = false ) {

			/**
			 * Filter whether to proceed with importing a prospective file.
			 *
			 * Returning a false value to the filter will short-circuit processing of the import file.
			 *
			 * @param bool  $display         Whether to proceed with importing the file. Default true.
			 * @param array $file            File data
			 */
			if ( ! apply_filters( 'wp_parser_pre_import_file', true, $file ) )
				return;

			// Maybe add this file to the file taxonomy
			$slug = sanitize_title( str_replace( '/', '_', $file['path'] ) );

			$term = $this->insert_term( $file['path'], $this->taxonomy_file, array( 'slug' => $slug ) );

			if ( is_wp_error( $term ) ) {
				$this->errors[] = sprintf( 'Problem creating file tax item "%1$s" for %2$s: %3$s', $slug, $file['path'], $term->get_error_message() );
				return;
			}

			// Detect deprecated file
			$deprecated_file = false;
			if ( isset( $file['uses']['functions'] ) ) {
				$first_function = $file['uses']['functions'][0];

				// If the first function in this file is _deprecated_function
				if ( '_deprecated_file' === $first_function['name'] ) {
					// Set the deprecated flag to the version number
					$deprecated_file = $first_function['deprecation_version'];
				}
			}

			/**
			 * Force to define deprecated file
			 * Use for not import and delete post if exists
			 */
			if ( ! $deprecated_file  ) {
				$deprecated_regex_files = array(
					'wp-config',        // Exclude in case exists personal data
					'wp-config-backup', // plugin wp-viewer-log generate copy of wp-config.php
					'wp-config-sample', // Exclude in case exists all definitions already exists in others files
					'wp-admin\/includes\/(ms-)?deprecated', // wp admin deprecated files
					'wp-admin\/includes\/noop', // ?; Some functions, not is defined explications but if defined in others files or if phpdoc tag @ignore
					'wp-includes\/compat', // wp php functions
					'wp-includes\/([^\/]+)?deprecated', // wp deprecated files
					'wp-includes\/random_compat\/.*', // PHP 7 support
					'wp-includes\/theme-compat\/(comments|header|footer|sidebar)', // wp themes compat deprecated files
					'wp-content\/themes\/([^\/]+)\/inc\/back-compat', // Themes wp ( functions|actions for internal uses, delevopers not use )
				);

				$deprecated_regex_files = '/(' . implode( '|', $deprecated_regex_files ) . ')\.php$/';

				if ( preg_match( $deprecated_regex_files, $file['path'] ) ) {
					$deprecated_file = true;
				}
			}

			// Store file meta for later use
			$this->file_meta = array(
				'docblock'   => $file['file'], // File docblock
				'term_id'    => $file['path'], // Term name in the file taxonomy is the file name
				'deprecated' => $deprecated_file, // Deprecation status
			);

			// TODO ensures values are set, but better handled upstream later
			$file = array_merge( array(
				'functions' => array(),
				'classes'   => array(),
				'hooks'     => array(),
				'constants' => array(),
			), $file );

			$count = 0;

			foreach ( $file['functions'] as $function ) {
				$this->import_function( $function, 0, $import_ignored );
				$count++;

				if ( ! $skip_sleep && 0 == $count % 10 ) { // TODO figure our why are we still doing this
					sleep( 3 );
				}
			}

			foreach ( $file['classes'] as $class ) {
				$this->import_class( $class, $import_ignored );
				$count++;

				if ( ! $skip_sleep && 0 == $count % 10 ) {
					sleep( 3 );
				}
			}

			foreach ( $file['hooks'] as $hook ) {
				$this->import_hook( $hook, 0, $import_ignored );
				$count++;

				if ( ! $skip_sleep && 0 == $count % 10 ) {
					sleep( 3 );
				}
			}

			foreach ( $file['constants'] as $constant ) {
				$this->import_constant( $constant, 0, $import_ignored );
				$count++;

				if ( ! $skip_sleep && 0 == $count % 10 ) {
					sleep( 3 );
				}
			}

			if ( 'wp-includes/version.php' === $file['path'] ) {
				$this->import_version( $file );
			}
		}

		public function import_constant( array $data, $parent_post_id = 0, $import_ignored = false ) {
			/**
			 * Exclude some Constants for various reasons
			 */
			if ( apply_filters( 'sublime_import_exclude_constant', false, $data['name'] ) )
				return false;

			$data = wp_parse_args( $data, array(
				'arguments' => isset( $data['value'] ) ? $data['value'] : array(),
				'line'      => '',
			) );

			$data['end_line']  = isset( $data['end_line'] ) ? $data['end_line'] : $data['line'];

			$constant_id = $this->import_item( $data, $parent_post_id, $import_ignored, array( 'post_type' => $this->post_type_constant ) );

			return $constant_id;
		}

		/**
		 * Create a post for a hook
		 *
		 * @param array $data           Hook.
		 * @param int   $parent_post_id Optional; post ID of the parent (function) this item belongs to. Defaults to zero (no parent).
		 * @param bool  $import_ignored Optional; defaults to false. If true, hooks marked `@ignore` will be imported.
		 * @return bool|int Post ID of this hook, false if any failure.
		 */
		public function import_hook( array $data, $parent_post_id = 0, $import_ignored = false ) {
			/**
			 * Filter whether to skip parsing duplicate hooks.
			 *
			 * "Duplicate hooks" are characterized in WordPress core by a preceding DocBlock comment
			 * including the phrases "This action is documented in" or "This filter is documented in".
			 *
			 * Passing a truthy value will skip the parsing of duplicate hooks.
			 *
			 * @param bool $skip Whether to skip parsing duplicate hooks. Default true.
			 */
			$skip_duplicates = apply_filters( 'wp_parser_skip_duplicate_hooks', true );

			if ( $skip_duplicates ) {
				/**
				 * Check external by example Akimet plugin not use WordPress DocBlock comment
				 */
				if ( apply_filters( 'sublime_skip_duplicate_by_name', false, $data ) )
					return false;

				/**
				 * Use regex beacuse some hooks not use WordPress DockBlock comment
				 */
				if ( preg_match( '/^This (filter|action) is documented in/', $data['doc']['description'] ) )
					return false;

				// ? Disabled akismet not use DocBlock comment
				//
				// if ( '' === $data['doc']['description'] && '' === $data['doc']['long_description'] )
				// 	return false;
			}

			$hook_id = $this->import_item( $data, $parent_post_id, $import_ignored, array( 'post_type' => $this->post_type_hook ) );

			if ( ! $hook_id ) {
				return false;
			}

			update_post_meta( $hook_id, '_wp-parser_hook_type', $data['type'] );

			return $hook_id;
		}

		/**
		 * Create a post for an item (a class or a function).
		 *
		 * Anything that needs to be dealt identically for functions or methods should go in this function.
		 * Anything more specific should go in either import_function() or import_class() as appropriate.
		 *
		 * @param array $data           Data.
		 * @param int   $parent_post_id Optional; post ID of the parent (class or function) this item belongs to. Defaults to zero (no parent).
		 * @param bool  $import_ignored Optional; defaults to false. If true, functions or classes marked `@ignore` will be imported.
		 * @param array $arg_overrides  Optional; array of parameters that override the defaults passed to wp_update_post().
		 *
		 * @return bool|int Post ID of this item, false if any failure.
		 */
		public function import_item( array $data, $parent_post_id = 0, $import_ignored = false, array $arg_overrides = array() ) {
			/** @var \wpdb $wpdb */
			global $wpdb;

			$is_new_post = true;
			$slug        = sanitize_title( str_replace( '::', '-', $data['name'] ) );
			$post_data   = wp_parse_args(
				$arg_overrides,
				array(
					'post_content' => $data['doc']['long_description'],
					'post_excerpt' => $data['doc']['description'],
					'post_name'    => $slug,
					'post_parent'  => (int) $parent_post_id,
					'post_status'  => 'publish',
					'post_title'   => $data['name'],
					'post_type'    => $this->post_type_function,
				)
			);
			$post_type_name_to_log = substr( $post_data['post_type'], 10 );

			// Don't import items marked @ignore
			if ( wp_list_filter( $data['doc']['tags'], array( 'name' => 'ignore' ) ) ) {

				$indent = ( $parent_post_id ) ? "\t\t" : "\t";
				// $this->logger->info( $indent . sprintf( 'Skipped importing @ignore-d %s "%s"', $post_type_name_to_log, $data['name'] ) );

				global $wpdb;
				if ( $ignore = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s", $slug, $post_data['post_type'] ) ) ) {
					$this->logger->info( $indent . sprintf( 'Deleting @ignore-d %s "%s"', $post_type_name_to_log, $data['name'] ) );
					foreach ( $ignore as $ignore_post_id ) {
						if ( wp_list_filter( get_post_meta( $ignore_post_id, '_wp-parser_tags', true ), array( 'name' => 'ignore' ) ) ) {
							if ( ! wp_delete_post( $ignore_post_id, true ) ) {
								$this->errors[] = $indent . sprintf( 'Problem deleting @ignore-d post for %s "%s"', $post_type_name_to_log, $data['name'] );
							}
						}
					}
				}

				return false;
			}

			/**
			 * Filter whether to proceed with adding/updating a prospective import item.
			 *
			 * Returning a falsey value to the filter will short-circuit addition of the import item.
			 *
			 * @param bool  $display         Whether to proceed with adding/updating the import item. Default true.
			 * @param array $data            Data
			 * @param int   $parent_post_id  Optional; post ID of the parent (class or function) this item belongs to. Defaults to zero (no parent).
			 * @param bool  $import_ignored  Optional; defaults to false. If true, functions or classes marked `@ignore` will be imported.
			 * @param array $arg_overrides   Optional; array of parameters that override the defaults passed to wp_update_post().
			 */
			if ( ! apply_filters( 'wp_parser_pre_import_item', true, $data, $parent_post_id, $import_ignored, $arg_overrides ) ) {
				return false;
			}

			// Look for an existing post for this item
			$existing_post_id = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s ORDER BY post_date ASC", $slug, $post_data['post_type']
			) );

			// If the file or this items is deprecated not import, if exists deleted
			if ( $this->file_meta['deprecated'] || wp_list_filter( $data['doc']['tags'], array( 'name' => 'deprecated' ) ) ) {
				if ( $existing_post_id ) {
					$indent = ( $parent_post_id ) ? "\t\t" : "\t";
					$this->logger->info( $indent . sprintf( 'Deleting deprecated %s "%s"', $post_type_name_to_log, $data['name'] ) );
					$deleted_with_error = array();
					foreach ( $existing_post_id as $exists_post_id ) {
						if ( $this->file_meta['deprecated'] || wp_list_filter( get_post_meta( $exists_post_id, '_wp-parser_tags', true ), array( 'name' => 'deprecated' ) ) ) {
							if ( ! wp_delete_post( $exists_post_id, true ) ) {
								$deleted_with_error[] = $exists_post_id;
							}
						}
					}
					if ( $deleted_with_error )
						$this->errors[] = $indent . sprintf( 'Problem deleting deprecated post for %s "%s"', $post_type_name_to_log, $data['name'] );
				}

				return false;
			}

			/**
			 * Filter an import item's post data before it is updated or inserted.
			 *
			 * @param array   $post_data          Array of post data.
			 * @param array   $existing_post_id   ID if the post already exists, empty otherwise.
			 */
			$post_data = apply_filters( 'wp_parser_import_item_post_data', $post_data, $existing_post_id );

			// Insert/update the item post
			if ( ! empty( $existing_post_id ) ) {
				$is_new_post        = false;
				$post_id            = $post_data['ID'] = (int) array_shift( $existing_post_id );
				$post_needed_update = array_diff_assoc( sanitize_post( $post_data, 'db' ), get_post( $post_id, ARRAY_A, 'db' ) );

				if ( $existing_post_id ) {
					$indent = ( $parent_post_id ) ? "\t\t" : "\t";
					$this->errors[] = $indent . sprintf( 'Possible Duplicate posts of the %s "%s"', $post_type_name_to_log, $data['name'] );
				}

				if ( $post_needed_update ) {
					$post_id = wp_update_post( wp_slash( $post_data ), true );
				}
			} else {
				$post_id = wp_insert_post( wp_slash( $post_data ), true );
			}

			$anything_updated = array();

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				$indent = ( $parent_post_id ) ? "\t\t" : "\t";
				$this->errors[] = $indent . sprintf( 'Problem inserting/updating post for %s "%s"', $post_type_name_to_log, $data['name'] );

				return false;
			}

			// If the item has @since markup, assign the taxonomy
			$since_versions = wp_list_filter( $data['doc']['tags'], array( 'name' => 'since' ) );
			if ( ! empty( $since_versions ) ) {

				// Loop through all @since versions.
				foreach ( $since_versions as $since_version ) {

					if ( ! empty( $since_version['content'] ) ) {
						$since_term = $this->insert_term( $since_version['content'], $this->taxonomy_since_version );

						// Assign the tax item to the post
						if ( ! is_wp_error( $since_term ) ) {
							$added_term_relationship = did_action( 'added_term_relationship' );
							wp_set_object_terms( $post_id, (int) $since_term['term_id'], $this->taxonomy_since_version, true );
							if ( did_action( 'added_term_relationship' ) > $added_term_relationship ) {
								$anything_updated[] = true;
							}
						} else {
							$this->logger->warning( "\tCannot set @since term: " . $since_term->get_error_message() );
						}
					}
				}
			}

			$packages = array(
				'main' => wp_list_filter( $data['doc']['tags'], array( 'name' => 'package' ) ),
				'sub'  => wp_list_filter( $data['doc']['tags'], array( 'name' => 'subpackage' ) ),
			);

			// If the @package/@subpackage is not set by the individual function or class, get it from the file scope
			if ( empty( $packages['main'] ) ) {
				$packages['main'] = wp_list_filter( $this->file_meta['docblock']['tags'], array( 'name' => 'package' ) );
			}

			if ( empty( $packages['sub'] ) ) {
				$packages['sub'] = wp_list_filter( $this->file_meta['docblock']['tags'], array( 'name' => 'subpackage' ) );
			}

			$main_package_id   = false;
			$package_term_ids = array();

			// If the item has any @package/@subpackage markup (or has inherited it from file scope), assign the taxonomy.
			foreach ( $packages as $pack_name => $pack_value ) {

				if ( empty( $pack_value ) ) {
					continue;
				}

				$pack_value = array_shift( $pack_value );
				$pack_value = $pack_value['content'];

				$package_term_args = array( 'parent' => 0 );
				// Set the parent term_id to look for, as the package taxonomy is hierarchical.
				if ( 'sub' === $pack_name && is_int( $main_package_id ) ) {
					$package_term_args = array( 'parent' => $main_package_id );
				}

				// If the package doesn't already exist in the taxonomy, add it
				$package_term = $this->insert_term( $pack_value, $this->taxonomy_package, $package_term_args );
				$package_term_ids[] = (int) $package_term['term_id'];

				if ( 'main' === $pack_name && false === $main_package_id && ! is_wp_error( $package_term ) ) {
					$main_package_id = (int) $package_term['term_id'];
				}

				if ( is_wp_error( $package_term ) ) {
					if ( is_int( $main_package_id ) ) {
						$this->logger->warning( "\tCannot create @subpackage term: " . $package_term->get_error_message() );
					} else {
						$this->logger->warning( "\tCannot create @package term: " . $package_term->get_error_message() );
					}
				}
			}
			$added_term_relationship = did_action( 'added_term_relationship' );
			wp_set_object_terms( $post_id, $package_term_ids, $this->taxonomy_package );
			if ( did_action( 'added_term_relationship' ) > $added_term_relationship ) {
				$anything_updated[] = true;
			}

			// Set other taxonomy and post meta to use in the theme templates
			$added_item = did_action( 'added_term_relationship' );
			wp_set_object_terms( $post_id, $this->file_meta['term_id'], $this->taxonomy_file );
			if ( did_action( 'added_term_relationship' ) > $added_item ) {
				$anything_updated[] = true;
			}

			if ( $post_data['post_type'] !== $this->post_type_class ) {
				$anything_updated[] = update_post_meta( $post_id, '_wp-parser_args', $data['arguments'] );
			}
			$anything_updated[] = update_post_meta( $post_id, '_wp-parser_line_num', (string) $data['line'] );
			$anything_updated[] = update_post_meta( $post_id, '_wp-parser_end_line_num', (string) $data['end_line'] );
			$anything_updated[] = update_post_meta( $post_id, '_wp-parser_tags', $data['doc']['tags'] );

			// If the post didn't need to be updated, but meta or tax changed, update it to bump last modified.
			if ( ! $is_new_post && ! $post_needed_update && array_filter( $anything_updated ) ) {
				wp_update_post( wp_slash( $post_data ), true );
			}

			$action = $is_new_post ? 'Imported' : 'Updated';
			$indent = ( $parent_post_id ) ? "\t\t" : "\t";
			$this->logger->info( "{$indent}{$action} {$post_type_name_to_log} \"{$data['name']}\"" );

			/**
			 * Action at the end of importing an item.
			 *
			 * @param int   $post_id   Optional; post ID of the inserted or updated item.
			 * @param array $data PHPDoc data for the item we just imported
			 * @param array $post_data WordPress data of the post we just inserted or updated
			 */
			do_action( 'wp_parser_import_item', $post_id, $data, $post_data );

			return $post_id;
		}
	}

}
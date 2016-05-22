<?php

namespace Sublime;


class Importer extends \WP_Parser\Importer {

	public $post_type_constant;

	public function __construct( array $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'post_type_constant' => 'wp-parser-constant',
			)
		);

		parent::__construct( $args );
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
		 * Returning a falsey value to the filter will short-circuit processing of the import file.
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

		// Detect back compat file, use $deprecated_file to delete
		if ( ! $deprecated_file ) {
			/**
			 * Exclude Core PHP7 back compat functions
			 *
			 * @param bool  $exclude   Default true.
			 */
			$php7_random_compat = apply_filters( 'sublime_exclude_PHP7_back_compat', true );
			if (
				false !== strpos( $file['file']['description'], 'back compat functionality' ) || // themes back-compat.php
				false !== strpos( $file['path'], 'wp-includes/compat.php' ) || // Core back compat
				( false !== strpos( $file['path'], 'wp-includes/random_compat' ) && $php7_random_compat ) // Core PHP7 back compat
			) {
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
			$count ++;

			if ( ! $skip_sleep && 0 == $count % 10 ) { // TODO figure our why are we still doing this
				sleep( 3 );
			}
		}

		foreach ( $file['classes'] as $class ) {
			$this->import_class( $class, $import_ignored );
			$count ++;

			if ( ! $skip_sleep && 0 == $count % 10 ) {
				sleep( 3 );
			}
		}

		foreach ( $file['hooks'] as $hook ) {
			$this->import_hook( $hook, 0, $import_ignored );
			$count ++;

			if ( ! $skip_sleep && 0 == $count % 10 ) {
				sleep( 3 );
			}
		}

		foreach ( $file['constants'] as $constant ) {
			$this->import_constant( $constant, 0, $import_ignored );
			$count ++;

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

		// Don't import items marked @ignore
		if ( wp_list_filter( $data['doc']['tags'], array( 'name' => 'ignore' ) ) ) {

			switch ( $post_data['post_type'] ) {
				case $this->post_type_class:
					$this->logger->info( "\t" . sprintf( 'Skipped importing @ignore-d class "%1$s"', $data['name'] ) );
					break;

				case $this->post_type_method:
					$this->logger->info( "\t\t" . sprintf( 'Skipped importing @ignore-d method "%1$s"', $data['name'] ) );
					break;

				case $this->post_type_hook:
					$indent = ( $parent_post_id ) ? "\t\t" : "\t";
					$this->logger->info( $indent . sprintf( 'Skipped importing @ignore-d hook "%1$s"', $data['name'] ) );
					break;

				case $this->post_type_constant:
					$indent = ( $parent_post_id ) ? "\t\t" : "\t";
					$this->logger->info( $indent . sprintf( 'Skipped importing @ignore-d constant "%1$s"', $data['name'] ) );
					break;

				default:
					$this->logger->info( "\t" . sprintf( 'Skipped importing @ignore-d function "%1$s"', $data['name'] ) );
			}

			return false;
		}

		if ( wp_list_filter( $data['doc']['tags'], array( 'name' => 'ignore' ) ) ) {
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
		$existing_post_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_parent = %d LIMIT 1",
			$slug,
			$post_data['post_type'],
			(int) $parent_post_id
		) );

		// If the file or this items is deprecated not import, if exists deleted
		if ( $this->file_meta['deprecated'] || wp_list_filter( $data['doc']['tags'], array( 'name' => 'deprecated' ) ) ) {
			if ( $existing_post_id ) {
				switch ( $post_data['post_type'] ) {
					case $this->post_type_class:
						$this->logger->info( "\t" . sprintf( 'Deleting class "%s"', $data['name'] ) );
						break;

					case $this->post_type_hook:
						$indent = ( $parent_post_id ) ? "\t\t" : "\t";
						$this->logger->info( $indent . sprintf( 'Deleting hook "%s"', $data['name'] ) );
						break;

					case $this->post_type_method:
						$this->logger->info( "\t\t" . sprintf( 'Deleting method "%s"', $data['name'] ) );
						break;

					case $this->post_type_constant:
						$indent = ( $parent_post_id ) ? "\t\t" : "\t";
						$this->logger->info( $indent . sprintf( 'Deleting constant "%s"', $data['name'] ) );
						break;

					default:
						$this->logger->info( "\t" . sprintf( 'Deleting function "%s"', $data['name'] ) );
				}

				if ( ! $delete_post_id = wp_delete_post( $existing_post_id, true ) ) {
					switch ( $post_data['post_type'] ) {
						case $this->post_type_class:
							$this->errors[] = "\t" . sprintf( 'Problem deleting post for class "%s"', $data['name'] );
							break;

						case $this->post_type_method:
							$this->errors[] = "\t\t" . sprintf( 'Problem deleting post for method "%s"', $data['name'] );
							break;

						case $this->post_type_hook:
							$indent = $parent_post_id ? "\t\t" : "\t";
							$this->errors[] = $indent . sprintf( 'Problem deleting post for hook "%s"', $data['name'] );
							break;

						case $this->post_type_constant:
							$indent = ( $parent_post_id ) ? "\t\t" : "\t";
							$this->errors[] = $indent . sprintf( 'Problem deleting post for constant "%s"', $data['name'] );
							break;

						default:
							$this->errors[] = "\t" . sprintf( 'Problem deleting post for function "%s"', $data['name'] );
					}
				}
			}

			return false;
		}

		/**
		 * Filter an import item's post data before it is updated or inserted.
		 *
		 * @param array       $post_data        Array of post data.
		 * @param string|null $existing_post_id ID if the post already exists, null otherwise.
		 */
		$post_data = apply_filters( 'wp_parser_import_item_post_data', $post_data, $existing_post_id );

		// Insert/update the item post
		if ( ! empty( $existing_post_id ) ) {
			$is_new_post     = false;
			$post_id = $post_data['ID'] = (int) $existing_post_id;
			$post_needed_update = array_diff_assoc( sanitize_post( $post_data, 'db' ), get_post( $existing_post_id, ARRAY_A, 'db' ) );
			if ( $post_needed_update ) {
				$post_id = wp_update_post( wp_slash( $post_data ), true );
			}
		} else {
			$post_id = wp_insert_post( wp_slash( $post_data ), true );
		}
		$anything_updated = array();

		if ( ! $post_id || is_wp_error( $post_id ) ) {

			switch ( $post_data['post_type'] ) {
				case $this->post_type_class:
					$this->errors[] = "\t" . sprintf( 'Problem inserting/updating post for class "%s"', $data['name'] );
					break;

				case $this->post_type_method:
					$this->errors[] = "\t\t" . sprintf( 'Problem inserting/updating post for method "%s"', $data['name'] );
					break;

				case $this->post_type_hook:
					$indent = $parent_post_id ? "\t\t" : "\t";
					$this->errors[] = $indent . sprintf( 'Problem inserting/updating post for hook "%s"', $data['name'] );
					break;

				case $this->post_type_constant:
					$indent = ( $parent_post_id ) ? "\t\t" : "\t";
					$this->errors[] = $indent . sprintf( 'Problem inserting/updating post for constant "%s"', $data['name'] );
					break;

				default:
					$this->errors[] = "\t" . sprintf( 'Problem inserting/updating post for function "%s"', $data['name'] );
			}

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

		switch ( $post_data['post_type'] ) {
			case $this->post_type_class:
				$this->logger->info( "\t" . sprintf( '%1$s class "%2$s"', $action, $data['name'] ) );
				break;

			case $this->post_type_hook:
				$indent = ( $parent_post_id ) ? "\t\t" : "\t";
				$this->logger->info( $indent . sprintf( '%1$s hook "%2$s"', $action, $data['name'] ) );
				break;

			case $this->post_type_method:
				$this->logger->info( "\t\t" . sprintf( '%1$s method "%2$s"', $action, $data['name'] ) );
				break;

			case $this->post_type_constant:
				$this->logger->info( "\t" . sprintf( '%1$s constant "%2$s"', $action, $data['name'] ) );
				break;

			default:
				$this->logger->info( "\t" . sprintf( '%1$s function "%2$s"', $action, $data['name'] ) );
		}

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

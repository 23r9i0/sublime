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

	public function import_file( array $file, $skip_sleep = false, $import_ignored = false ) {
		if ( ! apply_filters( 'wp_parser_pre_import_file', true, $file ) )
			return;

		parent::import_file( $file, $skip_sleep, $import_ignored );

		if ( count( $this->errors ) ) {
			if ( false !== strpos( end( $this->errors ), 'Problem creating file tax item' ) ) {
				return;
			}
		}

		if ( isset( $file['constants'] ) && count( $file['constants'] ) ) {
			foreach ( $file['constants'] as $constant ) {
				$this->import_constant( $constant, 0, $import_ignored );
			}
		}
	}

	protected function import_constant( array $data, $parent_post_id = 0, $import_ignored = false ) {
		if ( apply_filters( 'sublime_exclude_constant', false, $data['name'] ) )
			return false;

		$constant_id = $this->import_item( $data, $parent_post_id, $import_ignored, array( 'post_type' => $this->post_type_constant ) );

		return $constant_id;
	}

	public function import_item( array $data, $parent_post_id = 0, $import_ignored = false, array $arg_overrides = array() ) {
		if ( ! $import_ignored && wp_list_filter( $data['doc']['tags'], array( 'name' => 'ignore' ) ) ) {
			if ( isset( $arg_overrides['post_type'] ) && 'wp-parser-constant' === $arg_overrides['post_type'] ) {
				$this->logger->info( "\t" . sprintf( 'Skipped importing @ignore-d constant "%1$s"', $data['name'] ) );
				return false;
			}
		}

		if ( wp_list_filter( $data['doc']['tags'], array( 'name' => 'ignore' ) ) ) {
			return false;
		}

		if ( ! apply_filters( 'wp_parser_pre_import_item', true, $data, $parent_post_id, $import_ignored, $arg_overrides ) ) {
			return false;
		}

		if ( isset( $arg_overrides['post_type'] ) && 'wp-parser-constant' === $arg_overrides['post_type'] ) {
			$data['arguments'] = isset( $data['value'] ) ? $data['value'] : array();
			$data['line'] = ( isset( $data['line'] ) ) ? $data['line'] : '';
			$data['end_line'] = ( isset( $data['end_line'] ) ) ? $data['end_line'] : $data['line'];
		}

		if ( false === $post_id = parent::import_item( $data, $parent_post_id, $import_ignored, $arg_overrides ) ) {
			if ( isset( $arg_overrides['post_type'] ) && 'wp-parser-constant' === $arg_overrides['post_type'] ) {
				$last_error = array_pop( $this->errors );
				$last_error = str_replace( 'function', 'constant', $last_error );
				$this->errors[] = $last_error;
			}

		} elseif ( isset( $arg_overrides['post_type'] ) && 'wp-parser-constant' === $arg_overrides['post_type'] ) {
			$this->logger->info( "\t" . sprintf( 'Before function "%1$s" is constant', $data['name'] ) );
			sleep(1);
		}

		return $post_id;
	}
}

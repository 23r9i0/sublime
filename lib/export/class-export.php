<?php
/**
 *
 */

namespace Sublime;

use WP_CLI;

class Export {

	private $_types = array(
		'constants',
		'capabilities',
		'functions',
		'hooks',
		'actions',
		'filters',
		'classes',
	);

	private $_type = 'all';

	private $_errors = array();

	public function __construct( $directory, $type = 'all' ) {
		if ( in_array( $type, $this->_types ) )
			$this->_type = $type;

		$this->directory = $directory;
	}

	public function get_errors() {
		return $this->_errors;
	}

	public function process() {
		if ( 'hooks' === $this->_type ) {
			foreach ( array( 'actions', 'filters' ) as $hook ) {
				$this->_process( $hook );
			}
		} elseif ( 'all' === $this->_type ) {
			foreach ( $this->_types as $type ) {
				if ( 'hooks' === $type )
					continue;

				$this->_process( $type );
			}
		} else {
			$this->_process( $this->_type );
		}
	}

	private function _process( $type = '' ) {
		WP_CLI::line( sprintf( 'Processing %s.... please wait!!!', ucfirst( $type ) ) );

		switch ( $type ) {
			case 'capabilities':
				$capabilities = new Export_Capabilities( $this->directory );
				if ( ! $capabilities->generate() ) {
					$this->_errors[] = 'Error Processing Capabilities. :(';
				} else {
					WP_CLI::line( 'Created Capabilities Completions.' );
				}
				break;

			case 'constants':
				$constants = new Export_Constants( $this->directory );
				if ( ! $constants->generate() ) {
					$this->_errors[] = 'Error Processing Constants. :(';
				} else {
					WP_CLI::line( 'Created Constants Completions.' );
				}
				break;

			case 'classes':
				$classes = new Export_Classes( $this->directory );
				if ( ! $classes->generate() ) {
					$this->_errors[] = 'Error Processing Classes. :(';
				} else {
					WP_CLI::line( 'Created Classes Completions.' );
				}
				break;

			case 'functions':
				$functions = new Export_Functions( $this->directory );
				if ( ! $functions->generate() ) {
					$this->_errors[] = 'Error Processing Functions. :(';
				} else {
					WP_CLI::line( 'Created Functions Completions.' );
				}
				break;

			case 'actions':
				$actions = new Export_Hooks_Actions( $this->directory );
				if ( ! $actions->generate() ) {
					$this->_errors[] = 'Error Processing Actions. :(';
				} else {
					WP_CLI::line( 'Created Actions Completions' );
				}
				break;

			case 'filters':
				$filters = new Export_Hooks_Filters( $this->directory );
				if ( ! $filters->generate() ) {
					$this->_errors[] = 'Error Processing Filters. :(';
				} else {
					WP_CLI::line( 'Created Filters Completions' );
				}
				break;

			default:
				break;
		}
	}
}

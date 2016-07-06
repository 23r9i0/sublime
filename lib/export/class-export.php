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
		'methods',
	);

	private $_type = 'all';

	private $_errors = array();

	public function __construct( $directory, $type = 'all' ) {
		$this->directory = $directory;

		if ( in_array( $type, $this->_types ) )
			$this->_type = $type;
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
				if ( 'hooks' !== $type )
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
				if ( false === ( $count = $capabilities->generate() ) ) {
					$this->_errors[] = 'Error Processing Capabilities. :(';
				} else {
					WP_CLI::line( sprintf( 'Created %s Capabilities Completions.', $count ) );
				}
				break;

			case 'constants':
				$constants = new Export_Constants( $this->directory );
				if ( false === ( $count = $constants->generate() ) ) {
					$this->_errors[] = 'Error Processing Constants. :(';
				} else {
					WP_CLI::line( sprintf( 'Created %s Constants Completions.', $count ) );
				}
				break;

			case 'classes':
				$classes = new Export_Classes( $this->directory );
				if ( false === ( $count = $classes->generate() ) ) {
					$this->_errors[] = 'Error Processing Classes. :(';
				} else {
					WP_CLI::line( sprintf( 'Created %s Classes Completions.', $count ) );
				}
				break;

			case 'methods':
				$methods = new Export_Methods( $this->directory );
				if ( false === ( $count = $methods->generate() ) ) {
					$this->_errors[] = 'Error Processing Methods. :(';
				} else {
					WP_CLI::line( sprintf( 'Created %s Methods Completions.', $count ) );
				}
				break;

			case 'functions':
				$functions = new Export_Functions( $this->directory );
				if ( false === ( $count = $functions->generate() ) ) {
					$this->_errors[] = 'Error Processing Functions. :(';
				} else {
					WP_CLI::line( sprintf( 'Created %s Functions Completions.', $count ) );
				}
				break;

			case 'actions':
				$actions = new Export_Hooks_Actions( $this->directory );
				if ( false === ( $count = $actions->generate() ) ) {
					$this->_errors[] = 'Error Processing Actions. :(';
				} else {
					WP_CLI::line( sprintf( 'Created %s Actions Completions', $count ) );
				}
				break;

			case 'filters':
				$filters = new Export_Hooks_Filters( $this->directory );
				if ( false === ( $count = $filters->generate() ) ) {
					$this->_errors[] = 'Error Processing Filters. :(';
				} else {
					WP_CLI::line( sprintf( 'Created %s Filters Completions', $count ) );
				}
				break;

			default:
				break;
		}
	}
}

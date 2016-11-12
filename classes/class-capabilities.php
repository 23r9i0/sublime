<?php
/**
 * Export WordPress Capabilities
 *
 * Note: user_level is Deprecated and not include
 *
 * Note: if exists custom Roles with capabilites or custom capabilities
 * 		 in WordPress Instalation for generate Capabilities Completions
 * 		 this capabilities append in completions.
 *
 * 		 Important:
 * 		 Ensure if not exists before run this Export
 * 		 for create Standard WordPress Completions
 * 		 Thanks
 *
 * @package sublime
 * @subpackage export
 */
namespace Sublime;

use \Sublime\Exporter;
use \WP_Roles;

class Capabilities extends Exporter {

	public function __construct( $directory = '' ) {
		parent::__construct( $directory );
	}

	public function get_capabilities() {
		$wp_roles = new WP_Roles();
		/**
		 * List of capabilities
		 *
		 * By default set 'Super Admin' and 'Special Cases' Capabilities
		 *
		 * @see https://codex.wordpress.org/Roles_and_Capabilities
		 *
		 * @var array
		 */
		$capabilities = array(
			'manage_network',         // Super Admin
			'manage_network_options', // Super Admin
			'manage_network_plugins', // Super Admin
			'manage_network_themes',  // Super Admin
			'manage_network_users',   // Super Admin
			'manage_sites',           // Super Admin
			'unfiltered_upload',      // Special Case
		);

		foreach ( (array) $wp_roles->roles as $role => $details ) {
			if ( ! isset( $details['capabilities'] ) )
				continue;

			// Get capabilities and remove all level_* capability
			$current_capabilities = array_filter( array_keys( $details['capabilities'] ), function( $capability ) {
				return ( false === strpos( $capability, 'level_' ) );
			} );

			foreach ( $current_capabilities as $capability ) {
				if ( ! in_array( $capability, $capabilities ) ) {
					$capabilities[] = $capability;

				}
			}
		}

		sort( $capabilities );

		return $capabilities;
	}

	public function generate_completions() {
		$completions  = array();
		$capabilities = $this->get_capabilities();
		$progress     = \WP_CLI\Utils\make_progress_bar( "Generating {$this->name} completions:", count( $capabilities ) );

		foreach ( $capabilities as $capability ) {
			$completions[] = array( 'trigger' => "{$capability}\tWP Capability", 'contents' => $capability );
			++$this->count;
			$this->update_messages();
			$progress->tick();
		}
		$progress->finish();

		return $completions;
	}

	public function update_messages( $post_id = null ) {
		if ( empty( $this->messages['WordPress'] ) ) {
			$this->messages['WordPress'] = array(
				'type'        => 'Core',
				'package'     => 'WordPress',
				'version'     => get_option( 'wp_parser_imported_wp_version', 'unknown' ),
				'completions' => $this->count,
			);
		} else {
			$this->messages['WordPress']['completions'] = $this->count;
		}
	}
}

<?php
/**
 *
 */

namespace Sublime;

class Export_Capabilities extends Export_Base {

	private $_wp_roles;

	private $_super_admin_capabilities = array(
		'manage_network',
		'manage_sites',
		'manage_network_users',
		'manage_network_plugins',
		'manage_network_themes',
		'manage_network_options',
	);

	public function __construct( $directory = '' ) {
		$wp_roles = new WP_Roles();
		$this->_wp_roles = $wp_roles->get_roles();

		$args = array(
			'template' => array( 'comment' => 'WordPress Capabilities' ),
			'name' => 'Capabilities',
		);

		if ( ! empty( $directory ) )
			$args['directory'] = $directory;

		parent::__construct( $args );
	}

	public function generate_completions() {
		$completions = $contents = array();

		$is_super_admin_exists = 0;
		if ( isset( $this->_wp_roles ) && count( $this->_wp_roles ) ) {
			foreach ( $this->_wp_roles as $name => $capabilities ) {
				foreach ( $capabilities as $key => $capability ) {
					$contents = array_column( $completions, 'contents' );

					if ( ! in_array( $capability, $contents ) ) {
						$completions[] = array( 'trigger' => "{$capability}\tWP Capability", 'contents' => $capability );
					}

					if ( in_array( $capability, $this->_super_admin_capabilities ) )
						$is_super_admin_exists++;
				}
			}

			$contents = array_column( $completions, 'contents' );

			/**
			 * Add Capability 'unfiltered_upload' if missing in $completions
			 *
			 * @see https://codex.wordpress.org/Roles_and_Capabilities#Special_Cases
			 */

			if ( ! in_array( 'unfiltered_upload', $contents ) ) {
				$completions[] = array( 'trigger' => "unfiltered_upload\tWP Capability", 'contents' => 'unfiltered_upload' );
			}

			/**
			 * Add Super Admin Capabilities if missing in $completions
			 * if not Multisite WP_Roles not return Super Admin Capabilities
			 *
			 */
			if ( $is_super_admin_exists !== count( $this->_super_admin_capabilities ) ) {
				foreach ( $this->_super_admin_capabilities as $capability ) {
					if ( ! in_array( $capability, $contents ) ) {
						$completions[] = array( 'trigger' => "{$capability}\tWP Capability", 'contents' => $capability );
					}
				}
			}
		}

		return $completions;
	}
}
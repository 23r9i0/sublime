<?php
/**
 * Extend WP_Roles Class for get current Capabilities in WordPress Instalation
 *
 * Note: remove user_level is Deprecated
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
 *
 * @since 1.0
 */

namespace Sublime;

class WP_Roles extends \WP_Roles {

	/**
	 * Initialize \WP_Roles
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Get Roles and Capabilities in \WP_Roles
	 *
	 * @return array
	 */
	public function get_roles() {
		if ( is_array( $this->roles ) )
			return $this->_get_roles();

		return array();
	}

	/**
	 * Get Capabilities for use in Export Roles Class
	 *
	 * @return array
	 */
	private function _get_roles() {
		$roles = array();

		foreach ( $this->roles as $role_slug => $role ) {
			$roles[ $role_slug ] = array_filter( array_keys( $role['capabilities'] ), function( $capability ) {
					return ( false === strpos( $capability, 'level_' ) );
			} );
		}

		return $roles;
	}
}

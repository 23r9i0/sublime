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
		 * The following capabilities are manually defined
		 * as they are not set by default in roles.
		 *
		 * Extracted and compared from the following files:
		 * - wp-includes/capabilities.php
		 * - wp-admin/includes/schema.php
		 *
		 * Last revision 02/11/2020
		 *
		 * @var array
		 */
		$capabilities = array(
			'activate_plugin',
			'add_comment_meta',
			'add_post_meta',
			'add_term_meta',
			'add_user_meta',
			'add_users',
			'assign_categories',
			'assign_post_tags',
			'assign_term',
			'assign_terms',
			'create_sites',
			'customize',
			'deactivate_plugin',
			'deactivate_plugins',
			'delete_blocks',
			'delete_categories',
			'delete_comment_meta',
			'delete_others_blocks',
			'delete_page',
			'delete_post',
			'delete_post_meta',
			'delete_post_tags',
			'delete_private_blocks',
			'delete_published_blocks',
			'delete_site',
			'delete_sites',
			'delete_term',
			'delete_term_meta',
			'delete_user',
			'delete_user_meta',
			'edit_blocks',
			'edit_categories',
			'edit_comment',
			'edit_comment_meta',
			'edit_css',
			'edit_others_blocks',
			'edit_page',
			'edit_post',
			'edit_post_meta',
			'edit_post_tags',
			'edit_private_blocks',
			'edit_published_blocks',
			'edit_term',
			'edit_term_meta',
			'edit_user',
			'edit_user_meta',
			'erase_others_personal_data',
			'export_others_personal_data',
			'install_languages',
			'manage_network',
			'manage_network_options',
			'manage_network_plugins',
			'manage_network_themes',
			'manage_network_users',
			'manage_post_tags',
			'manage_privacy_options',
			'manage_sites',
			'promote_user',
			'publish_blocks',
			'publish_post',
			'read_page',
			'read_post',
			'read_private_blocks',
			'remove_user',
			'resume_plugin',
			'resume_plugins',
			'resume_theme',
			'resume_themes',
			'setup_network',
			'update_languages',
			'update_php',
			'upgrade_network',
			'upload_plugins',
			'upload_themes',
			'view_site_health_checks',
		);

		foreach ( (array) $wp_roles->roles as $role => $details ) {
			if ( ! isset( $details['capabilities'] ) )
				continue;

			// Get capabilities and remove all level_* capability
			$current_capabilities = array_filter( array_keys( $details['capabilities'] ), function( $capability ) {
				return ( false === strpos( $capability, 'level_' ) );
			} );

			foreach ( $current_capabilities as $capability ) {
				if ( ! in_array( $capability, $capabilities, true ) ) {
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

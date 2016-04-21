<?php
/**
 * Initializes the Invitations skeleton component.
 *
 * @package BuddyPress
 * @subpackage Invitations
 * @since 2.6.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Extends the component class to set up the Invitations component.
 */
class BP_Invitations_Component extends BP_Component {

	/**
	 * Start the invitations component creation process.
	 *
	 * @since 2.6.0
	 */
	public function __construct() {
		parent::start(
			'invitations',
			_x( 'Invitations', 'Page <title>', 'buddypress' ),
			buddypress()->plugin_dir,
			array()
		);
	}

	/**
	 * Include invitations component files.
	 *
	 * @since 1.9.0
	 *
	 * @see BP_Component::includes() for a description of arguments.
	 *
	 * @param array $includes See BP_Component::includes() for a description.
	 */
	public function includes( $includes = array() ) {
	}

	/**
	 * Set up component global data.
	 *
	 * @since 2.6.0
	 *
	 * @see BP_Component::setup_globals() for a description of arguments.
	 *
	 * @param array $args See BP_Component::setup_globals() for a description.
	 */
	public function setup_globals( $args = array() ) {
		$bp = buddypress();

		// Define a slug, if necessary.
		if ( ! defined( 'BP_INVITATIONS_SLUG' ) ) {
			define( 'BP_INVITATIONS_SLUG', $this->id );
		}

		// Global tables for the invitations component.
		$global_tables = array(
			'table_name'      => $bp->table_prefix . 'bp_invitations',
		);

		// All globals for the invitations component.
		// Note that global_tables is included in this array.
		$args = array(
			'slug'          => BP_INVITATIONS_SLUG,
			'has_directory' => false,
			'search_string' => __( 'Search Invitations...', 'buddypress' ),
			'global_tables' => $global_tables,
		);

		parent::setup_globals( $args );
	}

	/**
	 * Set up component navigation.
	 *
	 * @since 2.6.0
	 *
	 * @see BP_Component::setup_nav() for a description of arguments.
	 *
	 * @param array $main_nav Optional. See BP_Component::setup_nav() for
	 *                        description.
	 * @param array $sub_nav  Optional. See BP_Component::setup_nav() for
	 *                        description.
	 */
	public function setup_nav( $main_nav = array(), $sub_nav = array() ) {
	}

	/**
	 * Set up the component entries in the WordPress Admin Bar.
	 *
	 * @since 2.6.0
	 *
	 * @see BP_Component::setup_nav() for a description of the $wp_admin_nav
	 *      parameter array.
	 *
	 * @param array $wp_admin_nav See BP_Component::setup_admin_bar() for a
	 *                            description.
	 */
	public function setup_admin_bar( $wp_admin_nav = array() ) {
	}

	/**
	 * Set up the title for pages and <title>.
	 *
	 * @since 2.6.0
	 */
	public function setup_title() {
	}

	/**
	 * Set up cache groups.
	 *
	 * @since 2.6.0
	 */
	public function setup_cache_groups() {

		// Global groups.
		wp_cache_add_global_groups( array(
			'bp_invitations'
		) );

		parent::setup_cache_groups();
	}
}

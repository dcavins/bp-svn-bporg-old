<?php
/**
 * BuddyPress Activity Classes.
 *
 * @package BuddyPress
 * @subpackage ActivityClasses
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

require dirname( __FILE__ ) . '/classes/class-bp-activity-activity.php';
require dirname( __FILE__ ) . '/classes/class-bp-activity-feed.php';
require dirname( __FILE__ ) . '/classes/class-bp-activity-query.php';

// Embeds - only applicable for WP 4.5+
if ( bp_get_major_wp_version() >= 4.5 && bp_is_active( 'activity', 'embeds' ) ) {
	require dirname( __FILE__ ) . '/classes/class-bp-activity-oembed-component.php';
}
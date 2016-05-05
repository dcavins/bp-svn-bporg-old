<?php
/**
 * BuddyPress invitations functions.
 *
 * @package BuddyPress
 * @subpackage Invitations
 * @since 2.6.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/** Set up ********************************************************************/

/**
 * Start the invitations component.
 * This is a very minimal component that has no screens/navigation items of its
 * own, only a database.
 *
 * @since 2.6.0
 */
function bp_setup_invitations_component() {
	buddypress()->invitations = new BP_Invitations_Component();
}
add_action( 'bp_setup_components', 'bp_setup_invitations_component', 1 );


/** Create ********************************************************************/

/**
 * Add an invitation to a specific user, from a specific user, related to a
 * specific component.
 *
 * @since 2.6.0
 *
 * @param array $args {
 *     Array of arguments describing the invitation. All are optional.
 *	   @type int    $user_id ID of the invited user.
 *	   @type int    $inviter_id ID of the user who created the invitation.
 *	   @type string $invitee_email Email address of the invited user.
 * 	   @type string $component_name Name of the related component.
 *	   @type string $component_action Name of the related component action.
 * 	   @type int    $item_id ID associated with the invitation and component.
 * 	   @type int    $secondary_item_id secondary ID associated with the
 *			        invitation and component.
 * 	   @type string $type @TODO.
 * 	   @type string $content Extra information provided by the requester
 *			        or inviter.
 * 	   @type string $date_modified Date the invitation was last modified.
 * 	   @type int    $invite_sent Has the invitation been sent, or is it a
 *			 draft invite?
 * }
 * @return int|bool ID of the newly created invitation on success, false
 *         on failure.
 */
function bp_invitations_add_invitation( $args = array() ) {

	$r = wp_parse_args( $args, array(
		'user_id'           => 0,
		'invitee_email'		=> '',
		'inviter_id'		=> 0,
		'component_name'    => '',
		'component_action'  => '',
		'item_id'           => 0,
		'secondary_item_id' => 0,
		'type'				=> 'invite',
		'content'			=> '',
		'date_modified'     => bp_core_current_time(),
		'invite_sent'       => 0,
		'accepted'          => 0
	) );

	// Invitations must have an invitee and inviter.
	if ( ! ( ( $r['user_id'] || $r['invitee_email'] ) && $r['inviter_id'] ) ) {
		return false;
	}

	/**
	 * Is this user allowed to extend invitations from this component/item?
	 *
	 * @since 2.6.0
	 *
	 * @param array $r Describes the invitation to be added.
	 */
	if ( ! apply_filters( 'bp_invitations_allow_invitation', true, $r ) ) {
		return false;
	}

	// Avoid creating duplicate invitations.
	$existing = bp_invitations_get_invitations( array(
		'user_id'           => $r['user_id'],
		'invitee_email'     => $r['invitee_email'],
		'inviter_id'        => $r['inviter_id'],
		'component_name'    => $r['component_name'],
		'component_action'  => $r['component_action'],
		'item_id'           => $r['item_id'],
		'secondary_item_id' => $r['secondary_item_id'],
	) );

	if ( $existing ) {
		return false;
	}

	// Set up the new invitation as a draft.
	$invitation                    = new BP_Invitations_Invitation;
	$invitation->user_id           = $r['user_id'];
	$invitation->inviter_id        = $r['inviter_id'];
	$invitation->invitee_email     = $r['invitee_email'];
	$invitation->component_name    = $r['component_name'];
	$invitation->component_action  = $r['component_action'];
	$invitation->item_id           = $r['item_id'];
	$invitation->secondary_item_id = $r['secondary_item_id'];
	$invitation->type              = $r['type'];
	$invitation->content           = $r['content'];
	$invitation->date_modified     = $r['date_modified'];
	$invitation->invite_sent       = 0;
	$invitation->accepted          = 0;

	$save_success = $invitation->save();

	// "Send" the invite if necessary.
	if ( $r['invite_sent'] && $save_success ) {
		$sent = bp_invitations_send_invitation_by_id( $save_success );
		if ( ! $sent ) {
			return false;
		}
	}

	return $save_success;
}

function bp_invitations_send_invitation_by_id( $invitation_id ) {
	$updated = false;

	$invitation = bp_invitations_get_invitation_by_id( $invitation_id );

	// Different uses may need different actions on sending. Plugins can hook in here to perform their own tasks.
	do_action( 'bp_invitations_send_invitation_by_id_before_send', $invitation_id, $invitation );

	// Plugins can stop the process here.
	$allowed = apply_filters( 'bp_invitations_send_invitation_by_id', true, $invitation_id, $invitation );

	if ( $allowed ) {
		/*
		 * Before creating a sent invitation, check for outstanding requests to the same item.
		 * A sent invitation + a request = acceptance.
		 */
		$request_args = array(
			'user_id'           => $invitation->user_id,
			'invitee_email'     => $invitation->invitee_email,
			'component_name'    => $invitation->component_name,
			'component_action'  => $invitation->component_action,
			'item_id'           => $invitation->item_id,
			'secondary_item_id' => $invitation->secondary_item_id,
		);
		$request = bp_invitations_get_requests( $request_args );

		if ( ! empty( $request ) ) {
			// Accept the request.
			return bp_invitations_accept_request( $request_args );
		}

		$updated = bp_invitations_mark_sent_by_id( $invitation_id );
	}

	return $updated;
}

/**
 * Add a request to an item for a specific user, related to a
 * specific component.
 *
 * @since 2.6.0
 *
 * @param array $args {
 *     Array of arguments describing the invitation. All are optional.
 *	   @type int    $user_id ID of the invited user.
 *	   @type int    $inviter_id ID of the user who created the invitation.
 * 	   @type string $component_name Name of the related component.
 *	   @type string $component_action Name of the related component action.
 * 	   @type int    $item_id ID associated with the invitation and component.
 * 	   @type int    $secondary_item_id secondary ID associated with the
 *			        invitation and component.
 * 	   @type string $type @TODO.
 * 	   @type string $content Extra information provided by the requester
 *			        or inviter.
 * 	   @type string $date_modified Date the invitation was last modified.
 * 	   @type int    $invite_sent Has the invitation been sent, or is it a
 *			 draft invite?
 * }
 * @return int|bool ID of the newly created invitation on success, false
 *         on failure.
 */
function bp_invitations_add_request( $args = array() ) {

	$r = wp_parse_args( $args, array(
		'user_id'           => 0,
		'inviter_id'		=> 0,
		'invitee_email'		=> '',
		'component_name'    => '',
		'component_action'  => '',
		'item_id'           => 0,
		'secondary_item_id' => 0,
		'type'				=> 'request',
		'content'			=> '',
		'date_modified'     => bp_core_current_time(),
		'invite_sent'       => 0,
		'accepted'          => 0
	) );

	// If there is no invitee, bail.
	if ( ! ( $r['user_id'] ) ) {
		return false;
	}

	/**
	 * Is the item accepting requests?
	 *
	 * @since 2.6.0
	 *
	 * @param array $r Describes the invitation to be added.
	 */
	if ( ! apply_filters( 'bp_invitations_allow_request', true, $r ) ) {
		return false;
	}


	// Check for existing duplicate requests.
	$existing = bp_invitations_get_requests( array(
		'user_id'           => $r['user_id'],
		'invitee_email'     => $r['invitee_email'],
		'component_name'    => $r['component_name'],
		'component_action'  => $r['component_action'],
		'item_id'           => $r['item_id'],
		'secondary_item_id' => $r['secondary_item_id'],
	) );

	if ( $existing ) {
		return false;
	}

	/*
	 * Check for outstanding invitations to the same item.
	 * A request + a sent invite = acceptance.
	 */
	$invite = bp_invitations_get_invitations( array(
		'user_id'           => $r['user_id'],
		'invitee_email'     => $r['invitee_email'],
		'component_name'    => $r['component_name'],
		'component_action'  => $r['component_action'],
		'item_id'           => $r['item_id'],
		'secondary_item_id' => $r['secondary_item_id'],
		'invite_sent'       => 'sent'
	) );

	if ( $invite ) {
		// Accept the invite.
		return bp_invitations_accept_invitation( array(
			'user_id'           => $r['user_id'],
			'invitee_email'     => $r['invitee_email'],
			'component_name'    => $r['component_name'],
			'component_action'  => $r['component_action'],
			'item_id'           => $r['item_id'],
			'secondary_item_id' => $r['secondary_item_id'],
		) );
	} else {
		// Set up the new invitation
		$request                    = new BP_Invitations_Invitation;
		$request->user_id           = $r['user_id'];
		$request->inviter_id        = $r['inviter_id'];
		$request->invitee_email     = $r['invitee_email'];
		$request->component_name    = $r['component_name'];
		$request->component_action  = $r['component_action'];
		$request->item_id           = $r['item_id'];
		$request->secondary_item_id = $r['secondary_item_id'];
		$request->type              = $r['type'];
		$request->date_modified     = $r['date_modified'];
		$request->invite_sent       = $r['invite_sent'];
		$request->accepted          = $r['accepted'];

		// Save the new invitation.
		return $request->save();
	}
}

/** Retrieve ******************************************************************/

/**
 * Get a specific invitation by its ID.
 *
 * @since 2.6.0
 *
 * @param int $id ID of the invitation.
 * @return BP_Invitations_Invitation object
 */
function bp_invitations_get_invitation_by_id( $id ) {
	$invitation = wp_cache_get( 'invitation_id_' . $id, 'bp_invitations' );
	if ( false === $invitation ) {
		$invitation = new BP_Invitations_Invitation( $id );
		wp_cache_set( 'invitation_id_' . $id, $invitation, 'bp_invitations' );
	}
	return $invitation;
}

/**
 * Get invitations, based on provided filter parameters.
 *
 * @since 2.6.0
 *
 * @param array $args {
 *     Associative array of arguments. All arguments but $page and
 *     $per_page can be treated as filter values for get_where_sql()
 *     and get_query_clauses(). All items are optional.
 *     @type int|array    $id                ID of invitation being requested.
 *                                           Can be an array of IDs.
 *     @type int|array    $user_id           ID of user being queried. Can be an
 *                                           Can be an array of IDs.
 *     @type int|array    $inviter_id        ID of user who created the
 *                                           invitation. Can be an array of IDs.
 *     @type string|array $invitee_email     Email address of invited users
 *			                                 being queried. Can be an array of
 *                                           addresses.
 *     @type string|array $component_name    Name of the component to filter by.
 *                                           Can be an array of component names.
 *     @type string|array $component_action  Name of the action to filter by.
 *                                           Can be an array of actions.
 *     @type int|array    $item_id           ID of associated item.
 *                                           Can be an array of multiple item IDs.
 *     @type int|array    $secondary_item_id ID of secondary associated item.
 *                                           Can be an array of multiple IDs.
 *     @type string       $invite_sent       Limit to draft, sent or all
 *                                           'draft' limits to unsent invites,
 *                                           'sent' returns only sent invites,
 *                                           'all' returns all. Default: 'all'.
 *     @type string       $invite_sent       Limit to draft, sent or all
 *                                           'draft' limits to unsent invites,
 *                                           'sent' returns only sent invites,
 *                                           'all' returns all. Default: 'all'.
 *     @type bool         $accepted          Limit to accepted or
 *                                           not-yet-accepted invitations.
 *                                           'accepted' returns accepted invites,
 *                                           'pending' returns pending invites,
 *                                           'all' returns all. Default: 'pending'
 *     @type string       $search_terms      Term to match against component_name
 *                                           or component_action fields.
 *     @type string       $order_by          Database column to order by.
 *     @type string       $sort_order        Either 'ASC' or 'DESC'.
 *     @type string       $order_by          Field to order results by.
 *     @type string       $sort_order        ASC or DESC.
 *     @type int          $page              Number of the current page of results.
 *                                           Default: false (no pagination,
 *                                           all items).
 *     @type int          $per_page          Number of items to show per page.
 *                                           Default: false (no pagination,
 *                                           all items).
 * }
 * @return array Located invitations.
 */
function bp_invitations_get_invitations( $args ) {
	return BP_Invitations_Invitation::get( $args );
}

/**
 * Get requests, based on provided filter parameters. This is the
 * Swiss Army Knife function. When possible, use the filter_invitations
 * functions that take advantage of caching.
 *
 * @since 2.6.0
 *
 * @param array $args {
 *     Associative array of arguments. All arguments but $page and
 *     $per_page can be treated as filter values for get_where_sql()
 *     and get_query_clauses(). All items are optional.
 *     @type int|array    $id ID of invitation. Can be an array of IDs.
 *     @type int|array    $user_id ID of user being queried. Can be an
 *                        array of user IDs.
 *     @type string|array $invitee_email Email address of invited users
 *			              being queried. Can be an array of addresses.
 *     @type string|array $component_name Name of the component to
 *                        filter by. Can be an array of component names.
 *     @type string|array $component_action Name of the action to
 *                        filter by. Can be an array of actions.
 *     @type int|array    $item_id ID of associated item. Can be an array
 *                        of multiple item IDs.
 *     @type int|array    $secondary_item_id ID of secondary associated
 *                        item. Can be an array of multiple IDs.
 *     @type string       $invite_sent Limit to draft, sent or all
 *                        invitations. 'draft' returns only unsent
 *                        invitations, 'sent' returns only sent
 *                        invitations, 'all' returns all. Default: 'all'.
 *     @type string       $search_terms Term to match against
 *                        component_name or component_action fields.
 *     @type string       $order_by Database column to order by.
 *     @type string       $sort_order Either 'ASC' or 'DESC'.
 *     @type string       $order_by Field to order results by.
 *     @type string       $sort_order ASC or DESC.
 *     @type int          $page Number of the current page of results.
 *                        Default: false (no pagination - all items).
 *     @type int          $per_page Number of items to show per page.
 *                        Default: false (no pagination - all items).
 * }
 * @return array Located invitations.
 */
function bp_invitations_get_requests( $args ) {
	// Set request-specific parameters.
	$args['type']        = 'request';
	$args['inviter_id']  = 0;
	$args['invite_sent'] = 'all';

	return BP_Invitations_Invitation::get( $args );
}

/**
 * @param array $args {
 *     Array of optional arguments.
 *     @type string $component_name    Name of the component to filter by.
 *     @type string $component_action  Name of the action to filter by.
 *     @type int    $item_id           ID of associated item. Can be an array
 *                                     of multiple item IDs.
 *     @type int    $secondary_item_id ID of secondary associated item.
 *                                     Can be an array of multiple IDs.
 *     @type string $type              Type of invite: invite or request.
 *                                     Default: invite.
 *     @type string $invite_sent       Limit to draft, sent or all invitations.
 *                                     'draft' returns only unsent invitations,
 *                                     'sent' returns only sent invitations,
 *                                     'all' returns all. Default: 'sent'.
 *     @type bool   $accepted          Limit to accepted or not-yet-accepted
 *                                     invitations.
 *                                     'accepted' returns only accepted invites,
 *                                     'pending' returns only pending invites,
 *                                     'all' returns all. Default: 'pending'
 *     @type string $sort_order        Order of results. 'ASC' or 'DESC'.
 *     @type int    $page              Which page of results to return.
 *     @type string $per_page          How many invites to include on each page
 *                                     of results.
 * }
 */
function bp_get_user_invitations( $user_id = 0, $args = array(), $invitee_email = false ) {
	$r = bp_parse_args( $args, array(
		'inviter_id'        => 0,
		'component_name'    => '',
		'component_action'  => '',
		'item_id'           => false,
		'secondary_item_id' => false,
 		'type'              => 'invite',
		'invite_sent'       => 'sent',
		'accepted'          => 'pending',
		'orderby'           => 'id',
		'sort_order'        => 'ASC',
		'page'              => false,
		'per_page'          => false
	), 'bp_get_user_invitations' );
	$invitations = array();

	// Two cases: we're searching by email address or user ID.
	if ( ! empty( $invitee_email ) && is_email( $invitee_email ) ) {
		// Get invitations out of the cache, or query for all if necessary
		$encoded_email = rawurlencode( $invitee_email );
		$invitations = wp_cache_get( 'all_to_user_' . $encoded_email, 'bp_invitations' );
		if ( false === $invitations ) {
			$all_args = array(
				'invitee_email' => $invitee_email,
				'invite_sent' => 'all',
				'accepted'    => 'all'
			);
			$invitations = bp_invitations_get_invitations( $all_args );
			wp_cache_set( 'all_to_user_' . $encoded_email, $invitations, 'bp_invitations' );
		}
	} else {
		// Default to displayed user or logged-in user if no ID is passed
		if ( empty( $user_id ) ) {
			$user_id = ( bp_displayed_user_id() ) ? bp_displayed_user_id() : bp_loggedin_user_id();
		}
		// Get invitations out of the cache, or query for all if necessary
		$invitations = wp_cache_get( 'all_to_user_' . $user_id, 'bp_invitations' );
		if ( false === $invitations ) {
			$all_args = array(
				'user_id'     => $user_id,
				'invite_sent' => 'all',
				'accepted'    => 'all'
			);
			$invitations = bp_invitations_get_invitations( $all_args );
			wp_cache_set( 'all_to_user_' . $user_id, $invitations, 'bp_invitations' );
		}
	}

	// Pass the list of invitations to the filter.
	$invitations = BP_Invitations_Invitation::filter_invitations_by_arguments( $invitations, $r );

	if ( 'request' == $r['type'] ) {
		$filter_hook_name = 'bp_get_user_requests';
	} else {
		$filter_hook_name = 'bp_get_user_invitations';
	}

	/**
	 * Fires before finalization of group creation and cookies are set.
	 *
	 * This hook is a variable hook dependent on the current step
	 * in the creation process.
	 *
	 * @since 2.6.0
	 */
	return apply_filters( $filter_hook_name, $invitations, $user_id, $args );
}

function bp_get_user_requests( $user_id = 0, $args = array() ){
	// Requests are a type of invitation, so we can use our main function.
	$args['type']        = 'request';
	// Passing 'all' ensures that all statuses are returned.
	$args['invite_sent'] = 'all';

	// Filter the results on the `bp_get_user_requests` hook.
	return bp_get_user_invitations( $user_id, $args );
}

/**
 * Get outgoing invitations from a user.
 * We get and cache all of the outgoing invitations from a user. We'll
 * filter the complete result set in PHP, in order to take advantage of
 * the cache.
 *
 * @since 2.6.0
 *
 * @param array $args {
 *     Array of optional arguments.
 *     @type int|array    $user_id ID of user being queried. Can be an
 *                        array of user IDs.
 *     @type string|array $invitee_email Email address of invited users
 *			              being queried. Can be an array of addresses.
 *     @type string|array $component_name Name of the component to
 *                        filter by. Can be an array of component names.
 *     @type string|array $component_action Name of the action to
 *                        filter by. Can be an array of actions.
 *     @type int|array    $item_id ID of associated item. Can be an array
 *                        of multiple item IDs.
 *     @type int|array    $secondary_item_id ID of secondary associated
 *                        item. Can be an array of multiple IDs.
 *     @type string       $invite_sent Limit to draft, sent or all
 *                        invitations. 'draft' returns only unsent
 *                        invitations, 'sent' returns only sent
 *                        invitations, 'all' returns all. Default: 'all'.
 *     @type string       $order_by Database column to order by.
 *     @type string       $sort_order Either 'ASC' or 'DESC'.
 * }
 * @return array $invitations Array of invitation results.
 *               (Returns an empty array if none found.)
 */
function bp_get_invitations_from_user( $inviter_id = 0, $args = array() ) {
	$r = bp_parse_args( $args, array(
		'component_name'    => '',
		'component_action'  => '',
		'item_id'           => null,
		'secondary_item_id' => null,
 		'type'              => 'invite',
		'invite_sent'       => 'all',
		'accepted'          => false,
		'orderby'           => 'id',
		'sort_order'        => 'ASC',
		'page'              => false,
		'per_page'          => false
	), 'bp_get_invitations_from_user' );
	$invitations = array();

	// Default to displayed user if no ID is passed
	if ( empty( $inviter_id ) ) {
		$inviter_id = ( bp_displayed_user_id() ) ? bp_displayed_user_id() : bp_loggedin_user_id();
	}

	// Get invitations out of the cache, or query if necessary
	$invitations = wp_cache_get( 'all_from_user_' . $inviter_id, 'bp_invitations' );
	if ( false === $invitations ) {
		$args = array(
			'inviter_id' => $inviter_id,
			'invite_sent' => 'all'
		);
		$invitations = bp_invitations_get_invitations( $args );
		wp_cache_set( 'all_from_user_' . $inviter_id, $invitations, 'bp_invitations' );
	}

	// Pass the list of invitations to the filter.
	$invitations = BP_Invitations_Invitation::filter_invitations_by_arguments( $invitations, $r );

	/**
	 * Fires before finalization of group creation and cookies are set.
	 *
	 * This hook is a variable hook dependent on the current step
	 * in the creation process.
	 *
	 * @since 2.6.0
	 */
	return apply_filters( 'bp_get_invitations_from_user', $invitations, $inviter_id, $args );
}

/** Update ********************************************************************/

/**
 * Accept invitation, based on provided filter parameters.
 *
 * @since 2.6.0
 *
 * @see BP_Invitations_Invitation::get() for a description of
 *      accepted update/where arguments.
 *
 * @param array $update_args Associative array of fields to update,
 *              and the values to update them to. Of the format
 *              array( 'user_id' => 4, 'component_name' => 'groups', )
 *
 * @return int|bool Number of rows updated on success, false on failure.
 */
function bp_invitations_accept_invitation( $args = array() ) {
	/*
	 * Some basic info is required to accept an invitation,
	 * because we'll need to mark all similar invitations and requests.
	 * The following, except the optional 'secondary_item_id', are required.
	 */
	$r = bp_parse_args( $args, array(
		'user_id'           => 0,
		'invitee_email'     => '',
		'component_name'    => '',
		'component_action'  => '',
		'item_id'           => null,
		'secondary_item_id' => null,
	), 'bp_get_invitations_from_user' );

	if ( ! ( ( $r['user_id'] || $r['invitee_email'] ) && $r['component_name'] && $r['component_action'] && $r['item_id'] ) ) {
		return false;
	}

	//@TODO: access check
	$success = false;
	if ( apply_filters( 'bp_invitations_accept_invitation', true, $args ) ) {
		// Mark invitations & requests to this item for this user.
		$success = bp_invitations_mark_accepted( $r );
	}
	return $success;
}

/**
 * Accept invitation, based on provided filter parameters.
 *
 * @since 2.6.0
 *
 * @see BP_Invitations_Invitation::get() for a description of
 *      accepted update/where arguments.
 *
 * @param array $update_args Associative array of fields to update,
 *              and the values to update them to. Of the format
 *              array( 'user_id' => 4, 'component_name' => 'groups', )
 *
 * @return bool Number of rows updated on success, false on failure.
 */
function bp_invitations_accept_request( $args = array() ) {
	/*
	 * Some basic info is required to accept an invitation,
	 * because we'll need to accept all similar invitations and requests.
	 * The following, except the optional 'secondary_item_id', are required.
	 */
	$r = bp_parse_args( $args, array(
		'user_id'           => 0,
		'component_name'    => '',
		'component_action'  => '',
		'item_id'           => null,
		'secondary_item_id' => null,
	), 'bp_get_invitations_from_user' );

	if ( ! ( $r['user_id'] && $r['component_name'] && $r['component_action'] && $r['item_id'] ) ) {
		return false;
	}

	//@TODO: access check
	$success = false;
	if ( apply_filters( 'bp_invitations_accept_request', true, $args ) ) {
		// Delete all related invitations & requests to this item for this user.
		$success = bp_invitations_mark_accepted( $r );
	}
	return $success;
}

/**
 * Update invitation, based on provided filter parameters.
 *
 * @since 2.6.0
 *
 * @see BP_Invitations_Invitation::get() for a description of
 *      accepted update/where arguments.
 *
 * @param array $update_args Associative array of fields to update,
 *              and the values to update them to. Of the format
 *              array( 'user_id' => 4, 'component_name' => 'groups', )
 * @param array $where_args Associative array of columns/values, to
 *              determine which invitations should be updated. Formatted as
 *              array( 'item_id' => 7, 'component_action' => 'members', )
 * @return int|bool Number of rows updated on success, false on failure.
 */
function bp_invitations_update_invitation( $update_args = array(), $where_args = array() ) {
	//@TODO: access check
	return BP_Invitations_Invitation::update( $update_args, $where_args );
}

/**
 * Mark invitation as sent by invitation ID.
 *
 * @since 2.6.0
 *
 * @param int $id The ID of the invitation to mark as sent.
 * @return bool True on success, false on failure.
 */
function bp_invitations_mark_sent_by_id( $id ) {
	//@TODO: access check
	return BP_Invitations_Invitation::mark_sent( $id );
}

/**
 * Mark invitations as sent that are found by user_id, inviter_id,
 * invitee_email, component name and action, optional item id,
 * optional secondary item id.
 *
 * @since 2.6.0
 *
 * @param array $args {
 *     Associative array of arguments. All arguments but $page and
 *     $per_page can be treated as filter values for get_where_sql()
 *     and get_query_clauses(). All items are optional.
 *     @type int|array    $user_id ID of user being queried. Can be an
 *                        array of user IDs.
 *     @type int|array    $inviter_id ID of user who created the
 *                        invitation. Can be an array of user IDs.
 *                        Special cases
 *     @type string|array $invitee_email Email address of invited users
 *			              being queried. Can be an array of addresses.
 *     @type string|array $component_name Name of the component to
 *                        filter by. Can be an array of component names.
 *     @type string|array $component_action Name of the action to
 *                        filter by. Can be an array of actions.
 *     @type int|array    $item_id ID of associated item. Can be an array
 *                        of multiple item IDs.
 *     @type int|array    $secondary_item_id ID of secondary associated
 *                        item. Can be an array of multiple IDs.
 * }
 */
function bp_invitations_mark_sent( $args ) {
	//@TODO: access check
	return BP_Invitations_Invitation::mark_sent_by_data( $args );
}

/**
 * Mark invitation as accepted by invitation ID.
 *
 * @since 2.6.0
 *
 * @param int $id The ID of the invitation to mark as sent.
 * @return bool True on success, false on failure.
 */
function bp_invitations_mark_accepted_by_id( $id ) {
	//@TODO: access check
	return BP_Invitations_Invitation::mark_accepted( $id );
}

/**
 * Mark invitations as sent that are found by user_id, inviter_id,
 * invitee_email, component name and action, optional item id,
 * optional secondary item id.
 *
 * @since 2.6.0
 *
 * @param array $args {
 *     Associative array of arguments. All arguments but $page and
 *     $per_page can be treated as filter values for get_where_sql()
 *     and get_query_clauses(). All items are optional.
 *     @type int|array    $user_id ID of user being queried. Can be an
 *                        array of user IDs.
 *     @type int|array    $inviter_id ID of user who created the
 *                        invitation. Can be an array of user IDs.
 *                        Special cases
 *     @type string|array $invitee_email Email address of invited users
 *			              being queried. Can be an array of addresses.
 *     @type string|array $component_name Name of the component to
 *                        filter by. Can be an array of component names.
 *     @type string|array $component_action Name of the action to
 *                        filter by. Can be an array of actions.
 *     @type int|array    $item_id ID of associated item. Can be an array
 *                        of multiple item IDs.
 *     @type int|array    $secondary_item_id ID of secondary associated
 *                        item. Can be an array of multiple IDs.
 * }
 */
function bp_invitations_mark_accepted( $args ) {
	//@TODO: access check
	return BP_Invitations_Invitation::mark_accepted_by_data( $args );
}

/** Delete ********************************************************************/

/**
 * Delete a specific invitation by its ID.
 *
 * Used when rejecting invitations or membership requests.
 *
 * @since 2.6.0
 *
 * @param int $id ID of the invitation to delete.
 * @return int|false Number of rows deleted on success, false on failure.
 */
function bp_invitations_delete_invitation_by_id( $id ) {
	//@TODO: access check
	return BP_Invitations_Invitation::delete_by_id( $id );
}

/**
 * Delete an invitation or invitations by query data.
 *
 * Used when declining invitations.
 *
 * @since 2.6.0
 *
 * @see bp_invitations_get_invitations() for a description of
 *      accepted where arguments.
 *
 * @param array $args {
 *     Associative array of arguments. All arguments but $page and
 *     $per_page can be treated as filter values for get_where_sql()
 *     and get_query_clauses(). All items are optional.
 *     @type int|array    $user_id ID of user being queried. Can be an
 *                        array of user IDs.
 *     @type int|array    $inviter_id ID of user who created the
 *                        invitation. Can be an array of user IDs.
 *                        Special cases
 *     @type string|array $invitee_email Email address of invited users
 *			              being queried. Can be an array of addresses.
 *     @type string|array $component_name Name of the component to
 *                        filter by. Can be an array of component names.
 *     @type string|array $component_action Name of the action to
 *                        filter by. Can be an array of actions.
 *     @type int|array    $item_id ID of associated item. Can be an array
 *                        of multiple item IDs.
 *     @type int|array    $secondary_item_id ID of secondary associated
 *                        item. Can be an array of multiple IDs.
 *     @type string       $type Invite or request.
 * }
 * @return int|false Number of rows deleted on success, false on failure.
 */
function bp_invitations_delete_invitations( $args ) {
	//@TODO: access check
	if ( empty( $args['type'] ) ) {
		$args['type'] = 'invite';
	}
	return BP_Invitations_Invitation::delete( $args );
}

/**
 * Delete a request or requests by query data.
 *
 * Used when rejecting membership requests.
 *
 * @since 2.6.0
 *
 * @see bp_invitations_get_invitations() for a description of
 *      accepted where arguments.
 *
 * @param array $args {
 *     Associative array of arguments. All arguments but $page and
 *     $per_page can be treated as filter values for get_where_sql()
 *     and get_query_clauses(). All items are optional.
 *     @type int|array    $user_id ID of user being queried. Can be an
 *                        array of user IDs.
 *     @type int|array    $inviter_id ID of user who created the
 *                        invitation. Can be an array of user IDs.
 *                        Special cases
 *     @type string|array $invitee_email Email address of invited users
 *			              being queried. Can be an array of addresses.
 *     @type string|array $component_name Name of the component to
 *                        filter by. Can be an array of component names.
 *     @type string|array $component_action Name of the action to
 *                        filter by. Can be an array of actions.
 *     @type int|array    $item_id ID of associated item. Can be an array
 *                        of multiple item IDs.
 *     @type int|array    $secondary_item_id ID of secondary associated
 *                        item. Can be an array of multiple IDs.
 * }
 * @return int|false Number of rows deleted on success, false on failure.
 */
function bp_invitations_delete_requests( $args ) {
	//@TODO: access check
	$args['type'] = 'request';
	return BP_Invitations_Invitation::delete( $args );
}

/**
 * Delete all invitations by component.
 *
 * Used when clearing out invitations for an entire component. Possibly used
 * when deactivating a component that created invitations.
 *
 * @since 2.6.0
 *
 * @param string $component_name Name of the associated component.
 * @param string $component_action Optional. Name of the associated action.
 * @return int|false Number of rows deleted on success, false on failure.
 */
function bp_invitations_delete_all_invitations_by_component( $component_name, $component_action = false ) {
	//@TODO: access check
	return BP_Invitations_Invitation::delete( array(
		'component_name'    => $component_name,
		'component_action'  => $component_action,
	) );
}

/** Helpers *******************************************************************/

/**
 * Get a count of incoming invitations for a user.
 *
 * @since 2.6.0
 *
 * @param int $user_id ID of the user whose incoming invitations are being
 *        counted.
 * @return int Incoming invitation count.
 */
// function bp_invitations_get_incoming_invitation_count( $user_id = 0 ) {
// 	$invitations = bp_invitations_get_incoming_invitations_for_user( $user_id );
// 	$count       = ! empty( $invitations ) ? count( $invitations ) : 0;

// 	return apply_filters( 'bp_invitations_get_incoming_invitation_count', (int) $count );
// }

/**
 * Return an array of component names that are currently active and have
 * registered Invitations callbacks.
 *
 * @since 2.6.0
 *
 * @return array
 */
function bp_invitations_get_registered_components() {

	// Load BuddyPress
	$bp = buddypress();

	// Setup return value
	$component_names = array();

	// Get the active components
	$active_components = array_keys( $bp->active_components );

	// Loop through components, look for callbacks, add to return value
	foreach ( $active_components as $component ) {
		if ( ! empty( $bp->$component->invitation_callback ) ) {
			$component_names[] = $component;
		}
	}

	// Return active components with registered invitations callbacks
	return apply_filters( 'bp_invitations_get_registered_components', $component_names, $active_components );
}

/* Caching ********************************************************************/

/**
 * Invalidate 'all_from_user_' and 'all_to_user_' caches when saving.
 *
 * @since 2.6.0
 *
 * @param BP_Invitations_Invitation $n Invitation object.
 */
function bp_invitations_clear_user_caches_after_save( BP_Invitations_Invitation $n ) {
	// User_id could be empty if a non-member is being invited via email.
	if ( $n->user_id ) {
		wp_cache_delete( 'all_to_user_' . $n->user_id, 'bp_invitations' );
	}
	// Inviter_id could be empty if this is a request for membership.
	if ( $n->inviter_id ) {
		wp_cache_delete( 'all_from_user_' . $n->inviter_id, 'bp_invitations' );
	}
}
add_action( 'bp_invitation_after_save', 'bp_invitations_clear_user_caches_after_save' );

/**
 * Invalidate 'all_from_user_' and 'all_to_user_' caches when
 * updating or deleting.
 *
 * @since 2.6.0
 *
 * @param int $args Invitation deletion arguments.
 */
function bp_invitations_clear_user_caches_before_update( $args ) {
	// Pull up a list of invitations matching the args (those about to be updated or deleted)
	$invites = BP_Invitations_Invitation::get( $args );

	$user_ids = array();
	$inviter_ids = array();
	foreach ( $invites as $i ) {
		$user_ids[] 	= $i->user_id;
		$inviter_ids[] 	= $i->inviter_id;
	}

	foreach ( array_unique( $user_ids ) as $user_id ) {
		wp_cache_delete( 'all_to_user_' . $user_id, 'bp_invitations' );
	}

	foreach ( array_unique( $inviter_ids ) as $inviter_id ) {
		wp_cache_delete( 'all_from_user_' . $inviter_id, 'bp_invitations' );
	}
}
add_action( 'bp_invitation_before_update', 'bp_invitations_clear_user_caches_before_update' );
add_action( 'bp_invitation_before_delete', 'bp_invitations_clear_user_caches_before_update' );
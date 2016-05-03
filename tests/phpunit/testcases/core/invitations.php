<?php
/**
 * @group core
 * @group invitations
 */
class BP_Tests_Invitations extends BP_UnitTestCase {
	public function test_bp_invitations_add_invitation_vanilla() {
		global $wpdb;
		$old_current_user = get_current_user_id();

		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$this->set_current_user( $u1 );

		// Create a couple of invitations.
		$invite_args = array(
			'user_id'           => $u3,
			'inviter_id'		=> $u1,
			'component_name'    => 'cakes',
			'component_action'  => 'cupcakes',
			'item_id'           => 1,
			'invite_sent'       => 1,
		);
		$i1 = bp_invitations_add_invitation( $invite_args );
		$invite_args['inviter_id'] = $u2;
		$i2 = bp_invitations_add_invitation( $invite_args );

		$get_invites = array(
			'user_id'        => $u3,
			'component_name' => 'cakes',
		);
		$invites = bp_invitations_get_invitations( $u3, $get_invites );
		$this->assertEqualSets( array( $i1, $i2 ), wp_list_pluck( $invites, 'id' ) );

		$this->set_current_user( $old_current_user );
	}

	public function test_bp_invitations_add_invitation_avoid_duplicates() {
		global $wpdb;
		$old_current_user = get_current_user_id();

		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$this->set_current_user( $u1 );

		// Create an invitation.
		$invite_args = array(
			'user_id'           => $u2,
			'inviter_id'		=> $u1,
			'component_name'    => 'blogs',
			'component_action'  => 'blog_invite',
			'item_id'           => 1,
			'invite_sent'       => 1,
		);
		$i1 = bp_invitations_add_invitation( $invite_args );
		// Attempt to create a duplicate.
		$this->assertFalse( bp_invitations_add_invitation( $invite_args ) );

		$this->set_current_user( $old_current_user );
	}

	public function test_bp_invitations_add_invitation_invite_plus_request_should_accept() {
		global $wpdb;
		$old_current_user = get_current_user_id();

		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$this->set_current_user( $u1 );

		// Create an invitation.
		$invite_args = array(
			'user_id'           => $u3,
			'inviter_id'		=> $u1,
			'component_name'    => 'cakes',
			'component_action'  => 'cupcakes',
			'item_id'           => 1,
			'invite_sent'       => 1,
		);
		$i1 = bp_invitations_add_invitation( $invite_args );

		// Create a request.
		$request_args = array(
			'user_id'           => $u3,
			'component_name'    => 'cakes',
			'component_action'  => 'cupcakes',
			'item_id'           => 1,
		);
		$r1 = bp_invitations_add_request( $request_args );

		$get_invites = array(
			'user_id'          => $u3,
			'component_name'   => 'cakes',
			'component_action' => 'cupcakes',
			'accepted'         => 'accepted'
		);
		$invites = bp_invitations_get_invitations( $get_invites );
		$this->assertEqualSets( array( $i1 ), wp_list_pluck( $invites, 'id' ) );

		$this->set_current_user( $old_current_user );
	}

	public function test_bp_invitations_add_invitation_unsent_invite_plus_request_should_not_accept() {
		global $wpdb;
		$old_current_user = get_current_user_id();

		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$this->set_current_user( $u1 );

		// Create an invitation.
		$invite_args = array(
			'user_id'           => $u3,
			'inviter_id'		=> $u1,
			'component_name'    => 'cakes',
			'component_action'  => 'cupcakes',
			'item_id'           => 1,
			'invite_sent'       => 0,
		);
		$i1 = bp_invitations_add_invitation( $invite_args );

		// Create a request.
		$request_args = array(
			'user_id'           => $u3,
			'component_name'    => 'cakes',
			'component_action'  => 'cupcakes',
			'item_id'           => 1,
		);
		$r1 = bp_invitations_add_request( $request_args );

		$get_invites = array(
			'user_id'          => $u3,
			'component_name'   => 'cakes',
			'component_action' => 'cupcakes',
			'accepted'         => 'accepted'
		);
		$invites = bp_invitations_get_invitations( $get_invites );
		$this->assertEqualSets( array(), wp_list_pluck( $invites, 'id' ) );

		$this->set_current_user( $old_current_user );
	}

	public function test_bp_invitations_add_invitation_unsent_invite_plus_request_then_send_invite_should_accept() {
		global $wpdb;
		$old_current_user = get_current_user_id();

		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$this->set_current_user( $u1 );

		// Create an invitation.
		$invite_args = array(
			'user_id'           => $u3,
			'inviter_id'		=> $u1,
			'component_name'    => 'cakes',
			'component_action'  => 'cupcakes',
			'item_id'           => 1,
			'invite_sent'       => 0,
		);
		$i1 = bp_invitations_add_invitation( $invite_args );

		// Create a request.
		$request_args = array(
			'user_id'           => $u3,
			'component_name'    => 'cakes',
			'component_action'  => 'cupcakes',
			'item_id'           => 1,
		);
		$r1 = bp_invitations_add_request( $request_args );

		bp_invitations_send_invitation_by_id( $i1 );

		$get_invites = array(
			'user_id'          => $u3,
			'component_name'   => 'cakes',
			'component_action' => 'cupcakes',
			'accepted'         => 'accepted'
		);
		$invites = bp_invitations_get_invitations( $get_invites );
		$this->assertEqualSets( array( $i1, $r1 ), wp_list_pluck( $invites, 'id' ) );

		$this->set_current_user( $old_current_user );
	}

	public function test_bp_invitations_add_request_vanilla() {
		global $wpdb;
		$old_current_user = get_current_user_id();

		$u1 = $this->factory->user->create();
		$this->set_current_user( $u1 );

		// Create a couple of requests.
		$request_args = array(
			'user_id'           => $u1,
			'component_name'    => 'cakes',
			'component_action'  => 'cupcakes',
			'item_id'           => 7,
		);
		$r1 = bp_invitations_add_request( $request_args );
		$request_args['item_id'] = 4;
		$r2 = bp_invitations_add_request( $request_args );

		$get_requests = array(
			'user_id'        => $u1,
			'component_name' => 'cakes',
			'component_action'  => 'cupcakes',
		);
		$requests = bp_invitations_get_requests( $get_requests );
		$this->assertEqualSets( array( $r1, $r2 ), wp_list_pluck( $requests, 'id' ) );

		$this->set_current_user( $old_current_user );
	}

	public function test_bp_invitations_add_request_avoid_duplicates() {
		global $wpdb;
		$old_current_user = get_current_user_id();

		$u1 = $this->factory->user->create();
		$this->set_current_user( $u1 );

		// Create a couple of requests.
		$request_args = array(
			'user_id'           => $u1,
			'component_name'    => 'cakes',
			'component_action'  => 'cupcakes',
			'item_id'           => 7,
		);
		$r1 = bp_invitations_add_request( $request_args );
		// Attempt to create a duplicate.
		$this->assertFalse( bp_invitations_add_request( $request_args ) );

		$this->set_current_user( $old_current_user );
	}

	public function test_bp_invitations_add_request_request_plus_sent_invite_should_accept() {
		global $wpdb;
		$old_current_user = get_current_user_id();

		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$this->set_current_user( $u1 );

		// Create a request.
		$request_args = array(
			'user_id'           => $u2,
			'component_name'    => 'cakes',
			'component_action'  => 'cupcakes',
			'item_id'           => 1,
		);
		$r1 = bp_invitations_add_request( $request_args );

		// Create an invitation.
		$invite_args = array(
			'user_id'           => $u2,
			'inviter_id'		=> $u1,
			'component_name'    => 'cakes',
			'component_action'  => 'cupcakes',
			'item_id'           => 1,
			'invite_sent'       => 1,
		);
		$i1 = bp_invitations_add_invitation( $invite_args );

		$get_invites = array(
			'user_id'          => $u2,
			'component_name'   => 'cakes',
			'component_action' => 'cupcakes',
			'accepted'         => 'accepted'
		);
		$invites = bp_invitations_get_invitations( $get_invites );
		$this->assertEqualSets( array( $r1, $i1 ), wp_list_pluck( $invites, 'id' ) );

		$this->set_current_user( $old_current_user );
	}

	public function test_bp_get_user_invitations_should_hit_cache() {
		global $wpdb;
		$old_current_user = get_current_user_id();

		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$this->set_current_user( $u1 );

		// Create a couple of invitations.
		$invite_args = array(
			'user_id'           => $u3,
			'inviter_id'		=> $u1,
			'component_name'    => 'blogs',
			'component_action'  => 'blog_invite',
			'item_id'           => 1,
			'type'				=> 'invite',
			'invite_sent'       => 1,
		);
		$i1 = bp_invitations_add_invitation( $invite_args );
		$invite_args['inviter_id'] = $u2;
		$i2 = bp_invitations_add_invitation( $invite_args );

		// Get the invitations.
		$invites = bp_get_user_invitations( $u3 );
		$num_queries = $wpdb->num_queries;
		// Get them again.
		$invites = bp_get_user_invitations( $u3 );
		$this->assertSame( $num_queries, $wpdb->num_queries );
		// Even changing the args shouldn't require a re-query.
		$invites = bp_get_user_invitations( $u3, array( 'component_name' => 'beep_beep', 'invite_sent' => 'draft' ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );

		$this->set_current_user( $old_current_user );
	}
}

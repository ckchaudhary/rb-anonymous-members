<?php
/**
 * BuddyPress groups integration.
 *
 * @package Anonymous Members
 * @subpackage BuddyPress Groups
 * @author ckchaudhary
 */

namespace RecycleBin\AnonymousMembers\Integrations\BuddyPress;

defined( 'ABSPATH' ) ? '' : exit();

/**
 * BuddyPress groups integration.
 *
 * @package Anonymous Members
 * @subpackage BuddyPress Groups
 * @author ckchaudhary
 */
class GroupMembership {
	use \RecycleBin\AnonymousMembers\TraitSingleton;

	/**
	 * Name of the query parameter which indicates that user wants to join anonymously.
	 *
	 * @var string
	 */
	protected $query_param = 'Pegh-SuvwI';

	/**
	 * Id of the user who is trying to join the group anonymously.
	 *
	 * @var int
	 */
	protected $user_id_joining_anonymously = false;

	/**
	 * Key for member meta which stores ids of all groups the given member has joined anonymously.
	 *
	 * @var string
	 */
	protected $meta_key_anonymous_groups = '_bp_groups_joined_anonymously';

	/**
	 * Key for group meta which stores ids of all members who have joined anonymously.
	 *
	 * @var string
	 */
	protected $meta_key_anonymous_members = '_anonymous_members';

	/**
	 * Initialize the object.
	 *
	 * @return void
	 */
	protected function init() {
		// If member has joined the group anonymously,
		// replace 'join group' button with our 'Leave group' button.
		\add_filter( 'bp_get_group_join_button', array( $this, 'replace_default_join_button' ), 2, 2 );
		\add_action( 'bp_group_header_actions', array( $this, 'leave_group_button' ), 6 );

		// Handle button click.
		\add_action( 'bp_actions', array( $this, 'process_group_join_leave' ), 99 );

		$allow_anonymous_members = false;
		if ( \bp_is_group() ) {
			$allow_anonymous_members = (int) groups_get_groupmeta( \bp_get_current_group_id(), 'allow_anonymous_members', true );
		}

		if ( $allow_anonymous_members ) {
			// Add 'join anonymously' button on groups.
			\add_action( 'bp_group_header_actions', array( $this, 'join_anon_button' ), 6 );

			// Show "You've joined anonymously....".
			\add_action( 'bp_group_header_meta', array( $this, 'show_anonymity_info' ) );

			// Act as if users are indeed members if thye've joined the group anonymously.
			// 1. This enables post activity form.
			\add_filter( 'bp_group_is_member', array( $this, 'bp_group_is_member' ), 90, 2 );
		}
	}

	//phpcs:ignore
	#region Join/Leave group.

	/**
	 * Check if the given user is an anonymous member of the given group.
	 *
	 * @param int $group_id Id of the group in question.
	 * @param int $user_id  Id of the user in question.
	 * @return boolean
	 */
	public function is_anonymous_member( $group_id, $user_id ) {
		// Forcefully cast to int, otherwise in_array returns false in some cases.
		$group_id         = absint( $group_id );
		$user_id          = absint( $user_id );
		$anonymous_groups = \bp_get_user_meta( $user_id, $this->meta_key_anonymous_groups, true );
		return ! empty( $anonymous_groups ) && in_array( $group_id, $anonymous_groups, true );
	}

	/**
	 * Act as if users are indeed members if thye've joined the group anonymously.
	 *
	 * @param bool   $is_member If user is a member of group or not.
	 * @param object $group     Group object.
	 * @return bool
	 */
	public function bp_group_is_member( $is_member, $group ) {
		if ( $is_member ) {
			return $is_member;
		}

		return $this->is_anonymous_member( $group->id, \bp_loggedin_user_id() );
	}

	/**
	 * Save information about anonymous membership.
	 *
	 * @since 1.0.0
	 *
	 * @param int $group_id ID of the group.
	 * @param int $user_id  ID of the user joining the group.
	 *
	 * @return void
	 */
	private function add_anonymous_membership_data( $group_id, $user_id ) {
		// Update member and group metas.
		$anonymous_groups = bp_get_user_meta( $user_id, $this->meta_key_anonymous_groups, true );
		if ( empty( $anonymous_groups ) ) {
			$anonymous_groups = array();
		}
		if ( ! in_array( $group_id, $anonymous_groups, true ) ) {
			$anonymous_groups[] = $group_id;
		}
		bp_update_user_meta( $user_id, $this->meta_key_anonymous_groups, $anonymous_groups );

		$anonymous_members = groups_get_groupmeta( $group_id, $this->meta_key_anonymous_members, true );
		if ( empty( $anonymous_members ) ) {
			$anonymous_members = array();
		}
		if ( ! in_array( $user_id, $anonymous_members, true ) ) {
			$anonymous_members[] = $user_id;
		}
		groups_update_groupmeta( $group_id, $this->meta_key_anonymous_members, $anonymous_members );
	}

	/**
	 * Save information about anonymous membership.
	 *
	 * @since 1.0.0
	 *
	 * @param int $group_id ID of the group.
	 * @param int $user_id  ID of the user joining the group.
	 *
	 * @return boolean
	 */
	private function remove_anonymous_membership_data( $group_id, $user_id ) {
		$was_anonymous = false;

		$anonymous_groups = bp_get_user_meta( $user_id, $this->meta_key_anonymous_groups, true );
		if ( ! empty( $anonymous_groups ) && in_array( $group_id, $anonymous_groups, true ) ) {
			$was_anonymous    = true;
			$anonymous_groups = array_diff( $anonymous_groups, array( $group_id ) );
			bp_update_user_meta( $user_id, $this->meta_key_anonymous_groups, $anonymous_groups );
		}

		$anonymous_members = groups_get_groupmeta( $group_id, $this->meta_key_anonymous_members, true );
		if ( ! empty( $anonymous_members ) && in_array( $user_id, $anonymous_members, true ) ) {
			$was_anonymous     = true;
			$anonymous_members = array_diff( $anonymous_members, array( $user_id ) );
			groups_update_groupmeta( $group_id, $this->meta_key_anonymous_members, $anonymous_members );
		}

		return $was_anonymous;
	}

	/**
	 * If member has joined the group anonymously,
	 * dont allow showing the default 'join group' button.
	 *
	 * @param array  $button_args The arguments for the button.
	 * @param object $group       BuddyPress group object.
	 *
	 * @return array
	 */
	public function replace_default_join_button( $button_args, $group ) {
		if ( ! \is_user_logged_in() || ! $this->is_anonymous_member( $group->id, \bp_loggedin_user_id() ) ) {
			return $button_args;
		}

		return array();
	}

	/**
	 * Prints a 'leave group' link, for members who have joined anonymously.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function leave_group_button() {
		$group = groups_get_current_group();
		if ( ! \is_user_logged_in() || ! $this->is_anonymous_member( $group->id, \bp_loggedin_user_id() ) ) {
			return false;
		}

		$reomve_anon_url = add_query_arg( array( $this->query_param => 'teq' ), bp_get_group_url( $group ) );
		$reomve_anon_url = wp_nonce_url( $reomve_anon_url, $this->query_param );

		printf(
			'<div class="generic-button"><a href="%s" class="button group-button"><i class="rb-am-icon gg-ghost-character"></i> %s</a></div>',
			esc_url( $reomve_anon_url ),
			esc_html__( 'Leave Group', 'rb-anonymous-members' )
		);

		return true;
	}

	/**
	 * Prints a 'join anonymously' button in group headers.
	 *
	 * @return void|boolean
	 */
	public function join_anon_button() {
		global $groups_template;

		// Set group to current loop group if none passed.
		if ( empty( $group ) ) {
			$group =& $groups_template->group;
		}

		if ( ! \is_user_logged_in() ) {
			return false;
		}

		// Already a normal member.
		if ( $group->is_member ) {
			return false;
		}

		// Already an anomymous member.
		if ( $this->is_anonymous_member( $group->id, \bp_loggedin_user_id() ) ) {
			return false;
		}

		$join_anon_url = add_query_arg( array( $this->query_param => 'muv' ), bp_get_group_url( $group ) );
		$join_anon_url = wp_nonce_url( $join_anon_url, $this->query_param );

		$html  = '<div class="group-button public join-anonymously generic-button" id="groupbutton-join-anonymous">';
		$html .= sprintf(
			'<a href="%s" class="group-button" title="%s"><span class="rb-am-icon gg-ghost-character"></span>%s</a>',
			esc_url( $join_anon_url ),
			esc_html__( 'Join group anonymously', 'rb-anonymous-members' ),
			esc_html__( 'Join Anonymously', 'rb-anonymous-members' )
		);
		$html .= '</div>';

		$integration = rb_anonymous_members()->get_integration( 'buddypress_groups' );
		$info_url    = $integration->get_option( 'url_membership_info' );
		if ( $info_url ) {
			$html .= sprintf(
				'<a class="rb-am-group-info" href="%s" title="%s"><span class="rb-am-icon.gg-info"></span></a>',
				esc_url( $info_url ),
				esc_html__( 'know more', 'rb-anonymous-members' )
			);
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;
	}

	/**
	 * Process 'join group anonymously', 'leave group' (if joined anonymously), actions.
	 *
	 * @return bool
	 */
	public function process_group_join_leave() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! \bp_is_group() || ! isset( $_GET[ $this->query_param ] ) || ! isset( $_GET['_wpnonce'] ) || ! \is_user_logged_in() ) {
			return false;
		}

		$action = sanitize_key( wp_unslash( $_GET[ $this->query_param ] ) );
		// That's kligon for 'add' and 'remove' ;) .
		if ( ! in_array( $action, array( 'muv', 'teq' ), true ) ) {
			return false;
		}

		$nonce_val = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		if ( ! \wp_verify_nonce( $nonce_val, $this->query_param ) ) {
			return false;
		}

		$group         = \groups_get_current_group();
		$did_something = false;
		if ( 'teq' === $action && $this->is_anonymous_member( $group->id, \bp_loggedin_user_id() ) ) {
			$this->cancel_anonymous_membership( $group->id, \bp_loggedin_user_id() );
			$did_something = true;
		} elseif ( 'muv' === $action ) {
			$this->join_anonymously( $group->id, \bp_loggedin_user_id() );
			$did_something = true;
		}

		if ( $did_something ) {
			bp_core_redirect( bp_get_group_url( $group->id ) );
		}

		return true;
	}

	/**
	 * Make the give user an anonymous member of given group.
	 *
	 * @param int $group_id Id of the group.
	 * @param int $org_user_id Id of the actual user.
	 * @return void
	 */
	protected function join_anonymously( $group_id, $org_user_id ) {
		// Where's stig?
		$anon_user = rb_anonymous_members()->get_anonymous_user();
		if ( $anon_user ) {
			$this->user_id_joining_anonymously = $org_user_id;

			// Add stig as the group member instead of the actual user.
			$joined = \groups_join_group( $group_id, $anon_user->ID );

			// Save info.
			$this->add_anonymous_membership_data( $group_id, $org_user_id );

			// Delete group join activity etc.
			if ( $joined ) {
				\add_action( 'groups_join_group', array( $this, 'after_join_group' ), 5, 2 );
			}
		}
	}

	/**
	 * Remove an anonymous user from a group.
	 *
	 * @param int $group_id    Id of the group in question.
	 * @param int $org_user_id Id of the user is question.
	 * @return void
	 */
	protected function cancel_anonymous_membership( $group_id, $org_user_id ) {
		// Save info.
		$this->remove_anonymous_membership_data( $group_id, $org_user_id );

		// @todo: Remove stig from group membership if there are no more anonymous members.
	}

	/**
	 * Do stuff after member joins a group.
	 *
	 * @since 1.0.0
	 *
	 * @param int $group_id ID of the group.
	 * @param int $user_id  ID of the user joining the group.
	 *
	 * @return bool|void
	 */
	public function after_join_group( $group_id, $user_id ) {
		$anon_user = rb_anonymous_members()->get_anonymous_user();
		if ( $anon_user && $anon_user->ID === $user_id ) {
			// Delete activity which was just added.
			if ( \bp_is_active( 'activity' ) ) {
				\bp_activity_delete(
					array(
						'component' => \buddypress()->groups->id,
						'type'      => 'joined_group',
						'user_id'   => $anon_user->id, // Stig's user id.
						'item_id'   => $group_id,
					)
				);
			}
		}

		// Don't update last activity.
		/* \remove_action( 'groups_join_group', '\groups_update_last_activity' ); */
	}

	/**
	 * Do stuff after member leaves a group.
	 *
	 * @since 1.0.0
	 *
	 * @param int $group_id ID of the group.
	 * @param int $user_id  ID of the user joining the group.
	 *
	 * @return bool|void
	 */
	public function after_leave_group( $group_id, $user_id ) {
		$was_anonymous = $this->remove_anonymous_membership_data( $group_id, $user_id );

		if ( ! $was_anonymous ) {
			return false;
		}

		/**
		 * We don't need to to adjust the member's groups count and group's members count.
		 * As those aren't just reduced by 1.
		 * The actual number is deduced by checking the membership table.
		 * So it is already correct.
		 */
	}

	/**
	 * On single group page, inform the user if they've joined the group anonymously.
	 *
	 * @return void
	 */
	public function show_anonymity_info() {
		if ( \bp_is_group() && \is_user_logged_in() ) {
			if ( $this->is_anonymous_member( \bp_get_current_group_id(), \bp_loggedin_user_id() ) ) {
				$alias       = \RecycleBin\AnonymousMembers\SecretIdentity::get_instance()->get( \bp_loggedin_user_id() );
				$secret_name = '<strong>' . esc_html( $alias['name'] ) . '</strong>';
				$message     = sprintf(
					/* translators: 1: alias */
					__( 'You have joined this group anonymously as %s.', 'rb-anonymous-members' ),
					$secret_name
				);

				$integration = rb_anonymous_members()->get_integration( 'buddypress_groups' );
				$info_url    = $integration->get_option( 'url_membership_info' );
				if ( $info_url ) {
					$message .= sprintf(
						'<a class="rb-am-group-info" href="%s" title="%s"><span class="rb-am-icon gg-info"></span></a>',
						esc_url( $info_url ),
						esc_html__( 'know more', 'rb-anonymous-members' )
					);
				}

				printf(
					'<p class="rb-anonymous-membership"><span class="rb-am-icon gg-ghost-character"></span>%s</span></p>',
					$message // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
		}
	}

	//phpcs:ignore
	#endregion 

	//phpcs:ignore
	#region hide anonymous members and groups
	/**
	 * When querying for members exclude anonymous members.
	 *
	 * @param array                 $group_member_ids Array of associated member IDs.
	 * @param BP_Group_Member_Query $user_query       Current BP_Group_Member_Query instance.
	 * @return array
	 */
	public function filter_group_member_query( $group_member_ids, $user_query ) {
		if ( empty( $group_member_ids ) ) {
			return $group_member_ids;
		}

		$group_ids = \wp_parse_id_list( $user_query->query_vars['group_id'] );
		if ( ! empty( $group_ids ) ) {
			foreach ( $group_ids as $group_id ) {
				$anonymous_members = groups_get_groupmeta( $group_id, $this->meta_key_anonymous_members, true );
				if ( ! empty( $anonymous_members ) ) {
					$group_member_ids = array_diff( $group_member_ids, $anonymous_members );
				}
			}
		}

		return $group_member_ids;
	}

	/**
	 * Filter arguments of bp_has_groups calls.
	 *
	 * @param array $args Existing arguments.
	 * @return array
	 */
	public function filter_bp_has_groups_args( $args ) {
		if ( ! isset( $args['user_id'] ) || empty( $args['user_id'] ) ) {
			return $args;
		}

		// Show all groups if viewing your own profile.
		if ( \bp_loggedin_user_id() === $args['user_id'] ) {
			return $args;
		}

		$anonymous_groups = bp_get_user_meta( $args['user_id'], $this->meta_key_anonymous_groups, true );
		if ( empty( $anonymous_groups ) ) {
			return $args;
		}

		$exclude         = isset( $args['exclude'] ) && ! empty( $args['exclude'] ) ? \wp_parse_id_list( $args['exclude'] ) : array();
		$args['exclude'] = array_merge( $exclude, $anonymous_groups );

		return $args;
	}

	//phpcs:ignore
	#endregion

	/**
	 * Filter the bp_user_can value to determine what the user can do
	 * with regards to a specific group.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $retval     Whether or not the current user has the capability.
	 * @param int    $user_id    The id of the user in question.
	 * @param string $capability The capability being checked for.
	 * @param int    $site_id    Site ID. Defaults to the BP root blog.
	 * @param array  $args       Array of extra arguments passed.
	 *
	 * @return bool
	 */
	public function bp_groups_user_can_filter( $retval, $user_id, $capability, $site_id, $args ) {
		if ( ! $user_id ) {
			return $retval;
		}

		if ( empty( $args['group_id'] ) ) {
			$group_id = bp_get_current_group_id();
		} else {
			$group_id = (int) $args['group_id'];
		}
		if ( ! $group_id ) {
			return $retval;
		}

		switch ( $capability ) {
			case 'groups_send_invitation':
				// All's well if ther user can't send invitation anyway.
				if ( ! $retval ) {
					break;
				}

				// Anonymous members can't send invitations.
				if ( $this->is_anonymous_member( $group_id, $user_id ) ) {
					$retval = false;
				}
				break;
		}

		return $retval;
	}
}

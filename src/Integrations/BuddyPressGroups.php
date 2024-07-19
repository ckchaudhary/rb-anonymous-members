<?php
/**
 * BuddyPress groups integration.
 *
 * @todo: Remove this file, it is no longer necessary.
 * The code has been distributed into various files inside BuddyPress folder.
 * 
 * @package Anonymous Members
 * @author ckchaudhary
 */

namespace RecycleBin\AnonymousMembers\Integrations;

use RecycleBin\AnonymousMembers\SecretIdentity;
use stdClass;

defined( 'ABSPATH' ) ? '' : exit();

/**
 * BuddyPress groups integration.
 *
 * @package Anonymous Members
 * @author ckchaudhary
 */
class BuddyPressGroups extends Integration {
	//phpcs:ignore
	#region properties

	/**
	 * Name of the query parameter which indicates that user wants to join anonymously.
	 *
	 * @var string
	 */
	protected $query_param = 'nuqneH';

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
	 * Undocumented variable
	 *
	 * @var boolean
	 */
	protected $dealing_with_contained_elmenets = false;

	//phpcs:ignore
	#endregion

	/**
	 * Initialize the object.
	 *
	 * @return void
	 */
	public function init() {
		// Add 'join anonymously' button on groups.
		add_action( 'bp_group_header_actions', array( $this, 'join_button' ), 6 );

		// Show "You've joined anonymously....".
		\add_action( 'bp_group_header_meta', array( $this, 'show_anonymity_info' ) );

		// Do stuff after a member is added to a group.
		// We need to hook pretty early, so that we can unhook some other functions.
		\add_action( 'groups_join_group', array( $this, 'after_join_group' ), 5, 2 );

		// Do stuff after a member is removed from a group.
		\add_action( 'groups_leave_group', array( $this, 'after_leave_group' ), 10, 2 );

		// Hide anonymous members from group's member query.
		\add_filter( 'bp_group_member_query_group_member_ids', array( $this, 'filter_group_member_query' ), 10, 2 );

		// Hide anonymous groups from member's group query.
		\add_filter( 'bp_after_has_groups_parse_args', array( $this, 'filter_bp_has_groups_args' ), 90 );

		// Obfuscate activity posting.
		\add_filter( 'bp_groups_format_activity_action_group_activity_update', array( $this, 'action_group_activity_update' ), 3, 2 );
		\add_filter( 'bp_get_activity_action_pre_meta', array( $this, 'action_group_activity_update' ), 90, 2 );

		// Hide activity avatar.
		\add_filter( 'bp_get_activity_avatar', array( $this, 'hide_activity_avatar' ), 90 );

		// Hide user link in acvitiy.
		\add_filter( 'bp_get_activity_user_link', array( $this, 'hide_activity_user_link' ), 90 );
		\add_filter( 'bp_activity_comment_user_link', array( $this, 'hide_activity_user_link' ), 90 );

		// Hide activity commenter's name.
		\add_filter( 'bp_activity_comment_name', array( $this, 'hide_subjects_name' ), 90 );

		// Prevent redirecting activity/xxx to members/xyz/activity/xxx.
		\add_filter( 'bp_activity_permalink_redirect_url', array( $this, 'prevent_activity_permalink_redirect' ), 90, 2 );

		// in the post actiivty form, change "what's new real_name" to "what's new alias".
		\add_filter( 'bp_core_get_js_strings', array( $this, 'bp_core_get_js_strings' ), 90 );

		// \BP_Groups_Member::get_group_member_ids( $group_id ) returns anonymous members as well, fix that. - Can't fix it.
		// @todo: filter notifications
		//\add_filter( 'bp_get_the_notification_description', array( $this, 'filter_notification_description' ), 90, 2 );
		\add_filter( 'bp_activity_single_update_reply_notification', array( $this, 'filter_update_reply_notification_description' ), 90, 5 );
		\add_filter( 'bp_activity_multiple_update_reply_notification', array( $this, 'filter_update_reply_notification_description' ), 90, 5 );

		\add_filter( 'bp_activity_single_comment_reply_notification', array( $this, 'filter_update_comment_notification_description' ), 90, 5 );
		\add_filter( 'bp_activity_multiple_comment_reply_notification', array( $this, 'filter_update_comment_notification_description' ), 90, 5 );
		// @todo: anonymous members shouldn't be able to send invitations
		// @todo: hide member details in all sorts of emails - check calls of bp_send_email

		/*
		Single group template starts

			If the loggedin user is an anonymous member
				- Filter all calls to loggedin_user_avatar, url, name etc and show alias
				These may be useful in unforseen cases.

		Single group template ends
		*/
		\add_action( 'bp_before_activity_post_form', array( $this, 'maybe_hook_filter_contained_elements' ) );
		\add_action( 'bp_before_group_body', array( $this, 'hook_filter_contained_elements' ) );
		\add_action( 'bp_after_group_body', array( $this, 'unhook_filter_contained_elements' ) );
		\add_filter( 'bp_get_loggedin_user_avatar', array( $this, 'loggedin_user_avatar_in_anon_group' ), 90, 3 );
		\add_filter( 'bp_get_loggedin_user_fullname', array( $this, 'loggedin_user_alias' ), 90 );
		\add_filter( 'bp_get_loggedin_user_username', array( $this, 'loggedin_user_alias' ), 90 );
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
		$group_id = absint( $group_id );
		$user_id  = absint( $user_id );
		$anonymous_groups = \bp_get_user_meta( $user_id, $this->meta_key_anonymous_groups, true );
		return ! empty( $anonymous_groups ) && in_array( $group_id, $anonymous_groups, true );
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
	 * Prints a 'join anonymously' button in group headers.
	 *
	 * @return void|boolean
	 */
	public function join_button() {
		global $groups_template;

		// Set group to current loop group if none passed.
		if ( empty( $group ) ) {
			$group =& $groups_template->group;
		}

		// Already a member.
		if ( ! empty( $group->is_member ) ) {
			return false;
		}

		$button_args = bp_groups_get_group_join_button_args( $group );

		if ( ! array_filter( $button_args ) ) {
			return false;
		}

		$button_args['wrapper_id']     = 'groupbutton-join-anonymous';
		$button_args['wrapper_class'] .= ' join-anonymously';
		$button_args['link_text']      = '<span class="rb-am-icon gg-ghost-character"></span>' . esc_html__( 'Join Anonymously', 'rb-anonymous-members' );
		$button_args['link_title']     = esc_html__( 'Join Group Anonymously', 'rb-anonymous-members' );
		$button_args['link_href']      = add_query_arg( array( $this->query_param => '1' ), $button_args['link_href'] );

		echo bp_get_button( $button_args );//phpcs:ignore
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
		// Do nothing if member isn't joining anonymously.
		if ( ! isset( $_REQUEST[ $this->query_param ] ) ) {//phpcs:ignore
			return false;
		}

		// Save info.
		$this->add_anonymous_membership_data( $group_id, $user_id );

		// Don't update last activity.
		// \remove_action( 'groups_join_group', '\groups_update_last_activity' );

		// Delete activity which was just added.
		if ( \bp_is_active( 'activity' ) ) {
			\bp_activity_delete(
				array(
					'component' => \buddypress()->groups->id,
					'type'      => 'joined_group',
					'user_id'   => $user_id,
					'item_id'   => $group_id,
				)
			);
		}

		// Reduce user's total group count, which was just increased by 1.
		// Is that useful though?
		/*$total_group_count = (int) bp_get_user_meta( $user_id, 'total_group_count', true );
		if ( $total_group_count > 0 ) {
			--$total_group_count;
		}
		bp_update_user_meta( $user_id, 'total_group_count', $total_group_count );

		// Reduce group's total member count, which was just increased by 1.
		// Is that useful though?
		$total_members_count = (int) groups_get_groupmeta( $group_id, 'total_member_count', true );
		if ( $total_members_count > 0 ) {
			--$total_members_count;
		}
		groups_update_groupmeta( $group_id, 'total_member_count', $total_members_count );*/
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
				$alias       = SecretIdentity::get_instance()->get( \bp_loggedin_user_id() );
				$secret_name = '<strong>' . esc_html( $alias['name'] ) . '</strong>';
				$message     = sprintf(
					/* translators: 1: alias */
					__( 'You have joined this group anonymously as %s.', 'rb-anonymous-members' ),
					$secret_name
				);
				printf(
					'<p class="rb-anonymous-membership"><span class="rb-am-icon gg-ghost-character"></span><span class="rb-am-text">%s</span></p>',
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

	//phpcs:ignore
	#region obfuscate activities
	/**
	 * Obfuscate member's info if the activity should be anonymous.
	 *
	 * @param string $action   The Group's activity update action.
	 * @param object $activity Activity data object.
	 * @return string
	 */
	public function action_group_activity_update( $action, $activity ) {
		if ( ! $this->is_anonymous_member( $activity->item_id, $activity->user_id ) ) {
			return $action;
		}

		$secret = \RecycleBin\AnonymousMembers\SecretIdentity::get_instance()->get( $activity->user_id );

		if ( empty( $secret ) ) {
			return $action;
		}

		$user_link = sprintf(
			'<span class="rb-anonymous-membership"><span class="rb-am-icon gg-ghost-character"></span><span class="rb-am-text">%s</span></span>',
			esc_html( $secret['name'] )
		);

		// Set the Activity update posted in a Group action.
		$action = sprintf(
			/* translators: 1: the user's secret name. */
			esc_html__( '%1$s posted an update.', 'rb-anonymous-members' ),
			$user_link
		);

		return $action;
	}

	/**
	 * Obfuscate member profile link if the activity should be anonymous.
	 *
	 * @param string $link original url.
	 * @return string
	 */
	public function hide_activity_user_link( $link ) {
		global $activities_template;

		// Within the activity comment loop, the current activity should be set
		// to current_comment. Otherwise, just use activity.
		$activity = isset( $activities_template->activity->current_comment ) ? $activities_template->activity->current_comment : $activities_template->activity;
		if ( $this->is_activity_anonymous( $activity ) ) {
			$secret = \RecycleBin\AnonymousMembers\SecretIdentity::get_instance()->get( $activity->user_id );
			$link   = $secret['url'];
		}

		return $link;
	}

	/**
	 * Hide user's avatar from activity entry if the activity should be anonymous.
	 *
	 * @param string $html Formatted HTML img element.
	 * @return string
	 */
	public function hide_activity_avatar( $html ) {
		global $activities_template;

		// Within the activity comment loop, the current activity should be set
		// to current_comment. Otherwise, just use activity.
		$activity = isset( $activities_template->activity->current_comment ) ? $activities_template->activity->current_comment : $activities_template->activity;

		// If the activity doesn't need to be anonymous.
		if ( ! $this->is_activity_anonymous( $activity ) ) {
			return $html;
		}

		$bp = buddypress();

		// On activity permalink pages, default to the full-size avatar.
		$type_default = bp_is_single_activity() ? 'full' : 'thumb';

		$img_height = 150;
		$img_width  = 150;
		if ( isset( $bp->avatar->full->height ) || isset( $bp->avatar->thumb->height ) ) {
			$img_height = ( 'full' === $type_default ) ? $bp->avatar->full->height : $bp->avatar->thumb->height;
		} else {
			$img_height = 20;
		}

		if ( isset( $bp->avatar->full->width ) || isset( $bp->avatar->thumb->width ) ) {
			$img_width = ( 'full' === $type_default ) ? $bp->avatar->full->width : $bp->avatar->thumb->width;
		} else {
			$img_width = 20;
		}

		$secret = \RecycleBin\AnonymousMembers\SecretIdentity::get_instance()->get();

		return sprintf(
			'<img loading="lazy" src="%1$s" class="avatar" width="%2$d" height="%3$d" alt="%4$s">',
			esc_url( $secret['avatar'] ),
			$img_width,
			$img_height,
			esc_attr__( 'Profile picture', 'rb-anonymous-members' )
		);
	}

	/**
	 * For group activity and comments, if the member has joined anonymously, hide his/her name.
	 *
	 * @param string $name Original name to be displayed.
	 * @return string
	 */
	public function hide_subjects_name( $name ) {
		global $activities_template;

		// Within the activity comment loop, the current activity should be set
		// to current_comment. Otherwise, just use activity.
		$activity = isset( $activities_template->activity->current_comment ) ? $activities_template->activity->current_comment : $activities_template->activity;

		if ( $this->is_activity_anonymous( $activity ) ) {
			$secret = \RecycleBin\AnonymousMembers\SecretIdentity::get_instance()->get( $activity->user_id );
			$name   = $secret['name'];
		}

		return $name;
	}

	/**
	 * Prevent redirecting activity/xxx to members/xyz/activity/xxx.
	 *
	 * @param string $redirect Url to redirect to.
	 * @param object $activity activity object.
	 * @return string
	 */
	public function prevent_activity_permalink_redirect( $redirect, $activity ) {
		if ( $this->is_anonymous_member( $activity->item_id, $activity->user_id ) ) {
			$redirect = '';
		}

		return $redirect;
	}

	/**
	 * In the post actiivty form, change "what's new real_name" to "what's new alias".
	 *
	 * @param array $strings trnslations.
	 * @return array
	 */
	public function bp_core_get_js_strings( $strings ) {
		if ( ! \bp_is_group_activity() ) {
			return $strings;
		}

		if ( ! isset( $strings['activity'] ) || ! isset( $strings['activity']['params'] ) ) {
			return $strings;
		}

		$group_id = \bp_get_current_group_id();
		$user_id  = \bp_loggedin_user_id();

		if ( ! $this->is_anonymous_member( $group_id, $user_id ) ) {
			return $strings;
		}

		$alias = SecretIdentity::get_instance()->get( $user_id );

		$strings['activity']['params']['avatar_url'] = $alias['avatar'];
		$strings['activity']['params']['avatar_alt'] = '';
		$strings['activity']['params']['user_domain'] = $alias['url'];

		$strings['activity']['strings']['whatsnewPlaceholder'] = sprintf(
			// translators: alias/secret-name.
			__( "What's new, %s?", 'buddypress' ),
			$alias['name'],
		);

		return $strings;
	}

	/**
	 * Should the given activity be anonymous?
	 *
	 * @param object $activity  Activity object.
	 * @param int    $member_id Member who did the activity.
	 * @return boolean
	 */
	protected function is_activity_anonymous( $activity, $member_id = false ) {
		global $wpdb;
		$bp = buddypress();

		if ( ! isset( $activity->component ) || ! isset( $activity->type ) ) {
			/**
			 * In ajax request, when posting a reply for example,
			 * the parent activity is dummy and doesn't have actual data like component, type, etc.
			 * We can't work with that, so we need to fetch other details for the parent activity.
			 */
			$activity = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$bp->activity->table_name} WHERE id = %d",
					$activity->id
				)
			);
		}

		if ( ! isset( $activity->component ) || ! isset( $activity->type ) ) {
			return false;// can't do anything :( .
		}

		$flag = false;
		if ( ! $member_id ) {
			$member_id = $activity->user_id;
		}

		/**
		 * Component: groups. Type: activity_update
		 * If the member has joined the group anonymously, the activity must be anonymous.
		 *
		 * Component: activity. Type: activity_comment
		 * This maybe a comment on a group activity. If the member has joined the group anonymously, the comment must also be anonymous.
		 */
		if ( 'groups' === $activity->component ) {
			$flag = $this->is_anonymous_member( $activity->item_id, $member_id );
		} elseif ( 'activity' === $activity->component && 'activity_comment' === $activity->type ) {
			global $activities_template;
			// Fetch the root level ancestor activity.
			$ancestor = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$bp->activity->table_name} WHERE id = %d",
					$activity->item_id
				)
			);
			if ( $ancestor ) {
				$flag = $this->is_activity_anonymous( $ancestor, $member_id );
			}
		}

		return $flag;
	}

	//phpcs:ignore
	#endregion

	//phpcs:ignore
	#region contained elements

	public function maybe_hook_filter_contained_elements() {
		if ( \bp_is_active( 'groups' ) && \bp_is_group() ) {
			$this->hook_filter_contained_elements();
		}
	}

	public function hook_filter_contained_elements() {
		$this->dealing_with_contained_elmenets = true;
	}

	public function unhook_filter_contained_elements() {
		$this->dealing_with_contained_elmenets = false;
	}

	/**
	 * Filter the user avatar when displayed inside a group,
	 * if the member has joined the group anonymously.
	 *
	 * @param string $avatar User avatar string.
	 * @param array  $r      Array of parsed arguments.
	 * @param array  $args   Array of initial arguments.
	 *
	 * @retrn string
	 */
	public function loggedin_user_avatar_in_anon_group( $avatar, $r, $args ) {
		if ( ! $this->dealing_with_contained_elmenets ) {
			return $avatar;
		}

		$group_id = \bp_get_current_group_id();
		$user_id  = \bp_loggedin_user_id();
		if ( ! $this->is_anonymous_member( $group_id, $user_id ) ) {
			return $avatar;
		}

		$secret = \RecycleBin\AnonymousMembers\SecretIdentity::get_instance()->get( $user_id );

		return sprintf(
			'<img loading="lazy" src="%1$s" class="avatar" width="%2$d" height="%3$d" alt="%4$s">',
			esc_url( $secret['avatar'] ),
			isset( $r['width'] ) ? absint( $r['width'] ) : '',
			isset( $r['height'] ) ? absint( $r['height'] ) : '',
			esc_attr__( 'Profile picture', 'rb-anonymous-members' )
		);
	}

	/**
	 * Filter the username, full name etc, when displayed inside a group,
	 * if the member has joined the group anonymously.
	 *
	 * @param string $real Real name, username etc.
	 * @return string
	 */
	public function loggedin_user_alias( $real ) {
		if ( ! $this->dealing_with_contained_elmenets ) {
			return $real;
		}

		$group_id = \bp_get_current_group_id();
		$user_id  = \bp_loggedin_user_id();
		if ( ! $this->is_anonymous_member( $group_id, $user_id ) ) {
			return $real;
		}

		$secret = \RecycleBin\AnonymousMembers\SecretIdentity::get_instance()->get( $user_id );
		return $secret['name'];
	}

	//phpcs:ignore
	#endregion

	//phpcs:ignore
	#region Obfuscate Notifications

	/**
	 * Hide anonymous member's names from notifications, when required.
	 *
	 * @param string                         $description text.
	 * @param \BP_Notifications_Notification $notification object.
	 * @return string
	 */
	public function filter_notification_description( $description, $notification ) {
		$actions = array( 'update_reply', 'comment_reply' );
		if ( 'activity' === $notification->component_name && in_array( $notification->component_action, $actions, true ) ) {
			$activity_id  = $notification->item_id;
			$commenter_id = $notification->secondary_item_id;
			if ( $activity_id && $commenter_id ) {
				$activity_obj     = new stdClass();
				$activity_obj->id = $activity_id;
				if ( $this->is_activity_anonymous( $activity_obj, $commenter_id ) ) {
					$text = '';
					$link = '';
					switch ( $notification->component_action ) {
						case 'update_reply':
							$text = __( 'You have comments on one of your updates.', 'rb-anonymous-members' );
							$link = add_query_arg( 'rid', (int) $notification->id, \bp_activity_get_permalink( $activity_id ) );
							break;

						case 'comment_reply':
							$text = __( 'You have replies to one of your comments.', 'rb-anonymous-members' );
							$link = add_query_arg( 'crid', (int) $notification->id, \bp_activity_get_permalink( $activity_id ) );
							break;
					}

					if ( ! empty( $text ) && ! empty( $link ) ) {
						$description = sprintf(
							'<a href="%s">%s</a>',
							esc_url( $link ),
							esc_html( $text )
						);
					}
				}
			}
		}
		return $description;
	}

	/**
	 * Hide anonymous member's names from notifications, when required.
	 *
	 * @param string|array $description     HTML anchor tag or array containing text and url.
	 * @param string       $link            The permalink for the interaction.
	 * @param int          $total_items     How many items being notified about.
	 * @param int          $activity_id     ID of the activity item being formatted.
	 * @param int          $user_id         ID of the user who inited the interaction.
	 *
	 * @return string|array
	 */
	public function filter_update_reply_notification_description( $description, $link, $total_items, $activity_id, $user_id ) {
		$activity_obj     = new stdClass();
		$activity_obj->id = $activity_id;
		if ( $this->is_activity_anonymous( $activity_obj ) ) {
			$text = __( 'You have comments on one of your updates.', 'rb-anonymous-members' );
			if ( $total_items < 2 ) {
				$text = __( 'You have a new comment on one of your updates.', 'rb-anonymous-members' );
			}
			$link = \bp_activity_get_permalink( $activity_id );

			if ( is_array( $description ) ) {
				$description['text'] = $text;
				$description['link'] = $link;
			} else {
				$description = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $link ),
					esc_html( $text )
				);
			}
		}

		return $description;
	}

	/**
	 * Hide anonymous member's names from notifications, when required.
	 *
	 * @param string|array $description     HTML anchor tag or array containing text and url.
	 * @param string       $link            The permalink for the interaction.
	 * @param int          $total_items     How many items being notified about.
	 * @param int          $activity_id     ID of the activity item being formatted.
	 * @param int          $user_id         ID of the user who inited the interaction.
	 *
	 * @return string|array
	 */
	public function filter_comment_reply_notification_description( $description, $link, $total_items, $activity_id, $user_id ) {
		$activity_obj     = new stdClass();
		$activity_obj->id = $activity_id;
		if ( $this->is_activity_anonymous( $activity_obj ) ) {
			$text = __( 'You have replies on one of your comments.', 'rb-anonymous-members' );
			if ( $total_items < 2 ) {
				$text = __( 'You have a new reply on one of your comments.', 'rb-anonymous-members' );
			}
			$link = \bp_activity_get_permalink( $activity_id );

			if ( is_array( $description ) ) {
				$description['text'] = $text;
				$description['link'] = $link;
			} else {
				$description = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $link ),
					esc_html( $text )
				);
			}
		}

		return $description;
	}

	//phpcs:ignore
	#endregion
}

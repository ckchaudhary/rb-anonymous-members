<?php
/**
 * The main plugin class.
 *
 * @package Anonymous Members
 * @author ckchaudhary
 */

namespace RecycleBin\AnonymousMembers;

defined( 'ABSPATH' ) ? '' : exit();

/**
 * The main plugin class.
 *
 * @package Anonymous Members
 * @author ckchaudhary
 */
final class Plugin {
	use TraitSingleton;

	/**
	 * Name of the url parameter which indicates that user wants to join anonymously.
	 *
	 * @var string
	 */
	private $url_param = 'nuqneH';

	/**
	 * Key for member meta which stores ids of all groups the given member has joined anonymously.
	 *
	 * @var string
	 */
	private $meta_key_anonymous_groups = '_bp_groups_joined_anonymously';

	/**
	 * Key for group meta which stores ids of all members who have joined anonymously.
	 *
	 * @var string
	 */
	private $meta_key_anonymous_members = '_joined_anonymously';

	/**
	 * Initialize the object.
	 *
	 * @return void
	 */
	public function init() {
		// Add 'join anonymously' button on groups.
		add_action( 'bp_group_header_actions', array( $this, 'join_button' ), 6 );

		// @todo: Figure out a way to pass additional info in ajax request for joining a group.

		// Do stuff after a member is added to a group.
		\add_action( 'groups_member_after_save', array( $this, 'after_member_added' ) );

		// Do stuff after a member is removed from a group.
		\add_action( 'groups_member_after_remove', array( $this, 'after_member_removed' ) );

		// Hide anonymous members from group's member query.
		// \add_filter( 'bp_group_member_query_group_member_ids', array( $this, 'exclude_anonymous_members_from_group_member_query' ), 10, 2 );

		// Hide anonymous groups from member's group query.
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
		$button_args['link_text']      = esc_html__( 'Join Anonymously', 'rb-anonymous-members' );
		$button_args['link_title']     = esc_html__( 'Join Group Anonymously', 'rb-anonymous-members' );
		$button_args['link_href']      = add_query_arg( array( $this->url_param => '1' ), $button_args['link_href'] );

		echo bp_get_button( $button_args );//phpcs:ignore
	}

	/**
	 * Update some information in database if the membership was anonymous.
	 *
	 * @param BP_Groups_Member $member Current instance of the group membership item has been saved. Passed by reference.
	 *
	 * @return void|bool
	 */
	public function after_member_added( $member ) {
		// Do nothing if member isn't joining anonymously.
		if ( ! isset( $_REQUEST[ $this->url_param ] ) ) {//phpcs:ignore
			return false;
		}

		$user_id  = $member->user_id;
		$group_id = $member->group_id;

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

		// Decrease user's group count.

		// Decrease group's member count.
	}

	/**
	 * Remove information about anoymous membership for given group and member.
	 *
	 * @param \BP_Groups_Member $member Current group membership object.
	 * @return void
	 */
	public function after_member_removed( $member ) {
		$was_anonymous = false;

		$user_id  = $member->user_id;
		$group_id = $member->group_id;

		/*
		Remove member from group's anonymous member meta.
		Remove group from member's anonymous groups meta.
		If the membership was anonymous
			1. Increase the count of members for group
			2. Increase the count of groups for member
		*/

		$anonymous_groups = bp_get_user_meta( $user_id, $this->meta_key_anonymous_groups, true );
		if ( ! empty( $anonymous_groups ) && in_array( $group_id, $anonymous_groups, true ) ) {
			$was_anonymous = true;
			$anonymous_groups = array_diff( $anonymous_groups, array( $group_id ) );
			bp_update_user_meta( $user_id, $this->meta_key_anonymous_groups, $anonymous_groups );
		}

		$anonymous_members = groups_get_groupmeta( $group_id, $this->meta_key_anonymous_members, true );
		if ( ! empty( $anonymous_members ) && in_array( $user_id, $anonymous_members, true ) ) {
			$was_anonymous = true;
			$anonymous_members = array_diff( $anonymous_members, array( $user_id ) );
			groups_update_groupmeta( $group_id, $this->meta_key_anonymous_members, $anonymous_members );
		}
	}

	/**
	 * Undocumented function
	 *
	 * @param array                 $group_member_ids Array of associated member IDs.
	 * @param BP_Group_Member_Query $user_query       Current BP_Group_Member_Query instance.
	 * @return array
	 */
	public function exclude_anonymous_members_from_group_member_query( $group_member_ids, $user_query ) {
		if ( empty( $group_member_ids ) ) {
			return $group_member_ids;
		}

		$anonymous_members = groups_get_groupmeta( $user_query->group_id, $this->meta_key_anonymous_members, true );
		if ( ! empty( $anonymous_members ) ) {
			$group_member_ids = array_diff( $group_member_ids, $anonymous_members );
		}
		return $group_member_ids;
	}
}

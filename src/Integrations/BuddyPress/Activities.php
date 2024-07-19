<?php
/**
 * BuddyPress activities helper.
 *
 * @package Anonymous Members
 * @subpackage BuddyPress
 * @author ckchaudhary
 */

namespace RecycleBin\AnonymousMembers\Integrations\BuddyPress;

use RecycleBin\AnonymousMembers\SecretIdentity;

defined( 'ABSPATH' ) ? '' : exit();

/**
 * BuddyPress activities helper.
 *
 * @package Anonymous Members
 * @subpackage BuddyPress
 * @author ckchaudhary
 */
class Activities {
	use \RecycleBin\AnonymousMembers\TraitSingleton;

	/**
	 * Undocumented variable
	 *
	 * @var boolean
	 */
	protected $dealing_with_contained_elmenets = false;

	/**
	 * Initialize the object.
	 *
	 * @return void
	 */
	protected function init() {
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
	}

	/**
	 * Check if the given user is an anonymous member of the given group.
	 *
	 * @param int $group_id Id of the group in question.
	 * @param int $user_id  Id of the user in question.
	 * @return boolean
	 */
	public function is_anonymous_member( $group_id, $user_id ) {
		return GroupMembership::get_instance()->is_anonymous_member( $group_id, $user_id );
	}

	/**
	 * Should the given activity be anonymous?
	 *
	 * @param object $activity  Activity object.
	 * @param int    $member_id Member who did the activity.
	 * @return boolean
	 */
	public function is_activity_anonymous( $activity, $member_id = false ) {
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

		$secret = SecretIdentity::get_instance()->get( $activity->user_id );

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
			$secret = SecretIdentity::get_instance()->get( $activity->user_id );
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

		$secret = SecretIdentity::get_instance()->get();

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
			$secret = SecretIdentity::get_instance()->get( $activity->user_id );
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
}

<?php
/**
 * BuddyPress activities helper.
 *
 * @package Anonymous Members
 * @subpackage BuddyPress
 * @author ckchaudhary
 */

namespace RecycleBin\AnonymousMembers\Integrations\BuddyPress2;

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
	 * Name of the meta key which is used to store id of the original(hidden) member associated with the activity.
	 * In case the activity was registered anonumosly.
	 *
	 * @var string
	 */
	protected $meta_key_org_user_id = 'rb_am_activity_org_user_id';

	/**
	 * Initialize the object.
	 *
	 * @return void
	 */
	protected function init() {
		// in the post actiivty form, change "what's new real_name" to "what's new alias".
		\add_filter( 'bp_core_get_js_strings', array( $this, 'bp_core_get_js_strings' ), 90 );

		// Before posting activity update, change user id from anonymous to stig.
		\add_filter( 'bp_before_groups_post_update_parse_args', array( $this, 'group_post_update_anonymously' ) );

		// After activity has been posted, save the anonymous user's id in meta.
		// This is not required as 'after_bp_activity_add' handles it.
		//\add_action( 'bp_groups_posted_update', array( $this, 'after_group_activity_post' ), 10, 4 );

		// Replace Stig's details with anonymous member's alias.
		\add_filter( 'bp_groups_format_activity_action_group_activity_update', array( $this, 'action_group_activity_update' ), 3, 2 );
		\add_filter( 'bp_get_activity_action_pre_meta', array( $this, 'action_group_activity_update' ), 90, 2 );

		// Allow anonymously joined members to comment on activities.
		\add_filter( 'bp_activity_can_comment', array( $this, 'filter_activity_can_comment' ), 100, 1 );
		\add_filter( 'bp_activity_can_comment_reply', array( $this, 'filter_activity_can_comment_reply' ), 100, 2 );

		/*
		Activity comment flow:
		bp_nouveau_ajax_new_activity_comment > bp_activity_new_comment > bp_activity_add
		> bp_parse_args > ( 'activity_add' )
		*/
		// Before adding any activity, replace actual member with Stig, wherever applicable.
		\add_filter( 'bp_before_activity_add_parse_args', array( $this, 'filter_activity_add_args' ) );

		// After an activity is added, store original user's id in meta, if applicable.
		\add_action( 'bp_activity_add', array( $this, 'after_bp_activity_add' ), 10, 2 );
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
	 * If the activity was posted anonymously, it is posted from Stig's account.
	 * This function returns the detail of the actual anonymous user behind this.
	 *
	 * @param int $activity_id Id of the activity item.
	 *
	 * @return bool|array {
	 *   Details of the anonymous user.
	 *   @type int    $id     User id.
	 *   @type string $name   Alias for this user.
	 *   @type string $url    Url of anonymous user profile.
	 *   @type string $avatar Url of the avatar image.
	 * }
	 */
	public function get_hidden_author( $activity_id ) {
		$hidden_mem_id = \bp_activity_get_meta( $activity_id, $this->meta_key_org_user_id );
		if ( $hidden_mem_id ) {
			return \RecycleBin\AnonymousMembers\SecretIdentity::get_instance()->get( $hidden_mem_id );
		}

		return false;
	}

	/**
	 * Is the given activity anonymous?
	 *
	 * @param object $activity  Activity object.
	 * @param int    $member_id Member who did the activity.
	 * @return boolean|array array containing details of hidden member if activity is anonymous. see get_hidden_author()
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

		if ( ! $member_id ) {
			$member_id = $activity->user_id;
		}

		// Is this activity from Stig?
		$is_from_stig = false;
		$stig = rb_anonymous_members()->get_anonymous_user();
		if ( $stig && $stig->ID === $member_id ) {
			$is_from_stig = true;
		}

		if ( ! $is_from_stig ) {
			return false;
		}

		return $this->get_hidden_author( $activity->id );
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
	 * Filter the parameters before initiating group activity post,
	 * to allow anonymous members to post.
	 *
	 * @param array $r Original parameters.
	 * @return array
	 */
	public function group_post_update_anonymously( $r ) {
		$bp = \buddypress();

		$group_id = (int) $r['group_id'];
		if ( ! $group_id && ! empty( $bp->groups->current_group->id ) ) {
			$group_id = (int) $bp->groups->current_group->id;
		}

		// Do nothing if group doesn't allow anonymous members.
		$allow = (int) groups_get_groupmeta( $group_id, 'allow_anonymous_members' );
		if ( ! $allow ) {
			return $r;
		}

		// Do nothing if user isn't anonymous member.
		if ( ! GroupMembership::get_instance()->is_anonymous_member( $group_id, \bp_loggedin_user_id() ) ) {
			return $r;
		}

		// Post as Stig.
		$anon_user = rb_anonymous_members()->get_anonymous_user();
		if ( $anon_user ) {
			// 'org_user_id' is used by $this->after_bp_activity_add() .
			$r['org_user_id'] = $r['user_id'];
			$r['user_id']     = $anon_user->ID;
		}

		return $r;
	}

	/**
	 * After activity has been posted, save the anonymous user's id in meta.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content     The content of the update.
	 * @param int    $user_id     ID of the user posting the update.
	 * @param int    $group_id    ID of the group being posted to.
	 * @param bool   $activity_id Whether or not the activity recording succeeded.
	 *
	 * @return bool
	 */
	public function after_group_activity_post( $content, $user_id, $group_id, $activity_id ) {
		// Do nothing if group doesn't allow anonymous members.
		$allow = (int) groups_get_groupmeta( $group_id, 'allow_anonymous_members' );
		if ( ! $allow || ! \is_user_logged_in() ) {
			return false;
		}

		// Do nothing if user isn't anonymous member.
		if ( ! GroupMembership::get_instance()->is_anonymous_member( $group_id, \bp_loggedin_user_id() ) ) {
			return false;
		}

		// Do nothing if it wasn't Stig.
		$anon_user = rb_anonymous_members()->get_anonymous_user();
		if ( ! $anon_user || $anon_user->ID !== $user_id ) {
			return false;
		}

		\bp_activity_add_meta( $activity_id, $this->meta_key_org_user_id, \bp_loggedin_user_id() );
	}

	/**
	 * Replace Stig's details with anonymous member's alias.
	 *
	 * @param string $action   The Group's activity update action.
	 * @param object $activity Activity data object.
	 * @return string
	 */
	public function action_group_activity_update( $action, $activity ) {
		$hidden_member = $this->is_activity_anonymous( $activity );
		if ( ! $hidden_member ) {
			return $action;
		}

		$user_link = sprintf(
			'<span class="rb-anonymous-membership"><span class="rb-am-icon gg-ghost-character"></span><span class="rb-am-text">%s</span></span>',
			esc_html( $hidden_member['name'] )
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
	 * Function used to determine if a user can comment on a group activity item.
	 *
	 * Used as a filter callback to 'bp_activity_can_comment'.
	 *
	 * @since 3.0.0
	 *
	 * @param  bool                      $retval   True if item can receive comments.
	 * @param  null|BP_Activity_Activity $activity Null by default. Pass an activity object to check against that instead.
	 * @return bool
	 */
	public function filter_activity_can_comment( $retval, $activity = null ) {
		if ( $retval || ! \is_user_logged_in() ) {
			return $retval;
		}

		// Use passed activity object, if available.
		if ( is_a( $activity, '\BP_Activity_Activity' ) ) {
			$component = $activity->component;
			$group_id  = $activity->item_id;

		// Use activity info from current activity item in the loop.
		} else {
			$component = bp_get_activity_object_name();
			$group_id  = bp_get_activity_item_id();
		}

		// If not a group activity item, bail.
		if ( 'groups' !== $component ) {
			return $retval;
		}

		// If the current user has joined the group anonymously and is not banned.
		if ( $this->is_anonymous_member( $group_id, \bp_loggedin_user_id() ) && ! groups_is_user_banned( \bp_loggedin_user_id(), $group_id ) ) {
			$retval = true;
		}

		return $retval;
	}

	/**
	 * Function used to determine if a user can reply on a group activity comment.
	 *
	 * Used as a filter callback to 'bp_activity_can_comment_reply'.
	 *
	 * @since 3.0.0
	 *
	 * @param  bool        $retval  True if activity comment can be replied to.
	 * @param  object|bool $comment Current activity comment object. If empty, parameter is boolean false.
	 * @return bool
	 */
	public function filter_activity_can_comment_reply( $retval, $comment ) {
		// Bail if no current user, if comment is empty or if retval is already true.
		if ( $retval || ! is_user_logged_in() || empty( $comment ) ) {
			return $retval;
		}

		// Grab parent activity item.
		$parent = new \BP_Activity_Activity( $comment->item_id );

		// Check to see if user can reply to parent group activity item.
		return $this->filter_activity_can_comment( $retval, $parent );
	}

	/**
	 * Before adding any activity, replace actual member with Stig, wherever applicable.
	 *
	 * @param array $r Arguments passed to bp_activity_add function.
	 * @return array
	 */
	public function filter_activity_add_args( $r ) {
		global $wpdb;
		$bp = \buddypress();

		if ( $bp->activity->id !== $r['component'] || 'activity_comment' !== $r['type'] ) {
			return $r;
		}

		$root_activity_id = isset( $r['item_id'] ) ? absint( $r['item_id'] ) : 0;
		if ( ! $root_activity_id ) {
			return $r;
		}
		// Fetch the root level activity.
		$ancestor = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$bp->activity->table_name} WHERE id = %d",
				$root_activity_id
			)
		);

		if ( empty( $ancestor ) ) {
			return $r;
		}

		// Check if the comment is on a group activity.
		if ( 'groups' === $ancestor->component ) {
			// If the current user is an anonymous member of the group.
			if ( $this->is_anonymous_member( $ancestor->item_id, $r['user_id'] ) ) {
				// Replace user id with Stig's id.
				$stig = rb_anonymous_members()->get_anonymous_user();
				if ( $stig ) {
					// 'org_user_id' is used by $this->after_bp_activity_add() .
					$r['org_user_id'] = $r['user_id'];
					$r['user_id']     = $stig->ID;
				}
			}
		}

		return $r;
	}

	/**
	 * After an activity is added, store original user's id in meta, if applicable.
	 *
	 * @param array $r           Arguments for the bp_activity_add function.
	 * @param int   $activity_id Id of the activity item that was just created.
	 * @return void
	 */
	public function after_bp_activity_add( $r, $activity_id ) {
		if ( isset( $r['org_user_id'] ) ) {
			bp_activity_add_meta( $activity_id, $this->meta_key_org_user_id, $r['org_user_id'] );
		}
	}
}

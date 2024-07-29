<?php
/**
 * BuddyPress notifications helper.
 *
 * @package Anonymous Members
 * @subpackage BuddyPress
 * @author ckchaudhary
 * @since 1.0.0
 */

namespace RecycleBin\AnonymousMembers\Integrations\BuddyPress;

defined( 'ABSPATH' ) ? '' : exit();

/**
 * BuddyPress notifications helper.
 *
 * @package Anonymous Members
 * @subpackage BuddyPress
 * @author ckchaudhary
 */
class Notifications {
	use \RecycleBin\AnonymousMembers\TraitSingleton;

	/**
	 * Initialize the object.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function init() {
		/*
		Activity type new_at_mention:
		When someone mentions another person in an acitvity post or comment:
		If the activity was anonymous:
			1. Do not notify the person who was mentioned.
			2. Do not email the person who was mentioend.
		*/

		\add_filter( 'bp_activity_at_name_do_notifications', array( $this, 'filter_bp_activity_at_name_do_notifications' ), 10, 4 );

		/**
		 * A new activity comment was made.
		 *
		 * If the comment was made anonymously:
		 *   1. Do not notify the author(s)
		 *   2. Do not send email to author(s)
		 *
		 * If the parent activity or parent comment were made anonymously.
		 *   1. Do not notify the anoymous author(s)
		 *   2. Do not send email to anonymous author(s)
		 */
		\add_action( 'bp_activity_comment_posted', array( $this, 'after_bp_activity_comment_posted' ), 5, 3 );
	}

	/**
	 * Filters BuddyPress' ability to send email notifications for @mentions.
	 *
	 * @since 1.0.0
	 *
	 * @param bool                 $value     Whether or not BuddyPress should send a notification to the mentioned users.
	 * @param array                $usernames Array of users potentially notified.
	 * @param int                  $user_id   ID of the current user being notified.
	 * @param BP_Activity_Activity $activity  Activity object.
	 *
	 * @return bool
	 */
	public function filter_bp_activity_at_name_do_notifications( $value, $usernames, $user_id, $activity ) {
		$stig = rb_anonymous_members()->get_anonymous_user();
		if ( $stig->ID === $activity->user_id && \bp_loggedin_user_id() !== $stig->ID ) {
			// Activity was done anonymously. Can't notify.
			return false;
		}

		return $value;
	}

	/**
	 * Fires near the end of an activity comment posting, before the returning of the comment ID.
	 * Sends a notification to the user @see bp_activity_new_comment_notification_helper().
	 *
	 * @since 1.2.0
	 *
	 * @param int                   $comment_id ID of the newly posted activity comment.
	 * @param array                 $r          Array of parsed comment arguments.
	 * @param \BP_Activity_Activity $activity   Activity item being commented on.
	 *
	 * @return void
	 */
	public function after_bp_activity_comment_posted( $comment_id, $r, $activity ) {
		$comment_activity = new \BP_Activity_Activity( $comment_id );

		$stig = rb_anonymous_members()->get_anonymous_user();
		if ( ! empty( $stig ) ) {
			// Unhook the orginal buddypress function.
			\remove_action( 'bp_activity_comment_posted', 'bp_activity_new_comment_notification_helper', 10, 2 );

			$this->bp_activity_new_comment_notification( $comment_activity, $activity, $r );
		}
	}

	/**
	 * If the comment was made anonymously:
	 *   1. Do not notify the author(s).
	 *   2. Do not send email to author(s).
	 *
	 * If the parent activity or parent comment were made anonymously.
	 *   1. Do not notify the anoymous author(s)
	 *   2. Do not send email to anonymous author(s)
	 *
	 * @param \BP_Activity_Activity $comment_activity  Comment activity.
	 * @param \BP_Activity_Activity $original_activity The parent activity this comment was made on.
	 * @param array                 $r                 Arguments passed to bp_activity_new_comment function.
	 * @return bool
	 */
	protected function bp_activity_new_comment_notification( $comment_activity, $original_activity, $r ) {
		$stig = rb_anonymous_members()->get_anonymous_user();

		$org_commenter_id = $comment_activity->user_id;
		$poster_name      = \bp_core_get_user_displayname( $org_commenter_id );

		if ( $org_commenter_id === $stig->ID ) {
			$org_commenter_id = (int) \bp_activity_get_meta( $comment_activity->id, Activities::get_instance()->get_meta_key_org_user_id() );
		}

		if ( $org_commenter_id !== $comment_activity->user_id ) {
			return false;
		}

		$thread_link = \bp_activity_get_permalink( $original_activity->id );

		\remove_filter( 'bp_get_activity_content_body', 'convert_smilies' );
		\remove_filter( 'bp_get_activity_content_body', 'wpautop' );
		\remove_filter( 'bp_get_activity_content_body', 'bp_activity_truncate_entry', 5 );

		/** This filter is documented in bp-activity/bp-activity-template.php */
		$content = apply_filters_ref_array( 'bp_get_activity_content_body', array( $r['content'], &$original_activity ) );

		\add_filter( 'bp_get_activity_content_body', 'convert_smilies' );
		\add_filter( 'bp_get_activity_content_body', 'wpautop' );
		\add_filter( 'bp_get_activity_content_body', 'bp_activity_truncate_entry', 5 );

		$original_activity_user_id = $original_activity->user_id;

		if ( $original_activity_user_id === $stig->ID ) {
			$original_activity_user_id = (int) \bp_activity_get_meta( $original_activity->id, Activities::get_instance()->get_meta_key_org_user_id() );
		}

		if ( $original_activity->user_id !== $org_commenter_id && $original_activity_user_id === $original_activity->user_id ) {

			// Send an email to original activity's author if he/she hasn't opted-out.
			if ( 'no' !== bp_get_user_meta( $original_activity_user_id, 'notification_activity_new_reply', true ) ) {

				$unsubscribe_args = array(
					'user_id'           => $original_activity_user_id,
					'notification_type' => 'activity-comment',
				);

				$args = array(
					'tokens' => array(
						'comment.id'                => $comment_activity->id,
						'commenter.id'              => $comment_activity->user_id,
						'usermessage'               => wp_strip_all_tags( $content ),
						'original_activity.user_id' => $original_activity_user_id,
						'poster.name'               => $poster_name,
						'thread.url'                => esc_url( $thread_link ),
						'unsubscribe'               => esc_url( bp_email_get_unsubscribe_link( $unsubscribe_args ) ),
					),
				);

				bp_send_email( 'activity-comment', $original_activity_user_id, $args );
			}

			// Send buddypress notification.
			\bp_notifications_add_notification(
				array(
					'user_id'           => $original_activity_user_id,
					'item_id'           => $comment_activity->id,
					'secondary_item_id' => $comment_activity->user_id,
					'component_name'    => buddypress()->activity->id,
					'component_action'  => 'update_reply',
					'date_notified'     => bp_core_current_time(),
					'is_new'            => 1,
				)
			);
		}

		/*
		* If this is a reply to another comment, send an email notification to the
		* author of the immediate parent comment.
		*/
		if ( empty( $r['parent_id'] ) || ( $r['activity_id'] === $r['parent_id'] ) ) {
			return;
		}

		$parent_comment = new \BP_Activity_Activity( $r['parent_id'] );
		$parent_comment_user_id = $parent_comment->user_id;
		if ( $parent_comment_user_id === $stig->ID ) {
			$parent_comment_user_id = (int) \bp_activity_get_meta( $parent_comment->id, Activities::get_instance()->get_meta_key_org_user_id() );
		}

		if ( $parent_comment_user_id !== $org_commenter_id && $original_activity_user_id !== $parent_comment_user_id && $parent_comment_user_id === $parent_comment->user_id ) {

			// Send an email if the user hasn't opted-out.
			if ( 'no' !== bp_get_user_meta( $parent_comment_user_id, 'notification_activity_new_reply', true ) ) {

				$unsubscribe_args = array(
					'user_id'           => $parent_comment_user_id,
					'notification_type' => 'activity-comment-author',
				);

				$args = array(
					'tokens' => array(
						'comment.id'             => $comment_activity->id,
						'commenter.id'           => $comment_activity->user_id,
						'usermessage'            => wp_strip_all_tags( $content ),
						'parent-comment-user.id' => $parent_comment_user_id,
						'poster.name'            => $poster_name,
						'thread.url'             => esc_url( $thread_link ),
						'unsubscribe'            => esc_url( bp_email_get_unsubscribe_link( $unsubscribe_args ) ),
					),
				);

				bp_send_email( 'activity-comment-author', $parent_comment_user_id, $args );
			}

			// Send buddypress notification.
			\bp_notifications_add_notification(
				array(
					'user_id'           => $parent_comment_user_id,
					'item_id'           => $comment_activity->id,
					'secondary_item_id' => $comment_activity->user_id,
					'component_name'    => buddypress()->activity->id,
					'component_action'  => 'comment_reply',
					'date_notified'     => bp_core_current_time(),
					'is_new'            => 1,
				)
			);
		}
	}
}

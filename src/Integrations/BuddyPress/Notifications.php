<?php
/**
 * BuddyPress notifications helper.
 *
 * @package Anonymous Members
 * @subpackage BuddyPress Groups
 * @author ckchaudhary
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
	 * @return void
	 */
	protected function init() {
		\add_filter( 'bp_activity_single_update_reply_notification', array( $this, 'filter_update_reply_notification_description' ), 90, 5 );
		\add_filter( 'bp_activity_multiple_update_reply_notification', array( $this, 'filter_update_reply_notification_description' ), 90, 5 );

		\add_filter( 'bp_activity_single_comment_reply_notification', array( $this, 'filter_comment_reply_notification_description' ), 90, 5 );
		\add_filter( 'bp_activity_multiple_comment_reply_notification', array( $this, 'filter_comment_reply_notification_description' ), 90, 5 );

		// @todo: hide member details in all sorts of emails - check calls of bp_send_email
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
		$activity_obj     = new \stdClass();
		$activity_obj->id = $activity_id;
		if ( Activities::get_instance()->is_activity_anonymous( $activity_obj ) ) {
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
		$activity_obj     = new \stdClass();
		$activity_obj->id = $activity_id;
		if ( Activities::get_instance()->is_activity_anonymous( $activity_obj ) ) {
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
}

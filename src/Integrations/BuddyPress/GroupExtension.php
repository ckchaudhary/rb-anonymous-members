<?php
/**
 * Add settings screen in buddypress groups.
 *
 * @package Anonymous Members
 * @subpackage BuddyPress
 * @author ckchaudhary
 * @since 1.0.0
 */

namespace RecycleBin\AnonymousMembers\Integrations\BuddyPress;

defined( 'ABSPATH' ) ? '' : exit();

/**
 * Add settings screen in buddypress groups.
 */
class GroupExtension extends \BP_Group_Extension {
	/**
	 * Constructor
	 */
	public function __construct() {
		$args = array(
			'slug'     => 'rb-anonymous-members',
			'name'     => __( 'Anonymous Members', 'rb-anonymous-members' ),
			'show_tab' => false,
			'access'   => 'noone',
			'screens'  => array(
				'admin' => array(
					'enabled' => true,
				),
			),
		);
		parent::init( $args );
	}

	/**
	 * Outputs a form to activate the extension on 'edit', 'create' & 'admin' screens.
	 *
	 * @param int|null $group_id ID of the displayed group.
	 * @return void
	 */
	public function settings_screen( $group_id = null ) {
		$active = (int) groups_get_groupmeta( $group_id, 'allow_anonymous_members' );
		printf(
			'<label><input type="checkbox" name="allow_anonymous_members" value="1" %1$s>%2$s</input></label>
			<input type="hidden" name="did_allow_anonymous_members" value="%3$s">',
			checked( $active, true, false ),
			esc_html__( 'Allow members to join anonymously', 'rb-anonymous-members' ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$active
		);

		$integration = rb_anonymous_members()->get_integration( 'buddypress_groups' );
		$url         = $integration->get_option( 'url_membership_info' );
		if ( $url ) {
			$link = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer"><span class="rb-am-icon gg-info"></span> %s</a>',
				esc_url( $url ),
				esc_html__( 'Know More', 'rb-anonymous-members' )
			);

			printf(
				'<p>%s ' . esc_html__( '( Opens in new tab )', 'rb-anonymous-members' ) . '.</p>',
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$link
			);
		}
	}

	/**
	 * Activate or Deactivate the group extension from 'edit', 'create' or 'admin' screens.
	 *
	 * @param int|null $group_id ID of the displayed group.
	 */
	public function settings_screen_save( $group_id = null ) {
		$was_active = 0;
		$is_active  = 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['did_allow_anonymous_members'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$was_active = intval( wp_unslash( $_REQUEST['did_allow_anonymous_members'] ) );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_REQUEST['allow_anonymous_members'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$is_active = intval( wp_unslash( $_REQUEST['allow_anonymous_members'] ) );
			}

			if ( $was_active && ! $is_active ) {
				groups_delete_groupmeta( $group_id, 'allow_anonymous_members' );
			} elseif ( ! $was_active && $is_active ) {
				groups_update_groupmeta( $group_id, 'allow_anonymous_members', $is_active );
			}
		}
	}
}

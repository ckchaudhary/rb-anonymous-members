<?php
/**
 * BuddyPress groups integration.
 *
 * This works by
 *  - Using Stig's profile instead of the actual user
 *      - Stig joins the group.
 *      - Stig posts activity updates, comments etc.
 *  - Then replacing stig with the actual user. For example:
 *      - Using member's alias instead of Stig's name and avatar in activity posts and comments.
 *
 * @package Anonymous Members
 * @subpackage BuddyPress
 * @author ckchaudhary
 */

namespace RecycleBin\AnonymousMembers\Integrations\BuddyPress2;

defined( 'ABSPATH' ) ? '' : exit();

/**
 * BuddyPress groups integration.
 *
 * @package Anonymous Members
 * @subpackage BuddyPress
 * @author ckchaudhary
 */
class Groups extends \RecycleBin\AnonymousMembers\Integrations\Integration {
	/**
	 * Get the fields for specific settings for this integration, if any.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$fields = parent::get_settings_fields();

		$link_add_page = sprintf(
			'<a href="%s">%s</a>',
			\admin_url( 'post-new.php?post_type=page' ),
			esc_html__( 'new page', 'rb-anonymous-members' )
		);
		$description   = sprintf(
			// translators: 1: link to wp-admin > add new page.
			__( 'You should create a %s that has all the information about joining groups anonymously.', 'rb-anonymous-members' ),
			$link_add_page
		);
		$description .= '<br>';
		$description .= __( 'Enter that page\'s url here.', 'rb-anonymous-members' );
		$description .= '<br>';
		$description .= __( 'If you provide this, a link to this url is displayed in few places inside groups.', 'rb-anonymous-members' );

		$fields['url_membership_info'] = array(
			'type'        => 'url',
			'label'       => __( 'Url information page', 'rb-anonymous-members' ),
			'value'       => $this->get_option( 'url_membership_info' ),
			'description' => $description,
			'attributes'  => array(
				'class' => 'regular-text',
			),
		);

		return $fields;
	}

	/**
	 * Constructor
	 *
	 * @param string $type type of the integration.
	 * @param string $name Name. Optional.
	 *
	 * @return void
	 */
	public function __construct( $type, $name = '' ) {
		parent::__construct( $type, $name );
		// Load helpers.
		\add_action( 'bp_late_include', array( $this, 'on_bp_include' ) );
		\add_action( 'bp_init', array( $this, 'register_bp_group_extension' ) );
	}

	/**
	 * Load helpers.
	 *
	 * @return void
	 */
	public function on_bp_include() {
		if ( \bp_is_active( 'groups' ) && 'yes' === $this->get_option( 'is_enabled' ) ) {
			GroupMembership::get_instance();
			Activities::get_instance();
			//Notifications::get_instance();
		}
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function register_bp_group_extension() {
		if ( \bp_is_active( 'groups' ) ) {
			\bp_register_group_extension( 'RecycleBin\AnonymousMembers\Integrations\BuddyPress2\GroupExtension' );
		}
	}

	/**
	 * Get details about this integration, to be displayed in admin settings screen.
	 *
	 * @return string
	 */
	public function get_admin_description() {
		$link = sprintf(
			'<a href="https://blogs.recycleb.in/2024/07/buddypress-buyddyboss-anonymous-members/" target="_blank" rel="nofollow noopener noreferrer">%s</a>',
			esc_html__( 'Know More', 'rb-anonymous-members' )
		);

		$desc = sprintf(
			// translators: 1: External link to read in details.
			__( 'This enables members to join groups anonymously. %s', 'rb-anonymous-members' ),
			$link
		);

		$html = '<p>' . $desc . '</p>';
		if ( ! \bp_is_active( 'groups' ) ) {
			$html .= '<p>';
			$html .= '<span class="notice notice-error inline">' . esc_html__( 'Groups are not enabled.', 'rb-anonymous-members' ) . '</span> ';
			$html .= esc_html__( 'Enabling this integration will have no effect.', 'rb-anonymous-members' );
			$html .= '</p>';
		}

		return $html;
	}
}

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
	 * All integrations.
	 *
	 * @var array
	 */
	private $all_integrations = array();

	/**
	 * Initialize the object.
	 *
	 * @return void
	 */
	public function init() {
		$this->load_plugin_textdomain();
		$this->load_integrations();

		\add_action( 'wp_enqueue_scripts', array( $this, 'load_assets' ) );
	}

	/**
	 * Loads the textdomain for the plugin.
	 *
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {
		\load_plugin_textdomain( 'rb-anonymous-members', false, RB_AM_P_DIR . 'languages' );
	}

	/**
	 * Register group extension if enabled.
	 *
	 * @return void
	 */
	public function load_integrations() {
		$buddypress_active = false;
		$buddyboss_active  = false;
		if ( function_exists( '\buddypress' ) ) {
			$buddypress_active = true;
			if ( isset( \buddypress()->buddyboss ) ) {
				$buddypress_active = false;
				$buddyboss_active  = true;
			}
		}

		if ( $buddypress_active ) {
			$this->all_integrations['buddypress_groups'] = \RecycleBin\AnonymousMembers\Integrations\BuddyPressGroups::get_instance();
		}
		if ( $buddyboss_active ) {
			$this->all_integrations['buddyboss_groups'] = \RecycleBin\AnonymousMembers\Integrations\BuddyBossGroups::get_instance();
		}
	}

	/**
	 * Load javascript, css, etc. files.
	 *
	 * @return void
	 */
	public function load_assets() {
		if ( ! empty( $this->all_integrations ) ) {
			\wp_enqueue_script( 'rb-am-main', RB_AM_P_URL . 'assets/main.js', array( 'jquery' ), RB_AM_P_VERSION, array( 'in_footer' => true ) );
			\wp_enqueue_style( 'rb-am-main', RB_AM_P_URL . 'assets/main.css', array(), RB_AM_P_VERSION );
		}
	}
}

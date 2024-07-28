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
	 * Is the plugin activated network wide?
	 *
	 * @var boolean
	 */
	public $network_activated = false;

	/**
	 * Default options for the plugin.
	 * After the user saves options the first time they are loaded from the DB.
	 *
	 * @var array
	 */
	private $default_options = array(
		'user_alias_prefix' => 'Boo-',
	);

	/**
	 * Final options for the plugin, after the default options have been overwritten by values from settings.
	 *
	 * @var array
	 */
	public $options = array();

	/**
	 * Get the value of one of the plugin options(settings).
	 *
	 * @since 1.0.0
	 * @param string $key Name of the option(setting).
	 * @return mixed
	 */
	public function option( $key ) {
		$key    = strtolower( $key );
		$option = isset( $this->options[ $key ] ) ? $this->options[ $key ] : null;

		return apply_filters( 'rb_am_option', $option, $key );
	}

	/**
	 * The object of Admin class
	 *
	 * @var \RecycleBin\AnonymousMembers\Admin
	 */
	private $admin;

	/**
	 * Get the Admin object.
	 *
	 * @return \RecycleBin\AnonymousMembers\Admin
	 */
	public function admin() {
		return $this->admin;
	}

	/**
	 * Details of 'the stig'.
	 *
	 * @var \WP_User
	 */
	private $anonymous_user = false;

	/**
	 * Get the details of 'the stig'.
	 *
	 * @return \WP_User|boolean false if the user not found/set.
	 */
	public function get_anonymous_user() {
		if ( empty( $this->anonymous_user ) ) {
			$this->anonymous_user = 'empty';
			$user_id              = $this->option( 'anonymous_user_id' );
			if ( $user_id ) {
				$user = \get_user_by( 'id', $user_id );
				if ( $user ) {
					$this->anonymous_user = $user;
				}
			}
		}

		return \is_a( $this->anonymous_user, '\WP_User' ) ? $this->anonymous_user : false;
	}

	/**
	 * All integrations.
	 *
	 * @var array
	 */
	private $integrations = array();

	/**
	 * Get all registered integrations.
	 *
	 * @return array \RecycleBin\AnonymousMembers\Integration
	 */
	public function get_all_integrations() {
		return $this->integrations;
	}

	/**
	 * Get a registered integration.
	 *
	 * @param string $type identifier of the integration.
	 * @return mixed \RecycleBin\AnonymousMembers\Integration if found. null otherwise.
	 */
	public function get_integration( $type ) {
		return isset( $this->integrations[ $type ] ) ? $this->integrations[ $type ] : null;
	}

	/**
	 * Initialize the object.
	 *
	 * @return void
	 */
	protected function init() {
		$this->setup_globals();
		$this->load_plugin_textdomain();
		$this->load_integrations();

		// init hook.
		add_action( 'init', array( $this, 'on_init' ) );
	}

	/**
	 * Setup globals.
	 *
	 * @return void
	 */
	public function setup_globals() {
		$this->network_activated = $this->is_network_activated();

		$saved_options = $this->network_activated ? get_site_option( 'rb_am_options' ) : get_option( 'rb_am_options' );
		$saved_options = maybe_unserialize( $saved_options );

		$this->options = wp_parse_args( $saved_options, $this->default_options );
	}

	/**
	 * Check if the plugin is activated network wide(in multisite)
	 *
	 * @return boolean
	 */
	private function is_network_activated() {
		$network_activated = false;
		if ( is_multisite() ) {
			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once ABSPATH . '/wp-admin/includes/plugin.php';
			}

			if ( is_plugin_active_for_network( 'rb-anonymous-members/loader.php' ) ) {
				$network_activated = true;
			}
		}

		return $network_activated;
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
		if ( function_exists( '\buddypress' ) ) {
			$buddypress_active = true;
		}

		if ( $buddypress_active ) {
			$this->integrations['buddypress_groups'] = new \RecycleBin\AnonymousMembers\Integrations\BuddyPress\Groups( 'buddypress_groups', 'BuddyPress Groups' );
		}
	}

	/**
	 * Run code on on_init hook
	 *
	 * @return void
	 */
	public function on_init() {
		if ( ( is_admin() || is_network_admin() ) && current_user_can( 'manage_options' ) ) {
			$this->admin = new Admin();
		}

		// Front End Assets.
		if ( ! is_admin() && ! is_network_admin() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		}
	}

	/**
	 * Load javascript, css, etc. files.
	 *
	 * @return void
	 */
	public function assets() {
		if ( ! empty( $this->integrations ) ) {
			\wp_enqueue_script( 'rb-am-main', RB_AM_P_URL . 'assets/main.js', array( 'jquery' ), RB_AM_P_VERSION, array( 'in_footer' => true ) );
			\wp_enqueue_style( 'rb-am-main', RB_AM_P_URL . 'assets/main.css', array(), RB_AM_P_VERSION );
		}
	}
}

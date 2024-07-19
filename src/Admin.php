<?php
/**
 * Admin class, to add settings screen, etc.
 *
 * @package FrontPage Buddy
 * @since 1.0.0
 */

namespace RecycleBin\AnonymousMembers;

defined( 'ABSPATH' ) ? '' : exit();

/**
 *  Admin class, to add settings screen, etc.
 */
class Admin {
	/**
	 * Plugin options
	 *
	 * @var array
	 */
	public $options = array();

	/**
	 * Settings screen slug.
	 *
	 * @var string
	 */
	private $plugin_slug = 'anonymous_members';

	/**
	 * Name of the options key
	 *
	 * @var string
	 */
	private $option_name = 'rb_am_options';

	/**
	 * Menu hook.
	 *
	 * @var string
	 */
	private $menu_hook = 'admin_menu';

	/**
	 * The hook suffix generated by call to add_submenu_page function.
	 *
	 * @var string
	 */
	private $generate_hook_sufix = '';

	/**
	 * Settings page.
	 *
	 * @var string
	 */
	private $settings_page = 'options-general.php';

	/**
	 * User capability to access settings screen.
	 *
	 * @var string
	 */
	private $capability = 'manage_options';

	/**
	 * Where does the settings form submit to?
	 *
	 * @var string
	 */
	private $form_action = 'options.php';

	/**
	 * Url for plugin settings screen.
	 *
	 * @var string
	 */
	private $plugin_settings_url = '';

	/**
	 * Get a settings value.
	 *
	 * @param string $key settings name.
	 * @return mixed
	 */
	public function option( $key ) {
		$value = rb_anonymous_members()->option( $key );
		return $value;
	}

	/**
	 * Empty constructor function to ensure a single instance
	 */
	public function __construct() {
		if ( ( ! is_admin() && ! is_network_admin() ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->plugin_settings_url = admin_url( 'options-general.php?page=' . $this->plugin_slug );

		// if the plugin is activated network wide in multisite, we need to override few variables.
		if ( \rb_anonymous_members()->network_activated ) {
			// Main settings page - menu hook.
			$this->menu_hook = 'network_admin_menu';

			// Main settings page - parent page.
			$this->settings_page = 'settings.php';

			// Main settings page - Capability.
			$this->capability = 'manage_network_options';

			// Settins page - form's action attribute.
			$this->form_action = 'edit.php?action=' . $this->plugin_slug;

			// Plugin settings page url.
			$this->plugin_settings_url = network_admin_url( 'settings.php?page=' . $this->plugin_slug );
		}

		// If the plugin is activated network wide in multisite, we need to process settings form submit ourselves.
		if ( \rb_anonymous_members()->network_activated ) {
			add_action( 'network_admin_edit_' . $this->plugin_slug, array( $this, 'save_network_settings_page' ) );
		}

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( $this->menu_hook, array( $this, 'admin_menu' ) );

		add_filter( 'plugin_action_links', array( $this, 'add_action_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'add_action_links' ), 10, 2 );
	}

	/**
	 * Add admin menu item.
	 *
	 * @return void
	 */
	public function admin_menu() {
		$this->generate_hook_sufix = add_submenu_page(
			$this->settings_page,
			__( 'RB Anonymous Members', 'rb-anonymous-members' ),
			__( 'Anonymous Members', 'rb-anonymous-members' ),
			$this->capability,
			$this->plugin_slug,
			array( $this, 'options_page' ),
		);
	}

	/**
	 * Load the main settings screen.
	 *
	 * @return void
	 */
	public function options_page() {
		?>
		<div class="wrap">
			<h2><?php echo esc_html( \get_admin_page_title() ); ?></h2>
			<form method="post" action="<?php echo esc_attr( $this->form_action ); ?>">

				<?php
				// phpcs:ignore
				if ( rb_anonymous_members()->network_activated && isset( $_GET['updated'] ) ) {
					echo '<div class="updated"><p>' . esc_attr__( 'Settings updated.', 'rb-anonymous-members' ) . '</p></div>';
				}
				?>

				<?php settings_fields( $this->option_name ); ?>
				<?php do_settings_sections( __FILE__ ); ?>

				<p class="submit">
					<input name="rb_am_submit" type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Setup admin stuff
	 *
	 * @return void
	 */
	public function admin_init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );

		register_setting( $this->option_name, $this->option_name, array( $this, 'plugin_options_validate' ) );

		add_settings_section( 'section_user_profiles', __( 'User Profiles', 'rb-anonymous-members' ), array( $this, 'section_user_profiles_desc' ), __FILE__ );
		add_settings_field( 'user_alias_prefix', __( 'Alias Prefix', 'rb-anonymous-members' ), array( $this, 'user_alias_prefix' ), __FILE__, 'section_user_profiles' );

		add_settings_section( 'section_integration', __( 'Integrations', 'rb-anonymous-members' ), array( $this, 'section_integration_desc' ), __FILE__ );
		add_settings_field( 'integrations', '', array( $this, 'integrations' ), __FILE__, 'section_integration' );
	}

	/**
	 * Load css and js files.
	 *
	 * @param string $hook_suffix hook suffix for current screen.
	 * @return boolean
	 */
	public function load_admin_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->generate_hook_sufix ) {
			return false;
		}

		wp_enqueue_style( 'rb-am-admin', RB_AM_P_URL . 'assets/admin.css', array(), RB_AM_P_VERSION );
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function section_integration_desc() {
		// Nothing yet.
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function section_user_profiles_desc() {
		// Nothing yet.
	}

	/**
	 * Validate plugin options.
	 *
	 * @param mixed $input array of options.
	 * @return mixed
	 */
	public function plugin_options_validate( $input ) {
		if ( empty( $input ) ) {
			return $input;
		}

		foreach ( $input as $field_name => $field_value ) {
			switch ( $field_name ) {
				case 'user_alias_prefix':
					$field_value = \sanitize_text_field( $field_value );
					break;

				case 'integrations':
					if ( ! empty( $field_value ) ) {
						foreach ( $field_value as $integration_type => $integration_fields ) {
							$integration = rb_anonymous_members()->get_integration( $integration_type );
							if ( empty( $integration ) ) {
								$field_value = false;
							} else {
								$registered_fields = $integration->get_settings_fields();
								if ( empty( $registered_fields ) ) {
									$field_value = false;
								} else {
									foreach ( $integration_fields as $i_field_name => $entered_value ) {
										if ( isset( $registered_fields[ $i_field_name ] ) ) {
											$integration_fields[ $i_field_name ] = sanitize_field( $entered_value, $registered_fields[ $i_field_name ] );
										} else {
											unset( $field_value[ $i_field_name ] );
										}
									}
								}
							}

							$field_value[ $integration_type ] = $integration_fields;
						}
					}
					break;
			}

			$input[ $field_name ] = $field_value;
		}

		return $input;
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public function user_alias_prefix() {
		$field_name  = __FUNCTION__;
		$field_value = $this->option( $field_name );
		$input_name  = $this->option_name . '[' . $field_name . ']';

		printf(
			'<input type="text" name="%s" value="%s">',
			esc_attr( $input_name ),
			esc_attr( $field_value )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'To keep a member\'s identity a secret, an alias is used instead of the member\'s actual name/username, wherever required. You can set a prefix for this alias, to make it more obvious to everyone that the name displayed in not the actual name.', 'rb-anonymous-members' )
		);
	}

	/**
	 * Settings for integrations.
	 *
	 * @return boolean
	 */
	public function integrations() {
		$main_setting_name = __FUNCTION__;
		$all_integrations  = rb_anonymous_members()->get_all_integrations();

		if ( empty( $all_integrations ) ) {
			?>
			<div class='notice notice-error inline'>
				<?php
				printf(
					/* translators: %s: list of plugins rb-anonymous-members works with. */
					'<p>' . esc_html__( 'Anonymous buddy can only work when either of the following plugins are active: %s', 'rb-anonymous-members' ) . '.</p>'
					. '<p>' . esc_html__( 'Not much it can do for now!', 'rb-anonymous-members' ) . '</p>',
					'BuddyPress, BuddyBoss'
				);
				?>
			</div>
			<?php
			return false;
		}

		foreach ( $all_integrations as $integration_type => $integration_obj ) {
			echo '<table class="table widefat striped form-table integration">';
			echo '<thead><tr class="integration-title"><td colspan="100%">';
			printf( '<h3>%s</h3>', esc_html( $integration_obj->get_integration_name() ) );
			echo '</td></tr></thead>';

			echo '<tbody>';

			echo '<tr class="integration-desc"><td colspan="100%" >';
			echo wp_kses( $integration_obj->get_admin_description(), basic_html_allowed_tags() );
			echo '</td></tr>';

			$settings_fields = $integration_obj->get_settings_fields();
			if ( ! empty( $settings_fields ) ) {
				$settings_fields_mod = array();
				foreach ( $settings_fields as $field_name => $v ) {
					$field_name                         = $this->option_name . '[' . $main_setting_name . '][' . $integration_type . '][' . $field_name . ']';
					$settings_fields_mod[ $field_name ] = $v;
				}

				// phpcs:ignore WordPress.Security.EscapeOutput
				echo generate_form_fields(
					$settings_fields_mod,
					array(
						'before_field' => '<tr class="{{FIELD_CLASS}}">',
						'after_field'  => '</tr><!-- .field -->',
						'before_label' => '<th>',
						'after_label'  => '</th>',
						'before_input' => '<td>',
						'after_input'  => '</td>',
					)
				);
			}

			echo '</tbody></table>';
		}
	}

	/**
	 * Save settings in
	 *
	 * @return void
	 */
	public function save_network_settings_page() {
		if ( ! check_admin_referer( $this->option_name . '-options' ) ) {
			return;
		}

		if ( ! current_user_can( $this->capability ) ) {
			die( 'Access denied!' );
		}

		if ( isset( $_POST['rb_am_submit'] ) && isset( $_POST[ $this->option_name ] ) ) {
			$submitted = stripslashes_deep( $_POST[ $this->option_name ] );//phpcs:ignore
			$submitted = $this->plugin_options_validate( $submitted );

			update_site_option( $this->option_name, $submitted );
		}

		// Where are we redirecting to?
		$base_url     = trailingslashit( network_admin_url() ) . 'settings.php';
		$redirect_url = add_query_arg(
			array(
				'page'    => $this->plugin_slug,
				'updated' => 'true',
			),
			$base_url
		);

		// Redirect.
		wp_safe_redirect( $redirect_url );
		die();
	}

	/**
	 * Add plugins settings link etc on plugins listing page.
	 *
	 * @param array  $links existing links.
	 * @param string $file plugin base file name.
	 * @return array
	 */
	public function add_action_links( $links, $file ) {
		// Return normal links if not this plugin.
		if ( plugin_basename( basename( constant( 'RB_AM_P_DIR' ) ) . '/loader.php' ) !== $file ) {
			return $links;
		}

		$mylinks = array(
			'<a href="' . esc_url( $this->plugin_settings_url ) . '">' . esc_html__( 'Settings', 'rb-anonymous-members' ) . '</a>',
		);

		return array_merge( $links, $mylinks );
	}
}

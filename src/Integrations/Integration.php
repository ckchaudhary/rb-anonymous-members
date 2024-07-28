<?php
/**
 * Base integration class.
 *
 * @package Anonymous Members
 * @author ckchaudhary
 */

namespace RecycleBin\AnonymousMembers\Integrations;

/**
 * Base integration class.
 *
 * @package Anonymous Members
 * @author ckchaudhary
 */
abstract class Integration {
	/**
	 * Integration type. E.g: 'buddypress_groups'
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Name of the integration. E.g: 'BuddyPress Groups'
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Constructor
	 *
	 * @param string $type type of the integration.
	 * @param string $name Name. Optional.
	 *
	 * @return void
	 */
	public function __construct( $type, $name = '' ) {
		$this->type = $type;
		$this->name = $name;
		if ( empty( $this->name ) ) {
			$this->name = ucfirst( $type );
		}
	}

	/**
	 * Get the type of integration.
	 *
	 * @return string
	 */
	public function get_integration_type() {
		return $this->type;
	}

	/**
	 * Get the name of integration.
	 *
	 * @return string
	 */
	public function get_integration_name() {
		return $this->name;
	}

	/**
	 * Get details about this integration, to be displayed in admin settings screen.
	 *
	 * @return string
	 */
	abstract public function get_admin_description();

	/**
	 * Get the fields for specific settings for this integration, if any.
	 *
	 * @return array
	 */
	public function get_settings_fields() {
		$attrs_enabled = array();
		if ( 'yes' === $this->get_option( 'is_enabled' ) ) {
			$attrs_enabled['checked'] = 'checked';
		}

		return array(
			'is_enabled' => array(
				'type'       => 'switch',
				'label'      => __( 'Enabled?', 'rb-anonymous-members' ),
				'label_off'  => __( 'No', 'rb-anonymous-members' ),
				'label_on'   => __( 'Yes', 'rb-anonymous-members' ),
				'attributes' => $attrs_enabled,
			),
		);
	}

	/**
	 * Get an option's/setting's value.
	 *
	 * @param string $option_name name of the option.
	 * @return mixed
	 */
	public function get_option( $option_name ) {
		$all_integrations = rb_anonymous_members()->option( 'integrations' );
		$all_options      = ! empty( $all_integrations ) && isset( $all_integrations[ $this->type ] ) && ! empty( $all_integrations[ $this->type ] ) ? $all_integrations[ $this->type ] : array();
		$opt_value        = isset( $all_options[ $option_name ] ) ? $all_options[ $option_name ] : null;

		return apply_filters( 'rb_anonymous_members_get_integration_option', $opt_value, $option_name, $this );
	}

	/**
	 * Get an option's/setting's default value.
	 * This function is to be overloaded by integrations.
	 *
	 * @param mixed                                    $option_value value of the option.
	 * @param string                                   $option_name  name of the option.
	 * @param \RecycleBin\AnonymousMembers\Integration $integration  integration object.
	 *
	 * @return mixed null if no default value is to be provided.
	 */
	public function filter_option_value( $option_value, $option_name, $integration ) {
		if ( $integration->type !== $this->type ) {
			return $option_value;
		}

		// @todo: Furnish default value if required.

		return $option_value;
	}
}

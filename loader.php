<?php
/**
 * Plugin Name: RB Anonymous Members
 * Plugin URI: https://blogs.recycleb.in/2024/07/buddypress-buyddyboss-anonymous-members/
 * Description: Allow your BuddyBoss & BuddyPress website's members to join groups anonymously.
 * Version: 1.0.0
 * Author: ckchaudhary
 * Author URI: https://www.recycleb.in/u/chandan/
 * Text Domain: rb-anonymous-members
 * Domain Path: /languages
 * Licence: GPLv2
 *
 * @package Anonymous Members
 * @author ckchaudhary
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) ? '' : exit();

require __DIR__ . '/vendor/autoload.php';

// Directory.
if ( ! defined( 'RB_AM_P_DIR' ) ) {
	define( 'RB_AM_P_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
}

// Url.
if ( ! defined( 'RB_AM_P_URL' ) ) {
	$plugin_url = trailingslashit( plugin_dir_url( __FILE__ ) );

	// If we're using https, update the protocol.
	if ( is_ssl() ) {
		$plugin_url = str_replace( 'http://', 'https://', $plugin_url );
	}

	define( 'RB_AM_P_URL', $plugin_url );
}

if ( ! defined( 'RB_AM_P_VERSION' ) ) {
	define( 'RB_AM_P_VERSION', '1.0.0' );
}

/**
 * Returns the main plugin object.
 *
 * @since 1.0.0
 *
 * @return \RecycleBin\AnonymousMembers\Plugin
 */
function rb_anonymous_members() {
	return \RecycleBin\AnonymousMembers\Plugin::get_instance();
}

// Instantiate the main plugin object.
\add_action( 'plugins_loaded', 'rb_anonymous_members' );

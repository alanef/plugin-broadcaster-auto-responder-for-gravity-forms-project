<?php
/**
 * Plugin Name:       Broadcaster Auto Responder for Gravity Forms
 * Plugin URI:        https://github.com/alanef/plugin-gravity-forms
 * Description:       Sends Gravity Forms submissions to Broadcaster as incoming WhatsApp contact messages and triggers an optional template auto-response.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Fullworks
 * Author URI:        https://fullworks.net/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       broadcaster-auto-responder-for-gravity-forms
 * Domain Path:       /languages
 *
 * @package BroadcasterGF
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'BROADCASTERGF_VERSION', '1.0.0' );
define( 'BROADCASTERGF_PATH', plugin_dir_path( __FILE__ ) );
define( 'BROADCASTERGF_URL', plugin_dir_url( __FILE__ ) );
define( 'BROADCASTERGF_BASENAME', plugin_basename( __FILE__ ) );
define( 'BROADCASTERGF_OPTION', 'broadcastergf_settings' );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Bootstrap the plugin.
 */
function broadcastergf_bootstrap() {
	if ( ! class_exists( '\\BroadcasterGF\\Plugin' ) ) {
		return;
	}
	\BroadcasterGF\Plugin::instance()->register();
}
add_action( 'plugins_loaded', 'broadcastergf_bootstrap' );

/**
 * Activation hook: seed an empty settings option so the row exists.
 */
function broadcastergf_activate() {
	if ( false === get_option( BROADCASTERGF_OPTION ) ) {
		add_option(
			BROADCASTERGF_OPTION,
			array(
				'api_url' => '',
				'api_key' => '',
			)
		);
	}
}
register_activation_hook( __FILE__, 'broadcastergf_activate' );

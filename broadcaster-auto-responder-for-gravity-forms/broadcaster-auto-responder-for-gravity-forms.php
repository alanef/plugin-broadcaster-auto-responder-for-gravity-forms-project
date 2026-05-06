<?php
/**
 * Plugin Name:       Broadcaster Auto Responder for Gravity Forms
 * Plugin URI:        https://github.com/alanef/plugin-broadcaster-auto-responder-for-gravity-forms-project
 * Description:       Sends Gravity Forms submissions to Broadcaster as incoming WhatsApp contact messages and triggers an optional template auto-response.
 * Version:           1.0.1
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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BROADCASTERGF_VERSION', '1.0.1' );
define( 'BROADCASTERGF_PATH', plugin_dir_path( __FILE__ ) );
define( 'BROADCASTERGF_URL', plugin_dir_url( __FILE__ ) );
define( 'BROADCASTERGF_BASENAME', plugin_basename( __FILE__ ) );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Bootstrap the namespaced admin/API classes (settings page, notices, AJAX).
 */
function broadcastergf_bootstrap() {
	if ( ! class_exists( '\\BroadcasterGF\\Plugin' ) ) {
		return;
	}
	\BroadcasterGF\Plugin::instance()->register();
}
add_action( 'plugins_loaded', 'broadcastergf_bootstrap' );

/*
 * Gravity Forms Feed Add-On registration.
 *
 * MUST run at file scope, BEFORE plugins_loaded executes — Gravity Forms fires
 * gform_loaded during its own plugins_loaded handler, so any add_action() for
 * gform_loaded that runs from inside another plugins_loaded callback may be
 * registered after gform_loaded has already fired and never execute. The
 * EmailOctopus add-on uses this same file-scope pattern for the same reason.
 */
require_once __DIR__ . '/includes/gf/class-broadcaster-gf-bootstrap.php';
add_action( 'gform_loaded', array( 'Broadcaster_GF_Bootstrap', 'load_addon' ), 5 );

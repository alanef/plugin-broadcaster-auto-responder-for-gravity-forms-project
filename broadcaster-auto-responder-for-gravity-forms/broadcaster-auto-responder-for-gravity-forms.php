<?php
/**
 * Plugin Name:       Broadcaster Auto Responder for Gravity Forms
 * Plugin URI:        https://getbroadcaster.com/docs/gravity-forms-addon
 * Description:       Sends Gravity Forms submissions to Broadcaster as incoming WhatsApp contact messages and triggers an optional template auto-response.
 * Version:           1.1.5
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
 *
 * Ownership / affiliation note (for the WordPress.org Plugin Review Team):
 *
 * This plugin, the "Broadcaster" service it connects to (https://getbroadcaster.com),
 * the "Fullworks" brand (https://fullworks.net) and Fullworks Plugins
 * (https://fullworksplugins.com) are all owned and operated by Fullworks
 * Digital Ltd, a company registered in England & Wales. "Broadcaster" is our
 * own product — not a third party's — so there is no external affiliation to
 * verify: this connector and the service it talks to are published by the same
 * company, under the WordPress.org account "fullworks".
 *
 * "Gravity Forms" is a trademark of Rocketgenius, Inc. and "WhatsApp" is a
 * trademark of Meta Platforms, Inc. This plugin is an independent, unofficial
 * add-on and uses those names only to describe what it is compatible with.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BROADCASTERGF_VERSION', '1.1.5' );
define( 'BROADCASTERGF_PATH', plugin_dir_path( __FILE__ ) );
define( 'BROADCASTERGF_URL', plugin_dir_url( __FILE__ ) );
define( 'BROADCASTERGF_BASENAME', plugin_basename( __FILE__ ) );

/*
 * BROADCASTERGF_ENABLE_USERNAMES — feature flag for the WhatsApp Recipient
 * field's username support.
 *
 * Default: false. While this flag is off, the WhatsApp Recipient field's
 * helper text omits the @username option and `@`-prefixed input is rejected
 * as invalid by the Submitter Validator. The underlying username code path
 * (Recipient_Discriminator, Username_Validator, Field_Submission_Dispatcher
 * username slot) is fully built; only the submitter-facing surface is gated.
 *
 * Override per-site by adding the following to wp-config.php BEFORE this
 * plugin loads:
 *
 *     define( 'BROADCASTERGF_ENABLE_USERNAMES', true );
 *
 * The default flips to true in a follow-up plugin release once Meta enables
 * WhatsApp usernames generally. See specification/BRO-902/design.md (D12) in
 * the broadcaster-web repository for the architectural rationale.
 */
if ( ! defined( 'BROADCASTERGF_ENABLE_USERNAMES' ) ) {
	define( 'BROADCASTERGF_ENABLE_USERNAMES', false );
}

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

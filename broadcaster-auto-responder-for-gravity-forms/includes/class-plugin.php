<?php
/**
 * Main plugin bootstrap class.
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF;

use BroadcasterGF\Admin\Notices;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton.
 *
 * Settings, AJAX, and the standalone WP options page have moved to the
 * Gravity Forms plugin-settings tab (see Broadcaster_GF_FeedAddOn::
 * plugin_settings_fields). This class now only registers admin notices.
 */
class Plugin {

	const SETTINGS_OPTION_KEY = 'gravityformsaddon_broadcaster-auto-responder-for-gravity-forms_settings';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire WordPress hooks.
	 */
	public function register(): void {
		( new Notices() )->register();
	}
}

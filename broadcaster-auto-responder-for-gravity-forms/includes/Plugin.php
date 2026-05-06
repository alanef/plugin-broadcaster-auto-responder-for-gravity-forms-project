<?php
/**
 * Main plugin bootstrap class.
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF;

use BroadcasterGF\Admin\Ajax;
use BroadcasterGF\Admin\Notices;
use BroadcasterGF\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton: wires up admin pages, notices, and AJAX handlers.
 */
class Plugin {

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
		( new Settings() )->register();
		( new Notices() )->register();
		( new Ajax() )->register();
	}

	/**
	 * Read the saved settings array.
	 *
	 * @return array{api_url:string,api_key:string}
	 */
	public static function get_settings(): array {
		$defaults = array(
			'api_url' => '',
			'api_key' => '',
		);
		$saved    = get_option( BROADCASTERGF_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $defaults, $saved );
	}

	/**
	 * True when both API URL and API key have been saved.
	 */
	public static function is_configured(): bool {
		$s = self::get_settings();
		return '' !== $s['api_url'] && '' !== $s['api_key'];
	}
}

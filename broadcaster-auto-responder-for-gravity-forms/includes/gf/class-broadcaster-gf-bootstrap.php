<?php
/**
 * Bootstrap loader for the Broadcaster Gravity Forms Feed Add-On.
 *
 * @package BroadcasterGF
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads and registers the Broadcaster GF Feed Add-On at gform_loaded.
 *
 * Mirrors the EmailOctopus add-on pattern. Lives in its own file so the
 * main plugin file can stay free of class declarations
 * (Universal.Files.SeparateFunctionsFromOO).
 */
class Broadcaster_GF_Bootstrap {

	/**
	 * Includes the add-on class file and registers it with GFAddOn.
	 */
	public static function load_addon() {
		require_once __DIR__ . '/class-broadcaster-gf-feedaddon.php';
		\GFAddOn::register( 'Broadcaster_GF_FeedAddOn' );
	}
}

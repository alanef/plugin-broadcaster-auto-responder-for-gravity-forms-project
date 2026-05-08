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
	 *
	 * Also registers the BRO-902 WhatsApp Recipient field type and its
	 * editor / entry-detail hooks. The field is registered at the same
	 * lifecycle point as the add-on so both are available together.
	 */
	public static function load_addon() {
		require_once __DIR__ . '/class-broadcaster-gf-feedaddon.php';
		\GFAddOn::register( 'Broadcaster_GF_FeedAddOn' );

		require_once __DIR__ . '/class-broadcaster-gf-whatsapp-recipient-field.php';
		\GF_Fields::register( new \Broadcaster_GF_WhatsApp_Recipient_Field() );

		add_action(
			'gform_field_advanced_settings',
			array( '\Broadcaster_GF_WhatsApp_Recipient_Field', 'render_default_country_setting' ),
			10,
			2
		);
		add_action(
			'gform_editor_js',
			array( '\Broadcaster_GF_WhatsApp_Recipient_Field', 'editor_inline_script' )
		);
		add_filter(
			'gform_entry_field_value',
			array( '\Broadcaster_GF_WhatsApp_Recipient_Field', 'render_rejection_marker' ),
			10,
			4
		);
		add_filter(
			'gform_pre_render',
			array( '\Broadcaster_GF_WhatsApp_Recipient_Field', 'maybe_enqueue_public_assets' )
		);
	}
}

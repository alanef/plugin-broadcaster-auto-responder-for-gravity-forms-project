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
 * plugin_settings_fields). This class registers admin notices and the
 * suggested privacy-policy disclosure.
 */
class Plugin {

	const SETTINGS_OPTION_KEY = 'gravityformsaddon_broadcaster-auto-responder-for-gravity-forms_settings';

	/**
	 * Broadcaster's public privacy policy and terms-of-use URLs.
	 *
	 * Surfaced in the WordPress privacy-policy guide so a site owner can
	 * point visitors at where their submitted data is processed.
	 */
	const BROADCASTER_PRIVACY_URL = 'https://getbroadcaster.com/privacy';
	const BROADCASTER_TERMS_URL   = 'https://getbroadcaster.com/terms';

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
		add_action( 'admin_init', array( $this, 'register_privacy_policy_content' ) );
	}

	/**
	 * Register suggested privacy-policy text (WP Privacy Guide, §7 disclosure).
	 *
	 * The plugin transmits a form submitter's personal data — phone number
	 * and/or WhatsApp username, name, and mapped message/field values — to a
	 * third-party service (Broadcaster, getbroadcaster.com) on every connected
	 * submission. WordPress core surfaces this text under Settings → Privacy
	 * so the site owner can disclose that flow to their visitors.
	 *
	 * The content is fully plugin-controlled: translatable copy is escaped and
	 * the only markup is the two trusted anchors built from esc_url()'d
	 * class constants, so it is passed to core as-is (wp_kses_post would strip
	 * the `privacy-policy-tutorial` class core uses to mark non-copied notes).
	 */
	public function register_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content =
			'<p class="privacy-policy-tutorial">'
			. esc_html__( 'This information is added by the Broadcaster Auto Responder for Gravity Forms plugin and describes the personal data it sends to a third-party service.', 'broadcaster-auto-responder-for-gravity-forms' )
			. '</p>'
			. '<p>'
			. esc_html__( 'When this site forwards a Gravity Forms submission to Broadcaster — a WhatsApp business-messaging platform operated by Fullworks Digital Ltd — the following personal data from that submission is transmitted to Broadcaster\'s servers at getbroadcaster.com:', 'broadcaster-auto-responder-for-gravity-forms' )
			. '</p>'
			. '<ul>'
			. '<li>' . esc_html__( 'The contact\'s phone number and/or WhatsApp username, as mapped in the form\'s Broadcaster feed.', 'broadcaster-auto-responder-for-gravity-forms' ) . '</li>'
			. '<li>' . esc_html__( 'The submitter\'s name, when a name field is mapped.', 'broadcaster-auto-responder-for-gravity-forms' ) . '</li>'
			. '<li>' . esc_html__( 'The message text and any form-field values included in the mapped message body or template placeholders.', 'broadcaster-auto-responder-for-gravity-forms' ) . '</li>'
			. '<li>' . esc_html__( 'A form identifier and source label used to attribute the message inside Broadcaster.', 'broadcaster-auto-responder-for-gravity-forms' ) . '</li>'
			. '</ul>'
			. '<p>'
			. esc_html__( 'This data is sent only for forms a site administrator has explicitly connected to Broadcaster, and only when a Broadcaster API key is configured. It is used to create an incoming contact message and, optionally, to trigger an approved WhatsApp template auto-response.', 'broadcaster-auto-responder-for-gravity-forms' )
			. '</p>'
			. '<p>'
			. sprintf(
				/* translators: 1: opening <a> tag to Broadcaster privacy policy, 2: closing </a>, 3: opening <a> tag to Broadcaster terms of use, 4: closing </a> */
				esc_html__( 'Broadcaster processes the data it receives under its own %1$sPrivacy Policy%2$s and %3$sTerms of Use%4$s.', 'broadcaster-auto-responder-for-gravity-forms' ),
				'<a href="' . esc_url( self::BROADCASTER_PRIVACY_URL ) . '">',
				'</a>',
				'<a href="' . esc_url( self::BROADCASTER_TERMS_URL ) . '">',
				'</a>'
			)
			. '</p>';

		wp_add_privacy_policy_content(
			'Broadcaster Auto Responder for Gravity Forms',
			$content
		);
	}
}

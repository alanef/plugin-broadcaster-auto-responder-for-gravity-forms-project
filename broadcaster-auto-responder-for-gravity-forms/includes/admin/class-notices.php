<?php
/**
 * Admin dependency / configuration notices.
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\Admin;

use BroadcasterGF\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Surfaces missing-Gravity-Forms and missing-config warnings to admins.
 *
 * Notices appear on every admin screen (so an admin who never visits the
 * settings page still sees them) but only for users who can act on them.
 */
class Notices {

	const GF_SETTINGS_PAGE = 'admin.php?page=gf_settings&subview=broadcaster-auto-responder-for-gravity-forms';

	/**
	 * Wire WordPress hooks.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
	}

	/**
	 * Render a notice if a relevant problem is detected.
	 */
	public function maybe_render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->gravity_forms_active() ) {
			$this->render_gf_missing();
			return;
		}

		if ( ! $this->api_configured() ) {
			$this->render_not_configured();
		}
	}

	/**
	 * Detect Gravity Forms presence + activation.
	 *
	 * Checked by class because Gravity Forms is a paid plugin and a typical
	 * detection target — class_exists is enough, no need to scan the plugin
	 * list.
	 */
	private function gravity_forms_active(): bool {
		return class_exists( 'GFForms' );
	}

	/**
	 * True when an API key is saved in the GF add-on settings.
	 *
	 * The API URL is locked to the live SaaS in production and defaults
	 * to it on dev/staging/local, so its presence is no longer part of
	 * the "configured" check — only the key matters.
	 */
	private function api_configured(): bool {
		$opt = get_option( Plugin::SETTINGS_OPTION_KEY, array() );
		if ( ! is_array( $opt ) ) {
			return false;
		}
		$api_key = isset( $opt['api_key'] ) ? trim( (string) $opt['api_key'] ) : '';
		return '' !== $api_key;
	}

	/**
	 * Notice: Gravity Forms missing or inactive.
	 */
	private function render_gf_missing(): void {
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Broadcaster Auto Responder for Gravity Forms:', 'broadcaster-auto-responder-for-gravity-forms' ),
			esc_html__( 'Gravity Forms is not active. The plugin will sit idle until Gravity Forms is installed and activated.', 'broadcaster-auto-responder-for-gravity-forms' )
		);
	}

	/**
	 * Notice: API not configured.
	 */
	private function render_not_configured(): void {
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Broadcaster Auto Responder for Gravity Forms:', 'broadcaster-auto-responder-for-gravity-forms' ),
			esc_html__( 'Broadcaster API key is not configured.', 'broadcaster-auto-responder-for-gravity-forms' ),
			esc_url( admin_url( self::GF_SETTINGS_PAGE ) ),
			esc_html__( 'Configure now', 'broadcaster-auto-responder-for-gravity-forms' )
		);
	}
}

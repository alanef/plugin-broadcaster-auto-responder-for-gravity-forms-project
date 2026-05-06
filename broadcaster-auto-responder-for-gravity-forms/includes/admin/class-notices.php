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

		if ( ! Plugin::is_configured() ) {
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
		$settings_url = admin_url( 'options-general.php?page=' . Settings::PAGE_SLUG );
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Broadcaster Auto Responder for Gravity Forms:', 'broadcaster-auto-responder-for-gravity-forms' ),
			esc_html__( 'Broadcaster API URL or API key is not configured.', 'broadcaster-auto-responder-for-gravity-forms' ),
			esc_url( $settings_url ),
			esc_html__( 'Configure now', 'broadcaster-auto-responder-for-gravity-forms' )
		);
	}
}

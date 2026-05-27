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
 * Scoped (WP.org guideline 11) to the screens where the problem is
 * actionable — the Plugins list, the Dashboard, and any Gravity Forms admin
 * page — rather than every admin screen, and shown only to users who can act
 * on them. Both notices also self-dismiss once the underlying condition is
 * resolved (Gravity Forms activated / API key saved).
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

		if ( ! $this->is_relevant_screen() ) {
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
	 * Limit the notice to screens where the admin can act on it: the Plugins
	 * list and Dashboard (where you land right after activating), and any
	 * Gravity Forms admin page (whose screen IDs all contain `gf_`, e.g.
	 * `toplevel_page_gf_edit`, `forms_page_gf_settings`). Avoids the
	 * guideline-11 "site-wide nag on every admin page" pattern.
	 */
	private function is_relevant_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen ) {
			return false;
		}
		$id = (string) $screen->id;
		if ( in_array( $id, array( 'plugins', 'plugins-network', 'dashboard', 'dashboard-network' ), true ) ) {
			return true;
		}
		return false !== strpos( $id, 'gf_' );
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

<?php
/**
 * Admin settings page.
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\Admin;

use BroadcasterGF\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page under Settings → Broadcaster GF.
 */
class Settings {

	const PAGE_SLUG  = 'broadcastergf';
	const GROUP      = 'broadcastergf_settings_group';
	const NONCE_TEST = 'broadcastergf_test_connection';

	/**
	 * Wire WordPress hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the menu entry.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'Broadcaster Auto Responder for Gravity Forms', 'broadcaster-auto-responder-for-gravity-forms' ),
			__( 'Broadcaster GF', 'broadcaster-auto-responder-for-gravity-forms' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the option, sections, and fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			BROADCASTERGF_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(
					'api_url' => '',
					'api_key' => '',
				),
			)
		);

		add_settings_section(
			'broadcastergf_connection',
			__( 'Broadcaster connection', 'broadcaster-auto-responder-for-gravity-forms' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'api_url',
			__( 'Broadcaster site URL', 'broadcaster-auto-responder-for-gravity-forms' ),
			array( $this, 'render_api_url_field' ),
			self::PAGE_SLUG,
			'broadcastergf_connection'
		);

		add_settings_field(
			'api_key',
			__( 'Broadcaster API key', 'broadcaster-auto-responder-for-gravity-forms' ),
			array( $this, 'render_api_key_field' ),
			self::PAGE_SLUG,
			'broadcastergf_connection'
		);
	}

	/**
	 * Sanitize and merge submitted settings.
	 *
	 * Empty/masked api_key submissions preserve the previously stored value
	 * so the masked display does not silently wipe the key.
	 *
	 * @param array $input Raw submitted values.
	 * @return array
	 */
	public function sanitize( $input ): array {
		$current = Plugin::get_settings();
		$out     = $current;

		if ( isset( $input['api_url'] ) ) {
			$url            = trim( (string) $input['api_url'] );
			$url            = untrailingslashit( esc_url_raw( $url ) );
			$out['api_url'] = $url;
		}

		if ( isset( $input['api_key'] ) ) {
			$key = trim( (string) $input['api_key'] );
			// Empty submissions or the bullet-mask placeholder mean keep the saved key.
			if ( '' !== $key && ! preg_match( '/^•+$/u', $key ) ) {
				$out['api_key'] = sanitize_text_field( $key );
			}
		}

		return $out;
	}

	/**
	 * Enqueue the small admin JS used by the connection-test button.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'broadcastergf-admin',
			BROADCASTERGF_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BROADCASTERGF_VERSION,
			true
		);

		wp_localize_script(
			'broadcastergf-admin',
			'broadcastergfAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_TEST ),
				'i18n'    => array(
					'testing'   => __( 'Testing…', 'broadcaster-auto-responder-for-gravity-forms' ),
					'reqFailed' => __( 'Request failed.', 'broadcaster-auto-responder-for-gravity-forms' ),
				),
			)
		);
	}

	/**
	 * Section intro copy.
	 */
	public function render_section_intro(): void {
		echo '<p>' . esc_html__(
			'Enter the URL of your Broadcaster site and an API key generated from Broadcaster → Settings → API Keys. The key is stored encrypted only as well as WordPress stores any option; treat it like a password.',
			'broadcaster-auto-responder-for-gravity-forms'
		) . '</p>';
	}

	/**
	 * Render the API URL input.
	 */
	public function render_api_url_field(): void {
		$settings = Plugin::get_settings();
		printf(
			'<input type="url" id="broadcastergf-api-url" name="%s[api_url]" value="%s" class="regular-text code" placeholder="https://broadcaster.example.com" />',
			esc_attr( BROADCASTERGF_OPTION ),
			esc_attr( $settings['api_url'] )
		);
		echo '<p class="description">' . esc_html__(
			'Site root only — do not include /api/v1.',
			'broadcaster-auto-responder-for-gravity-forms'
		) . '</p>';
	}

	/**
	 * Render the API key input (masked when present).
	 */
	public function render_api_key_field(): void {
		$settings = Plugin::get_settings();
		$has_key  = '' !== $settings['api_key'];
		$display  = $has_key ? str_repeat( '•', 24 ) : '';
		printf(
			'<input type="password" id="broadcastergf-api-key" name="%s[api_key]" value="%s" class="regular-text code" autocomplete="new-password" %s />',
			esc_attr( BROADCASTERGF_OPTION ),
			esc_attr( $display ),
			$has_key ? 'placeholder="' . esc_attr__( 'Saved — leave masked to keep, replace to update.', 'broadcaster-auto-responder-for-gravity-forms' ) . '"' : ''
		);
		echo '<p class="description">' . esc_html__(
			'Generated in Broadcaster under Settings → API Keys. Sent as Authorization: Bearer …',
			'broadcaster-auto-responder-for-gravity-forms'
		) . '</p>';
	}

	/**
	 * Render the settings page wrapper.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Broadcaster Auto Responder for Gravity Forms', 'broadcaster-auto-responder-for-gravity-forms' ); ?></h1>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Test connection', 'broadcaster-auto-responder-for-gravity-forms' ); ?></h2>
			<p>
				<?php esc_html_e( 'Verifies the saved API URL and API key against Broadcaster. The key must be saved first.', 'broadcaster-auto-responder-for-gravity-forms' ); ?>
			</p>
			<p>
				<button type="button" class="button button-secondary" id="broadcastergf-test-connection">
					<?php esc_html_e( 'Test connection', 'broadcaster-auto-responder-for-gravity-forms' ); ?>
				</button>
				<span id="broadcastergf-test-result" style="margin-left:10px;"></span>
			</p>
		</div>
		<?php
	}
}

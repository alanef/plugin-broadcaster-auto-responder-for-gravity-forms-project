<?php
/**
 * Admin AJAX handlers.
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\Admin;

use BroadcasterGF\Api\Client;
use BroadcasterGF\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the "Test connection" AJAX call from the settings page.
 */
class Ajax {

	const ACTION = 'broadcastergf_test_connection';

	/**
	 * Wire WordPress hooks.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'test_connection' ) );
	}

	/**
	 * AJAX: test the saved Broadcaster connection.
	 */
	public function test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions.', 'broadcaster-auto-responder-for-gravity-forms' ) ),
				403
			);
		}

		check_ajax_referer( Settings::NONCE_TEST, 'nonce' );

		$settings = Plugin::get_settings();
		if ( '' === $settings['api_url'] || '' === $settings['api_key'] ) {
			wp_send_json_error(
				array( 'message' => __( 'Save the Broadcaster URL and API key before testing.', 'broadcaster-auto-responder-for-gravity-forms' ) ),
				400
			);
		}

		$client = new Client( $settings['api_url'], $settings['api_key'] );
		$result = $client->test_connection();

		if ( $result['ok'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		}

		wp_send_json_error( array( 'message' => $result['message'] ), 200 );
	}
}

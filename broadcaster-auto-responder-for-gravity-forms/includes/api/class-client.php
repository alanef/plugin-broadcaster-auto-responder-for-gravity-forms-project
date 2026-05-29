<?php
/**
 * Broadcaster API client.
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around wp_remote_* for talking to a Broadcaster instance.
 */
class Client {

	/**
	 * Broadcaster site URL (no trailing slash, no /api/v1).
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Broadcaster API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $base_url Broadcaster site URL.
	 * @param string $api_key  Broadcaster API key.
	 */
	public function __construct( string $base_url, string $api_key ) {
		$this->base_url = untrailingslashit( $base_url );
		$this->api_key  = $api_key;
	}

	/**
	 * Probe the Broadcaster API.
	 *
	 * Strategy: POST an empty body to /api/v1/messages/incoming. The
	 * auth.api.key middleware runs before request validation, so a bad key
	 * yields 401 and a good key yields 422 (validation rejects empty body).
	 * Either resolution proves we reached Broadcaster; the 422 path proves
	 * the key is accepted.
	 *
	 * @return array{ok:bool,message:string,http_code:int|null}
	 */
	public function test_connection(): array {
		$endpoint = $this->base_url . '/api/v1/messages/incoming';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 10,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( new \stdClass() ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'        => false,
				'message'   => sprintf(
					/* translators: %s: low-level transport error message */
					__( 'Cannot reach Broadcaster: %s', 'broadcaster-auto-responder-for-gravity-forms' ),
					$response->get_error_message()
				),
				'http_code' => null,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			return array(
				'ok'        => false,
				'message'   => __( 'Broadcaster rejected the API key (HTTP 401/403). Check the key and try again.', 'broadcaster-auto-responder-for-gravity-forms' ),
				'http_code' => $code,
			);
		}

		if ( 404 === $code ) {
			return array(
				'ok'        => false,
				'message'   => __( 'Broadcaster URL responded but the messages endpoint was not found (HTTP 404). Check the site URL.', 'broadcaster-auto-responder-for-gravity-forms' ),
				'http_code' => $code,
			);
		}

		// 422 = key accepted, body rejected (expected). 200/201/202 = key accepted, message accepted (also fine).
		if ( 422 === $code || ( $code >= 200 && $code < 300 ) ) {
			return array(
				'ok'        => true,
				'message'   => __( 'Connected. Broadcaster accepted the API key.', 'broadcaster-auto-responder-for-gravity-forms' ),
				'http_code' => $code,
			);
		}

		return array(
			'ok'        => false,
			'message'   => sprintf(
				/* translators: %d: HTTP status code returned by Broadcaster */
				__( 'Unexpected response from Broadcaster (HTTP %d).', 'broadcaster-auto-responder-for-gravity-forms' ),
				$code
			),
			'http_code' => $code,
		);
	}

	/**
	 * Send an incoming-message payload to /api/v1/messages/incoming.
	 *
	 * The plugin assumes Broadcaster has already validated this payload
	 * shape (BRO-883). Empty values are NOT stripped here — pass only the
	 * keys you want sent.
	 *
	 * The return shape carries the `auto_response` block from the response
	 * body (`data.auto_response`, BRO-905) and an `error_code` lifted from
	 * `auto_response.error_code` when present (BRO-902). Callers use these to
	 * surface the auto-response outcome / a recipient rejection without
	 * re-parsing the raw response body. Both are null when no template was
	 * configured on the feed or on a transport error.
	 *
	 * @param array $payload Payload matching the BRO-883 contract.
	 *
	 * @return array{ok:bool,http_code:int|null,message:string,response:array|null,error_code:string|null,auto_response:array|null}
	 */
	public function send_incoming_message( array $payload ): array {
		$endpoint = $this->base_url . '/api/v1/messages/incoming';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'            => false,
				'http_code'     => null,
				'message'       => sprintf(
					/* translators: %s: low-level transport error message */
					__( 'Transport error contacting Broadcaster: %s', 'broadcaster-auto-responder-for-gravity-forms' ),
					$response->get_error_message()
				),
				'response'      => null,
				'error_code'    => null,
				'auto_response' => null,
			);
		}

		$code        = (int) wp_remote_retrieve_response_code( $response );
		$body_string = (string) wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body_string, true );
		$body        = is_array( $decoded ) ? $decoded : null;

		// BRO-905: the API wraps the body as { success, data: { ... auto_response } },
		// so auto_response lives under `data` — earlier code looked for it at the
		// top level and therefore never found it (so error_code was always null).
		// Read `data.auto_response` first; fall back to a top-level key defensively.
		$auto_response = null;
		if ( is_array( $body ) ) {
			if ( isset( $body['data']['auto_response'] ) && is_array( $body['data']['auto_response'] ) ) {
				$auto_response = $body['data']['auto_response'];
			} elseif ( isset( $body['auto_response'] ) && is_array( $body['auto_response'] ) ) {
				$auto_response = $body['auto_response'];
			}
		}

		// BRO-902: lift auto_response.error_code (when present) to a top-level
		// key so callers like the API Rejection Surfacer can branch on it
		// without re-parsing the response body.
		$error_code = null;
		if ( is_array( $auto_response ) && isset( $auto_response['error_code'] ) && is_string( $auto_response['error_code'] ) ) {
			$error_code = $auto_response['error_code'];
		}

		if ( $code >= 200 && $code < 300 ) {
			return array(
				'ok'            => true,
				'http_code'     => $code,
				'message'       => __( 'Broadcaster accepted the message.', 'broadcaster-auto-responder-for-gravity-forms' ),
				'response'      => $body,
				'error_code'    => $error_code,
				'auto_response' => $auto_response,
			);
		}

		$detail = '';
		if ( is_array( $body ) ) {
			if ( isset( $body['message'] ) && is_string( $body['message'] ) ) {
				$detail = $body['message'];
			} elseif ( isset( $body['error'] ) && is_string( $body['error'] ) ) {
				$detail = $body['error'];
			}
		}

		return array(
			'ok'            => false,
			'http_code'     => $code,
			'message'       => sprintf(
				/* translators: 1: HTTP status code, 2: detail message from Broadcaster */
				__( 'Broadcaster rejected the message (HTTP %1$d). %2$s', 'broadcaster-auto-responder-for-gravity-forms' ),
				$code,
				$detail
			),
			'response'      => $body,
			'error_code'    => $error_code,
			'auto_response' => $auto_response,
		);
	}
}

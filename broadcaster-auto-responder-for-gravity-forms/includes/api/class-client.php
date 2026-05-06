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
 *
 * BRO-880 ships only the connection-test method. Submission dispatch
 * (POST /api/v1/messages/incoming with a real payload) is added in BRO-882.
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
}

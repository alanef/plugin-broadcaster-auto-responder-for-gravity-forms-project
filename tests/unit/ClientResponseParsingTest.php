<?php
/**
 * Unit tests for Broadcaster API Client response parsing (BRO-905).
 *
 * Covers extraction of the `data.auto_response` block and the lifted
 * `error_code` from the /api/v1/messages/incoming response. Uses tiny
 * stubs for the wp_remote_* family so the class can be exercised without
 * booting WordPress.
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\Api\Client;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}
if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		return $GLOBALS['brogf_test_http_response'] ?? array( 'code' => 200, 'body' => '{}' );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return $response['code'] ?? 0;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return $response['body'] ?? '';
	}
}

/**
 * @covers \BroadcasterGF\Api\Client
 */
final class ClientResponseParsingTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['brogf_test_http_response'] );
		parent::tearDown();
	}

	private function fakeHttp( int $code, array $body ): void {
		$GLOBALS['brogf_test_http_response'] = array( 'code' => $code, 'body' => wp_json_encode( $body ) );
	}

	public function test_auto_response_lifted_from_data_on_success(): void {
		$this->fakeHttp( 201, array(
			'success' => true,
			'data'    => array(
				'message_id'    => 1711,
				'auto_response' => array( 'requested' => true, 'success' => true, 'template_name' => 'contact_form_reply', 'template_slot' => 'in_hours' ),
			),
		) );

		$result = ( new Client( 'https://example.test/', 'key' ) )->send_incoming_message( array( 'message' => 'hi' ) );

		$this->assertTrue( $result['ok'] );
		$this->assertIsArray( $result['auto_response'] );
		$this->assertTrue( $result['auto_response']['success'] );
		$this->assertSame( 'contact_form_reply', $result['auto_response']['template_name'] );
		$this->assertNull( $result['error_code'] );
	}

	public function test_error_code_lifted_from_data_auto_response(): void {
		$this->fakeHttp( 201, array(
			'success' => true,
			'data'    => array(
				'message_id'    => 1712,
				'auto_response' => array( 'requested' => true, 'success' => false, 'error_code' => 'recipient_not_deliverable', 'error' => 'no phone' ),
			),
		) );

		$result = ( new Client( 'https://example.test', 'key' ) )->send_incoming_message( array( 'message' => 'hi' ) );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'recipient_not_deliverable', $result['error_code'] );
		$this->assertSame( 'recipient_not_deliverable', $result['auto_response']['error_code'] );
	}

	public function test_no_auto_response_when_absent(): void {
		$this->fakeHttp( 201, array( 'success' => true, 'data' => array( 'message_id' => 1713, 'new_client' => false ) ) );

		$result = ( new Client( 'https://example.test', 'key' ) )->send_incoming_message( array( 'message' => 'hi' ) );

		$this->assertTrue( $result['ok'] );
		$this->assertNull( $result['auto_response'] );
		$this->assertNull( $result['error_code'] );
	}

	public function test_legacy_top_level_auto_response_still_read(): void {
		// Defensive fallback for an alternate body shape.
		$this->fakeHttp( 201, array( 'auto_response' => array( 'success' => false, 'error_code' => 'template_not_found' ) ) );

		$result = ( new Client( 'https://example.test', 'key' ) )->send_incoming_message( array( 'message' => 'hi' ) );

		$this->assertSame( 'template_not_found', $result['error_code'] );
		$this->assertIsArray( $result['auto_response'] );
	}
}

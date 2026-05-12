<?php
/**
 * Unit tests for Api_Rejection_Surfacer.
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\GF\Api_Rejection_Surfacer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BroadcasterGF\GF\Api_Rejection_Surfacer
 */
final class ApiRejectionSurfacerTest extends TestCase {

	// --- is_rejection ---

	public function test_http_422_is_rejection(): void {
		$response = array(
			'ok'        => false,
			'http_code' => 422,
			'message'   => 'Validation failed',
			'response'  => null,
		);
		$this->assertTrue( Api_Rejection_Surfacer::is_rejection( $response ) );
	}

	public function test_recipient_not_deliverable_error_code_is_rejection(): void {
		$response = array(
			'ok'         => true,
			'http_code'  => 201,
			'message'    => 'Accepted',
			'response'   => array( 'auto_response' => array( 'error_code' => 'recipient_not_deliverable' ) ),
			'error_code' => 'recipient_not_deliverable',
		);
		$this->assertTrue( Api_Rejection_Surfacer::is_rejection( $response ) );
	}

	public function test_recipient_not_deliverable_extracted_from_nested_response(): void {
		// Surfacer falls back to digging into response when Client hasn't
		// populated the top-level error_code yet.
		$response = array(
			'ok'        => true,
			'http_code' => 201,
			'message'   => 'Accepted',
			'response'  => array( 'auto_response' => array( 'error_code' => 'recipient_not_deliverable' ) ),
		);
		$this->assertTrue( Api_Rejection_Surfacer::is_rejection( $response ) );
	}

	public function test_recipient_not_deliverable_extracted_from_data_auto_response(): void {
		// BRO-905: real API body shape — auto_response lives under `data`.
		$response = array(
			'ok'        => true,
			'http_code' => 201,
			'message'   => 'Accepted',
			'response'  => array( 'success' => true, 'data' => array( 'auto_response' => array( 'error_code' => 'recipient_not_deliverable' ) ) ),
		);
		$this->assertTrue( Api_Rejection_Surfacer::is_rejection( $response ) );
	}

	public function test_clean_success_is_not_rejection(): void {
		$response = array(
			'ok'        => true,
			'http_code' => 201,
			'message'   => 'Accepted',
			'response'  => array( 'id' => 42 ),
		);
		$this->assertFalse( Api_Rejection_Surfacer::is_rejection( $response ) );
	}

	public function test_500_error_is_not_a_recipient_rejection(): void {
		// Server errors are NOT recipient rejections — they're transport/
		// availability problems. The form/feed has its own retry logic.
		$response = array(
			'ok'        => false,
			'http_code' => 500,
			'message'   => 'Internal server error',
			'response'  => null,
		);
		$this->assertFalse( Api_Rejection_Surfacer::is_rejection( $response ) );
	}

	public function test_unknown_error_code_is_not_recipient_rejection(): void {
		// Only `recipient_not_deliverable` triggers the rejection path; other
		// auto_response error codes (e.g. template_missing) might be
		// surfaced differently in future tickets.
		$response = array(
			'ok'         => true,
			'http_code'  => 201,
			'message'    => 'Accepted',
			'response'   => array( 'auto_response' => array( 'error_code' => 'template_missing' ) ),
			'error_code' => 'template_missing',
		);
		$this->assertFalse( Api_Rejection_Surfacer::is_rejection( $response ) );
	}

	// --- extract_rejection_summary ---

	public function test_summary_includes_http_code_error_code_and_message(): void {
		$response = array(
			'http_code'  => 422,
			'message'    => 'Phone number format invalid',
			'error_code' => 'recipient_not_deliverable',
			'response'   => null,
		);
		$summary = Api_Rejection_Surfacer::extract_rejection_summary( $response );

		$this->assertSame( 422, $summary['http_code'] );
		$this->assertSame( 'recipient_not_deliverable', $summary['error_code'] );
		$this->assertSame( 'Phone number format invalid', $summary['message'] );
	}

	public function test_summary_handles_missing_keys(): void {
		$summary = Api_Rejection_Surfacer::extract_rejection_summary( array() );

		$this->assertNull( $summary['http_code'] );
		$this->assertNull( $summary['error_code'] );
		$this->assertSame( '', $summary['message'] );
	}

	// --- surface() side-effects via injected callables ---

	public function test_surface_returns_false_and_calls_nothing_for_non_rejection(): void {
		$logger_called      = false;
		$meta_setter_called = false;
		$note_adder_called  = false;

		$result = Api_Rejection_Surfacer::surface(
			array(
				'ok'        => true,
				'http_code' => 201,
				'message'   => 'Accepted',
				'response'  => null,
			),
			42,
			3,
			function () use ( &$logger_called ) {
				$logger_called = true;
			},
			function () use ( &$meta_setter_called ) {
				$meta_setter_called = true;
			},
			function () use ( &$note_adder_called ) {
				$note_adder_called = true;
			}
		);

		$this->assertFalse( $result );
		$this->assertFalse( $logger_called );
		$this->assertFalse( $meta_setter_called );
		$this->assertFalse( $note_adder_called );
	}

	public function test_surface_calls_meta_setter_with_correct_key_and_payload(): void {
		$captured = array();
		Api_Rejection_Surfacer::surface(
			array(
				'ok'        => false,
				'http_code' => 422,
				'message'   => 'Phone format invalid',
				'response'  => null,
			),
			42,
			3,
			function () {},
			function ( $entry_id, $key, $value ) use ( &$captured ) {
				$captured = array(
					'entry_id' => $entry_id,
					'key'      => $key,
					'value'    => $value,
				);
			},
			function () {}
		);

		$this->assertSame( 42, $captured['entry_id'] );
		$this->assertSame( '_broadcastergf_rejection_3', $captured['key'] );
		$this->assertIsArray( $captured['value'] );
		$this->assertSame( 422, $captured['value']['http_code'] );
		$this->assertSame( 'Phone format invalid', $captured['value']['message'] );
		$this->assertArrayHasKey( 'at', $captured['value'] );
	}

	public function test_surface_calls_note_adder_with_human_readable_note(): void {
		$captured = array();
		Api_Rejection_Surfacer::surface(
			array(
				'ok'         => true,
				'http_code'  => 201,
				'message'    => 'Recipient unreachable',
				'response'   => array( 'auto_response' => array( 'error_code' => 'recipient_not_deliverable' ) ),
				'error_code' => 'recipient_not_deliverable',
			),
			42,
			3,
			function () {},
			function () {},
			function ( $entry_id, $user_id, $user_name, $note ) use ( &$captured ) {
				$captured = array(
					'entry_id'  => $entry_id,
					'user_id'   => $user_id,
					'user_name' => $user_name,
					'note'      => $note,
				);
			}
		);

		$this->assertSame( 42, $captured['entry_id'] );
		$this->assertSame( 0, $captured['user_id'] );
		$this->assertSame( 'Broadcaster', $captured['user_name'] );
		$this->assertStringContainsString( '201', $captured['note'] );
		$this->assertStringContainsString( 'recipient_not_deliverable', $captured['note'] );
		$this->assertStringContainsString( 'Recipient unreachable', $captured['note'] );
	}

	public function test_surface_calls_logger_with_structured_one_liner(): void {
		$captured_log = '';
		Api_Rejection_Surfacer::surface(
			array(
				'ok'        => false,
				'http_code' => 422,
				'message'   => 'Phone format invalid',
				'response'  => null,
			),
			42,
			3,
			function ( $line ) use ( &$captured_log ) {
				$captured_log = $line;
			},
			function () {},
			function () {}
		);

		$this->assertStringContainsString( 'entry=42', $captured_log );
		$this->assertStringContainsString( 'field=3', $captured_log );
		$this->assertStringContainsString( 'http=422', $captured_log );
		$this->assertStringContainsString( 'Phone format invalid', $captured_log );
	}

	public function test_surface_returns_true_on_rejection(): void {
		$result = Api_Rejection_Surfacer::surface(
			array(
				'ok'        => false,
				'http_code' => 422,
				'message'   => 'Phone format invalid',
				'response'  => null,
			),
			42,
			3,
			function () {},
			function () {},
			function () {}
		);

		$this->assertTrue( $result );
	}
}

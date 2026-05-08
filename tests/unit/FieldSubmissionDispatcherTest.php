<?php
/**
 * Unit tests for Field_Submission_Dispatcher.
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\GF\Field_Submission_Dispatcher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BroadcasterGF\GF\Field_Submission_Dispatcher
 */
final class FieldSubmissionDispatcherTest extends TestCase {

	// --- Phone path ---

	public function test_gb_national_phone_returns_phone_payload(): void {
		$payload = Field_Submission_Dispatcher::dispatch( '07700 900123', 'GB', false );

		$this->assertSame(
			array(
				'kind'  => 'phone',
				'value' => '+447700900123',
			),
			$payload
		);
	}

	public function test_international_phone_overrides_region(): void {
		// Field region is GB; the `+33` prefix wins.
		$payload = Field_Submission_Dispatcher::dispatch( '+33 6 12 34 56 78', 'GB', false );

		$this->assertSame(
			array(
				'kind'  => 'phone',
				'value' => '+33612345678',
			),
			$payload
		);
	}

	public function test_invalid_phone_returns_null(): void {
		// Too short for GB.
		$this->assertNull( Field_Submission_Dispatcher::dispatch( '07700 9001', 'GB', false ) );
	}

	public function test_unsupported_region_without_plus_prefix_returns_null(): void {
		// `XX` isn't a real region code; no `+` prefix → can't construct E.164.
		$this->assertNull( Field_Submission_Dispatcher::dispatch( '0721234567', 'XX', false ) );
	}

	public function test_unsupported_region_with_plus_prefix_works(): void {
		$payload = Field_Submission_Dispatcher::dispatch( '+27 72 123 4567', 'XX', false );

		$this->assertSame(
			array(
				'kind'  => 'phone',
				'value' => '+27721234567',
			),
			$payload
		);
	}

	// --- Username path (flag on) ---

	public function test_valid_username_with_flag_on_returns_username_payload(): void {
		$payload = Field_Submission_Dispatcher::dispatch( '@some_user', 'GB', true );

		$this->assertSame(
			array(
				'kind'  => 'username',
				'value' => 'some_user',
			),
			$payload
		);
	}

	public function test_username_lowercased_before_returning(): void {
		// Meta's "case is ignored when comparing" — dispatcher normalises.
		$payload = Field_Submission_Dispatcher::dispatch( '@AlanEF', 'GB', true );

		$this->assertSame(
			array(
				'kind'  => 'username',
				'value' => 'alanef',
			),
			$payload
		);
	}

	public function test_username_with_leading_whitespace_routes_correctly(): void {
		$payload = Field_Submission_Dispatcher::dispatch( "  \t@some_user", 'GB', true );

		$this->assertSame(
			array(
				'kind'  => 'username',
				'value' => 'some_user',
			),
			$payload
		);
	}

	public function test_invalid_username_with_flag_on_returns_null(): void {
		// Too short (after stripping the `@`).
		$this->assertNull( Field_Submission_Dispatcher::dispatch( '@a', 'GB', true ) );
	}

	public function test_username_failing_charset_with_flag_on_returns_null(): void {
		$this->assertNull( Field_Submission_Dispatcher::dispatch( '@bad-user', 'GB', true ) );
	}

	public function test_www_prefix_username_with_flag_on_returns_null(): void {
		$this->assertNull( Field_Submission_Dispatcher::dispatch( '@wwwuser', 'GB', true ) );
	}

	// --- Username path (flag off) ---

	public function test_valid_username_with_flag_off_returns_null(): void {
		// Flag off → discriminator says invalid for any `@` input.
		$this->assertNull( Field_Submission_Dispatcher::dispatch( '@some_user', 'GB', false ) );
	}

	// --- Empty / blank ---

	public function test_empty_input_returns_null(): void {
		$this->assertNull( Field_Submission_Dispatcher::dispatch( '', 'GB', true ) );
	}

	public function test_whitespace_only_returns_null(): void {
		$this->assertNull( Field_Submission_Dispatcher::dispatch( "   \t  ", 'GB', true ) );
	}
}

<?php
/**
 * Unit tests for Recipient_Discriminator.
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\GF\Recipient_Discriminator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BroadcasterGF\GF\Recipient_Discriminator
 */
final class RecipientDiscriminatorTest extends TestCase {

	public function test_phone_input_returns_phone_kind(): void {
		$this->assertSame(
			Recipient_Discriminator::KIND_PHONE,
			Recipient_Discriminator::discriminate( '07700 900123', false )
		);
	}

	public function test_at_prefix_with_flag_on_returns_username(): void {
		$this->assertSame(
			Recipient_Discriminator::KIND_USERNAME,
			Recipient_Discriminator::discriminate( '@some_user', true )
		);
	}

	public function test_at_prefix_with_flag_off_returns_invalid(): void {
		$this->assertSame(
			Recipient_Discriminator::KIND_INVALID,
			Recipient_Discriminator::discriminate( '@some_user', false )
		);
	}

	public function test_empty_string_returns_invalid(): void {
		$this->assertSame(
			Recipient_Discriminator::KIND_INVALID,
			Recipient_Discriminator::discriminate( '', true )
		);
	}

	public function test_whitespace_only_returns_invalid(): void {
		$this->assertSame(
			Recipient_Discriminator::KIND_INVALID,
			Recipient_Discriminator::discriminate( "  \t  ", true )
		);
	}

	public function test_leading_whitespace_with_at_routes_username(): void {
		$this->assertSame(
			Recipient_Discriminator::KIND_USERNAME,
			Recipient_Discriminator::discriminate( '  @user', true )
		);
	}

	public function test_leading_whitespace_with_phone_routes_phone(): void {
		$this->assertSame(
			Recipient_Discriminator::KIND_PHONE,
			Recipient_Discriminator::discriminate( '  07700 900123', false )
		);
	}

	public function test_at_in_middle_of_string_routes_phone(): void {
		// Only the leading character matters; an `@` mid-string is just
		// a malformed phone, which the validator will reject downstream.
		$this->assertSame(
			Recipient_Discriminator::KIND_PHONE,
			Recipient_Discriminator::discriminate( '07@invalid', false )
		);
	}

	public function test_plus_prefix_routes_phone(): void {
		$this->assertSame(
			Recipient_Discriminator::KIND_PHONE,
			Recipient_Discriminator::discriminate( '+447700900123', false )
		);
	}
}

<?php
/**
 * Unit tests for Phone_Validator.
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\GF\Phone_Validator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BroadcasterGF\GF\Phone_Validator
 */
final class PhoneValidatorTest extends TestCase {

	// --- GB ---

	public function test_gb_national_mobile_normalises_to_e164(): void {
		$this->assertSame( '+447700900123', Phone_Validator::normalize( '07700 900123', 'GB' ) );
	}

	public function test_gb_national_with_parens_and_spaces_normalises(): void {
		$this->assertSame( '+447700900123', Phone_Validator::normalize( '(07700) 900-123', 'GB' ) );
	}

	public function test_gb_national_landline_normalises(): void {
		$this->assertSame( '+442012345678', Phone_Validator::normalize( '0201 2345 678', 'GB' ) );
	}

	public function test_gb_with_explicit_plus_prefix_kept_as_is(): void {
		$this->assertSame( '+447700900123', Phone_Validator::normalize( '+44 7700 900123', 'GB' ) );
	}

	public function test_gb_too_short_rejected(): void {
		$this->assertNull( Phone_Validator::normalize( '07700 9001', 'GB' ) );
	}

	public function test_gb_too_long_rejected(): void {
		$this->assertNull( Phone_Validator::normalize( '077001234567', 'GB' ) );
	}

	public function test_gb_starting_with_double_zero_rejected(): void {
		$this->assertNull( Phone_Validator::normalize( '00700 900123', 'GB' ) );
	}

	// --- US ---

	public function test_us_national_normalises(): void {
		$this->assertSame( '+13035550100', Phone_Validator::normalize( '303-555-0100', 'US' ) );
	}

	public function test_us_with_leading_1_normalises(): void {
		$this->assertSame( '+13035550100', Phone_Validator::normalize( '1-303-555-0100', 'US' ) );
	}

	public function test_us_starting_with_1_in_area_code_rejected(): void {
		$this->assertNull( Phone_Validator::normalize( '103-555-0100', 'US' ) );
	}

	public function test_us_too_short_rejected(): void {
		$this->assertNull( Phone_Validator::normalize( '303-555-010', 'US' ) );
	}

	// --- IN ---

	public function test_in_national_normalises(): void {
		$this->assertSame( '+919876543210', Phone_Validator::normalize( '9876543210', 'IN' ) );
	}

	public function test_in_starting_with_5_rejected(): void {
		$this->assertNull( Phone_Validator::normalize( '5876543210', 'IN' ) );
	}

	// --- AU ---

	public function test_au_national_normalises(): void {
		$this->assertSame( '+61412345678', Phone_Validator::normalize( '0412 345 678', 'AU' ) );
	}

	// --- IE ---

	public function test_ie_national_normalises(): void {
		$this->assertSame( '+353871234567', Phone_Validator::normalize( '087 123 4567', 'IE' ) );
	}

	// --- ID ---

	public function test_id_national_normalises(): void {
		$this->assertSame( '+62812345678', Phone_Validator::normalize( '0812345678', 'ID' ) );
	}

	// --- International (+ prefix) regardless of region ---

	public function test_international_french_number_with_gb_default(): void {
		$this->assertSame( '+33612345678', Phone_Validator::normalize( '+33 6 12 34 56 78', 'GB' ) );
	}

	public function test_international_e164_too_short_rejected(): void {
		$this->assertNull( Phone_Validator::normalize( '+44123', 'GB' ) );
	}

	public function test_international_e164_too_long_rejected(): void {
		$this->assertNull( Phone_Validator::normalize( '+12345678901234567', 'GB' ) );
	}

	public function test_international_starting_with_zero_rejected(): void {
		$this->assertNull( Phone_Validator::normalize( '+01234567890', 'GB' ) );
	}

	// --- Unsupported region ---

	public function test_unsupported_region_national_format_rejected(): void {
		// `XX` is not a real region code. Without a `+` prefix we can't
		// construct E.164, so this fails closed for any region not in the
		// supported list.
		$this->assertNull( Phone_Validator::normalize( '0721234567', 'XX' ) );
	}

	public function test_unsupported_region_with_plus_prefix_accepted(): void {
		// Even with an unknown region, an explicit `+` prefix passes the
		// E.164 shape check and is kept as-is.
		$this->assertSame( '+27721234567', Phone_Validator::normalize( '+27 72 123 4567', 'XX' ) );
	}

	// --- Empty / blank ---

	public function test_empty_input_rejected(): void {
		$this->assertNull( Phone_Validator::normalize( '', 'GB' ) );
	}

	public function test_whitespace_only_rejected(): void {
		$this->assertNull( Phone_Validator::normalize( '   ', 'GB' ) );
	}

	// --- is_valid mirrors normalize ---

	public function test_is_valid_true_when_normalize_succeeds(): void {
		$this->assertTrue( Phone_Validator::is_valid( '07700 900123', 'GB' ) );
	}

	public function test_is_valid_false_when_normalize_fails(): void {
		$this->assertFalse( Phone_Validator::is_valid( 'not a phone', 'GB' ) );
	}

	// --- dialling_code helper ---

	public function test_dialling_code_returns_country_code_for_supported_region(): void {
		$this->assertSame( '44', Phone_Validator::dialling_code( 'GB' ) );
		$this->assertSame( '1', Phone_Validator::dialling_code( 'US' ) );
	}

	public function test_dialling_code_returns_null_for_unsupported_region(): void {
		$this->assertNull( Phone_Validator::dialling_code( 'XX' ) );
	}

	public function test_dialling_code_case_insensitive(): void {
		$this->assertSame( '44', Phone_Validator::dialling_code( 'gb' ) );
	}
}

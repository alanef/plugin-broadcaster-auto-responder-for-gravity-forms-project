<?php
/**
 * Unit tests for Username_Validator.
 *
 * Rules mirror Meta's published WhatsApp business username spec; see the
 * docblock on Username_Validator for the source of truth.
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\GF\Username_Validator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BroadcasterGF\GF\Username_Validator
 */
final class UsernameValidatorTest extends TestCase {

	// --- Length ---

	public function test_length_3_valid(): void {
		$this->assertTrue( Username_Validator::is_valid( 'abc' ) );
	}

	public function test_length_2_invalid(): void {
		$this->assertFalse( Username_Validator::is_valid( 'ab' ) );
	}

	public function test_length_35_valid(): void {
		$this->assertTrue( Username_Validator::is_valid( str_repeat( 'a', 35 ) ) );
	}

	public function test_length_36_invalid(): void {
		$this->assertFalse( Username_Validator::is_valid( str_repeat( 'a', 36 ) ) );
	}

	// --- Charset ---

	public function test_lowercase_letters_valid(): void {
		$this->assertTrue( Username_Validator::is_valid( 'alanef' ) );
	}

	public function test_uppercase_letters_valid(): void {
		// Both cases are accepted on input; lowercasing happens later in
		// the Field_Submission_Dispatcher per Meta's "case is ignored when
		// comparing" rule.
		$this->assertTrue( Username_Validator::is_valid( 'AlanEF' ) );
	}

	public function test_digits_with_letters_valid(): void {
		$this->assertTrue( Username_Validator::is_valid( 'alan123' ) );
	}

	public function test_periods_with_letters_valid(): void {
		$this->assertTrue( Username_Validator::is_valid( 'alan.fuller' ) );
	}

	public function test_underscores_with_letters_valid(): void {
		$this->assertTrue( Username_Validator::is_valid( 'alan_f' ) );
	}

	public function test_non_english_letter_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( 'alané' ) );
	}

	public function test_hyphen_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( 'alan-f' ) );
	}

	public function test_space_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( 'alan f' ) );
	}

	// --- Must contain a letter ---

	public function test_digits_only_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( '12345' ) );
	}

	public function test_periods_underscores_only_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( '_._._._' ) );
	}

	// --- Leading / trailing periods ---

	public function test_leading_period_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( '.alan' ) );
	}

	public function test_trailing_period_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( 'alan.' ) );
	}

	// --- Consecutive periods ---

	public function test_consecutive_periods_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( 'alan..fuller' ) );
	}

	// --- www prefix ---

	public function test_www_prefix_lowercase_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( 'www123' ) );
	}

	public function test_www_prefix_mixed_case_rejected(): void {
		// Meta says "must not start with www" — case-insensitive.
		$this->assertFalse( Username_Validator::is_valid( 'WwWtest' ) );
	}

	public function test_www_anywhere_else_valid(): void {
		// `www` is only blocked as a prefix, not anywhere in the string.
		$this->assertTrue( Username_Validator::is_valid( 'mywwwsite' ) );
	}

	// --- TLD blocklist ---

	public function test_dot_com_suffix_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( 'site.com' ) );
	}

	public function test_dot_org_suffix_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( 'site.org' ) );
	}

	public function test_dot_html_suffix_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( 'page.html' ) );
	}

	public function test_dot_uk_suffix_rejected(): void {
		$this->assertFalse( Username_Validator::is_valid( 'company.uk' ) );
	}

	public function test_dot_int_suffix_rejected(): void {
		// Meta enumerates `.int` explicitly.
		$this->assertFalse( Username_Validator::is_valid( 'who.int' ) );
	}

	public function test_known_tld_in_middle_valid(): void {
		// `.com` mid-string is fine — only the suffix is blocked.
		$this->assertTrue( Username_Validator::is_valid( 'com.alan' ) );
	}

	public function test_unknown_tld_suffix_valid(): void {
		// `.foo` isn't on the blocklist; passes shape rules.
		$this->assertTrue( Username_Validator::is_valid( 'site.foo' ) );
	}

	// --- Empty ---

	public function test_empty_invalid(): void {
		$this->assertFalse( Username_Validator::is_valid( '' ) );
	}
}

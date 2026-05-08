<?php
/**
 * Username Validator — validates a WhatsApp username against Meta's
 * published rules.
 *
 * Source of truth: Meta's WhatsApp Business platform documentation,
 * "Business usernames" section. Quoted in BRO-902's implementation plan.
 *
 *   - Length 3-35 characters.
 *   - Charset: a-z, A-Z, 0-9, period (.), underscore (_). Both cases
 *     accepted on input; the Field_Submission_Dispatcher lowercases before
 *     sending to the API ("case is ignored when comparing usernames").
 *   - Must contain at least one English letter.
 *   - Cannot start or end with a period.
 *   - No two consecutive periods.
 *   - Cannot start with `www` (case-insensitive — note: Meta's wording is
 *     "must not start with www", which matches this stricter rule rather
 *     than "must not start with www.").
 *   - Cannot end with a TLD-like suffix. Meta lists `.com .org .net .int
 *     .edu .gov .mil .us .in .html and so on`; the blocklist in this
 *     validator covers Meta's enumerated set plus common TLDs already
 *     blocked on Broadcaster's server-side validator.
 *   - Meta-platform uniqueness is server-only; not enforceable here.
 *
 * The input is the username WITHOUT the leading `@` sentinel — the `@` is
 * the Recipient_Discriminator's responsibility, not this validator's.
 *
 * BRO-903 tracks aligning Broadcaster's `Company::isValidWhatsappUsername()`
 * with the same Meta spec; the plugin uses Meta's spec directly so it is
 * correct from day one regardless of when BRO-903 ships.
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\GF;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates a WhatsApp username against Meta's spec.
 */
final class Username_Validator {

	const MIN_LENGTH = 3;
	const MAX_LENGTH = 35;

	/**
	 * TLDs that may not appear as a suffix.
	 *
	 * Meta's explicit list (com, org, net, int, edu, gov, mil, us, in, html)
	 * plus common TLDs already on Broadcaster's server-side blocklist.
	 */
	private static function tld_blocklist() {
		return array(
			'com',
			'org',
			'net',
			'int',
			'edu',
			'gov',
			'mil',
			'us',
			'in',
			'html',
			'io',
			'co',
			'uk',
			'de',
			'fr',
			'es',
			'it',
			'nl',
			'be',
			'au',
			'ca',
			'info',
			'biz',
		);
	}

	/**
	 * Validate a WhatsApp username (without the leading `@`).
	 *
	 * @param string $username Username to validate.
	 * @return bool
	 */
	public static function is_valid( $username ) {
		$username = (string) $username;
		$len      = strlen( $username );

		if ( $len < self::MIN_LENGTH || $len > self::MAX_LENGTH ) {
			return false;
		}

		// Charset: a-z, A-Z, 0-9, period, underscore.
		if ( ! preg_match( '/^[a-zA-Z0-9._]+$/', $username ) ) {
			return false;
		}

		// Must contain at least one English letter.
		if ( ! preg_match( '/[a-zA-Z]/', $username ) ) {
			return false;
		}

		// Cannot start or end with a period.
		if ( '.' === $username[0] || '.' === $username[ $len - 1 ] ) {
			return false;
		}

		// No consecutive periods.
		if ( false !== strpos( $username, '..' ) ) {
			return false;
		}

		// Cannot start with `www` (case-insensitive).
		if ( 0 === stripos( $username, 'www' ) ) {
			return false;
		}

		// Cannot end with a TLD-like suffix.
		$tlds = implode( '|', self::tld_blocklist() );
		if ( preg_match( '/\.(' . $tlds . ')$/i', $username ) ) {
			return false;
		}

		return true;
	}
}

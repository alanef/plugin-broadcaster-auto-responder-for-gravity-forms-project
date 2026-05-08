<?php
/**
 * Recipient Discriminator — decides whether a submitted value is a phone or
 * a WhatsApp username, based on the leading character.
 *
 * The rule (BRO-902 design D2): trim leading whitespace from the input, then
 * check the first character. `@` means the username path; anything else means
 * the phone path. Empty input is invalid.
 *
 * Honours the `BROADCASTERGF_ENABLE_USERNAMES` feature flag (D12): when the
 * flag is off, the username branch reports "invalid" instead of routing to
 * the username path. This keeps the submitter-facing surface phone-only
 * until Meta enables WhatsApp usernames generally, while the underlying
 * code path stays exercised by tests.
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\GF;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides recipient kind (phone / username / invalid) for a submitted value.
 */
final class Recipient_Discriminator {

	const KIND_PHONE    = 'phone';
	const KIND_USERNAME = 'username';
	const KIND_INVALID  = 'invalid';

	/**
	 * Decide which recipient path the input should take.
	 *
	 * @param string $input             Raw submitter input as typed.
	 * @param bool   $usernames_enabled Whether the username surface is active.
	 *                                  Pass `BROADCASTERGF_ENABLE_USERNAMES`.
	 * @return string One of self::KIND_PHONE | self::KIND_USERNAME | self::KIND_INVALID.
	 */
	public static function discriminate( $input, $usernames_enabled ) {
		$trimmed = ltrim( (string) $input );

		if ( '' === $trimmed ) {
			return self::KIND_INVALID;
		}

		if ( '@' === $trimmed[0] ) {
			return $usernames_enabled ? self::KIND_USERNAME : self::KIND_INVALID;
		}

		return self::KIND_PHONE;
	}
}

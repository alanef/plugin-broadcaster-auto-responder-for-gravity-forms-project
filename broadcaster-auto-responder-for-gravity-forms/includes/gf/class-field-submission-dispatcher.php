<?php
/**
 * Field Submission Dispatcher — composes Recipient_Discriminator + the
 * phone/username validators and returns a typed payload describing how
 * the field's submitted value should be sent to the Broadcaster API.
 *
 * BRO-902 design D3: the plugin splits the value at submission and sends
 * a typed payload to Broadcaster's existing phone vs username slots.
 * This class is the place where that split happens.
 *
 * Country precedence (BRO-902 design D5): callers pass the field's
 * effective region (`field_setting ?? plugin_default`). An explicit `+`
 * prefix on the input overrides even that — handled inside Phone_Validator.
 *
 * Username path lowercasing: Meta's "case is ignored when comparing
 * usernames" rule means `myID` and `myid` are the same username on the
 * server. The dispatcher normalises to lowercase before returning so
 * what we send (and what we store as the typed value) is consistent.
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\GF;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes a submitted field value to the right Broadcaster API slot.
 */
final class Field_Submission_Dispatcher {

	const KIND_PHONE    = 'phone';
	const KIND_USERNAME = 'username';

	/**
	 * Dispatch a raw field value to a typed payload, or null if invalid.
	 *
	 * @param string $input             Raw submitter input.
	 * @param string $region            ISO-3166 alpha-2 region for phone normalisation.
	 *                                  Pass the field's effective region
	 *                                  (`field_setting ?? plugin_default`).
	 * @param bool   $usernames_enabled Whether the username path is active.
	 *                                  Pass `BROADCASTERGF_ENABLE_USERNAMES`.
	 * @return array|null On success: ['kind' => 'phone'|'username', 'value' => string].
	 *                    On invalid input (failed validation, empty, flag-off `@`):
	 *                    null.
	 */
	public static function dispatch( $input, $region, $usernames_enabled ) {
		$kind = Recipient_Discriminator::discriminate( $input, (bool) $usernames_enabled );

		if ( Recipient_Discriminator::KIND_INVALID === $kind ) {
			return null;
		}

		if ( Recipient_Discriminator::KIND_USERNAME === $kind ) {
			$without_at = self::strip_at_prefix( $input );

			if ( ! Username_Validator::is_valid( $without_at ) ) {
				return null;
			}

			return array(
				'kind'  => self::KIND_USERNAME,
				'value' => strtolower( $without_at ),
			);
		}

		// Phone path.
		$normalised = Phone_Validator::normalize( $input, $region );

		if ( null === $normalised ) {
			return null;
		}

		return array(
			'kind'  => self::KIND_PHONE,
			'value' => $normalised,
		);
	}

	/**
	 * Strip leading whitespace and the `@` sentinel from a username input.
	 *
	 * @param string $input Raw submitter input.
	 * @return string Input without leading whitespace or `@`.
	 */
	private static function strip_at_prefix( $input ) {
		$trimmed = ltrim( (string) $input );
		if ( '' !== $trimmed && '@' === $trimmed[0] ) {
			return substr( $trimmed, 1 );
		}
		return $trimmed;
	}
}

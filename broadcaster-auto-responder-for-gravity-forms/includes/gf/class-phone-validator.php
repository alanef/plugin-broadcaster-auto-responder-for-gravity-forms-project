<?php
/**
 * Phone Validator — hand-rolled per-region phone-shape validation and
 * normalisation to E.164.
 *
 * Why hand-rolled instead of libphonenumber-js (BRO-902 design Q1 resolution):
 * Broadcaster delivery is async via the Gravity Forms feed, so any phone
 * input that fails server-side validation never gets back to the submitter
 * — it just becomes a silent admin notice on a lead that's already been
 * collected. Client-side validation has to actually validate, both for
 * default-region and `+`-prefixed input. A hand-rolled per-region check
 * stays small (~2 KB JS, no Composer/npm dependency) and catches the typo
 * class (missing digits, wrong starting char) that motivates this BRO.
 *
 * The rules deliberately stay loose (less strict than libphonenumber's
 * full ruleset) to bound drift — Broadcaster's server-side libphonenumber
 * (BRO-893) remains the source of truth for what counts as valid; the
 * plugin's job is to catch obvious typos before the API call.
 *
 * Supported regions for national-format input: GB, IE, AU, ID (lead-zero
 * pattern); US, IN (no lead-zero pattern). Any region outside this set
 * requires the submitter to use a `+` prefix; bare national-format input
 * for an unsupported region is rejected. The supported region list mirrors
 * BRO-893's currency-derived pre-fill list.
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\GF;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and normalises phone input for a configured region.
 */
final class Phone_Validator {

	/**
	 * Per-region national-format rules.
	 *
	 * 'pattern' — applied to the digits-only cleaned input (after stripping
	 *             everything except digits and a leading `+`).
	 * 'strip'   — regex applied once to remove any leading prefix (e.g. `0`
	 *             for GB, optional `1` for US) before prepending the country
	 *             code. Use null for regions where the national number is
	 *             already the international subscriber number (e.g. IN).
	 * 'cc'      — the country dialling code (without `+`).
	 */
	private static function rules() {
		return array(
			'GB' => array(
				'pattern' => '/^0[1-9]\d{9}$/',
				'strip'   => '/^0/',
				'cc'      => '44',
			),
			'IE' => array(
				'pattern' => '/^0[1-9]\d{7,8}$/',
				'strip'   => '/^0/',
				'cc'      => '353',
			),
			'AU' => array(
				'pattern' => '/^0[2-478]\d{8}$/',
				'strip'   => '/^0/',
				'cc'      => '61',
			),
			'ID' => array(
				'pattern' => '/^0\d{8,11}$/',
				'strip'   => '/^0/',
				'cc'      => '62',
			),
			'US' => array(
				'pattern' => '/^1?[2-9]\d{9}$/',
				'strip'   => '/^1/',
				'cc'      => '1',
			),
			'IN' => array(
				'pattern' => '/^[6-9]\d{9}$/',
				'strip'   => null,
				'cc'      => '91',
			),
		);
	}

	/**
	 * Strip whitespace, parens, dashes, dots — keep digits and a leading `+`.
	 *
	 * @param string $input Raw phone input.
	 * @return string Digits-only, with leading `+` preserved if present.
	 */
	public static function strip_formatting( $input ) {
		$cleaned = trim( (string) $input );
		if ( '' === $cleaned ) {
			return '';
		}

		$has_plus = ( '+' === $cleaned[0] );
		$cleaned  = preg_replace( '/\D/', '', $cleaned );

		if ( $has_plus && '' !== $cleaned ) {
			$cleaned = '+' . $cleaned;
		}

		return $cleaned;
	}

	/**
	 * Normalise to E.164 (`+` followed by digits).
	 *
	 * @param string $input  Raw phone input as typed by the submitter.
	 * @param string $region ISO-3166 alpha-2 region (e.g. 'GB') for
	 *                       interpreting national-format input.
	 * @return string|null E.164 phone number on success, null on failure.
	 */
	public static function normalize( $input, $region ) {
		$cleaned = self::strip_formatting( $input );

		if ( '' === $cleaned ) {
			return null;
		}

		// International (`+`) prefix → must match E.164 shape.
		if ( '+' === $cleaned[0] ) {
			if ( preg_match( '/^\+[1-9]\d{6,14}$/', $cleaned ) ) {
				return $cleaned;
			}
			return null;
		}

		$region = strtoupper( (string) $region );
		$rules  = self::rules();

		// Unsupported region → submitter must use `+` prefix; bare national
		// input is rejected because we can't construct E.164 without a
		// country code.
		if ( ! isset( $rules[ $region ] ) ) {
			return null;
		}

		$rule = $rules[ $region ];

		if ( ! preg_match( $rule['pattern'], $cleaned ) ) {
			return null;
		}

		$national = $cleaned;
		if ( null !== $rule['strip'] ) {
			$national = preg_replace( $rule['strip'], '', $cleaned, 1 );
		}

		return '+' . $rule['cc'] . $national;
	}

	/**
	 * Returns true if the input is valid for the given region.
	 *
	 * @param string $input  Raw phone input.
	 * @param string $region ISO-3166 alpha-2 region.
	 * @return bool
	 */
	public static function is_valid( $input, $region ) {
		return null !== self::normalize( $input, $region );
	}

	/**
	 * Returns the country dialling code for a supported region, or null.
	 *
	 * Helper for the helper-text renderer ("We'll assume +44 unless you
	 * start with +").
	 *
	 * @param string $region ISO-3166 alpha-2 region.
	 * @return string|null Country dialling code without leading `+`.
	 */
	public static function dialling_code( $region ) {
		$region = strtoupper( (string) $region );
		$rules  = self::rules();

		return isset( $rules[ $region ] ) ? $rules[ $region ]['cc'] : null;
	}
}

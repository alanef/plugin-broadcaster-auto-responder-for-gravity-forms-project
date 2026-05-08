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
	 * Each entry holds:
	 *   'pattern' — regex applied to the digits-only cleaned input (after
	 *               stripping everything except digits and a leading `+`).
	 *               Deliberately approximate; tighter strict validation is
	 *               Broadcaster's libphonenumber-for-php on the server side.
	 *   'strip'   — regex applied once to remove a leading prefix before
	 *               prepending the country code (e.g. `0` for most European
	 *               regions, optional `1` for NANP). Use null when the
	 *               national number is already the international subscriber
	 *               number (e.g. IN, ES).
	 *   'cc'      — country dialling code, no `+`.
	 *   'name'    — display name for the country picker UI. English by
	 *               convention; localisation is the form designer's
	 *               concern via standard WP translation.
	 */
	private static function rules() {
		return array(
			'GB' => array(
				'pattern' => '/^0[1-9]\d{9}$/',
				'strip'   => '/^0/',
				'cc'      => '44',
				'name'    => 'United Kingdom',
			),
			'IE' => array(
				'pattern' => '/^0[1-9]\d{7,8}$/',
				'strip'   => '/^0/',
				'cc'      => '353',
				'name'    => 'Ireland',
			),
			'US' => array(
				'pattern' => '/^1?[2-9]\d{9}$/',
				'strip'   => '/^1/',
				'cc'      => '1',
				'name'    => 'United States',
			),
			'CA' => array(
				'pattern' => '/^1?[2-9]\d{9}$/',
				'strip'   => '/^1/',
				'cc'      => '1',
				'name'    => 'Canada',
			),
			'AU' => array(
				'pattern' => '/^0[2-478]\d{8}$/',
				'strip'   => '/^0/',
				'cc'      => '61',
				'name'    => 'Australia',
			),
			'NZ' => array(
				'pattern' => '/^0[2-9]\d{7,8}$/',
				'strip'   => '/^0/',
				'cc'      => '64',
				'name'    => 'New Zealand',
			),
			'ZA' => array(
				'pattern' => '/^0\d{9}$/',
				'strip'   => '/^0/',
				'cc'      => '27',
				'name'    => 'South Africa',
			),
			'IN' => array(
				'pattern' => '/^[6-9]\d{9}$/',
				'strip'   => null,
				'cc'      => '91',
				'name'    => 'India',
			),
			'ID' => array(
				'pattern' => '/^0\d{8,11}$/',
				'strip'   => '/^0/',
				'cc'      => '62',
				'name'    => 'Indonesia',
			),
			'MY' => array(
				'pattern' => '/^0\d{8,9}$/',
				'strip'   => '/^0/',
				'cc'      => '60',
				'name'    => 'Malaysia',
			),
			'SG' => array(
				'pattern' => '/^[3689]\d{7}$/',
				'strip'   => null,
				'cc'      => '65',
				'name'    => 'Singapore',
			),
			'TH' => array(
				'pattern' => '/^0\d{8,9}$/',
				'strip'   => '/^0/',
				'cc'      => '66',
				'name'    => 'Thailand',
			),
			'PH' => array(
				'pattern' => '/^0\d{9,10}$/',
				'strip'   => '/^0/',
				'cc'      => '63',
				'name'    => 'Philippines',
			),
			'JP' => array(
				'pattern' => '/^0\d{9,10}$/',
				'strip'   => '/^0/',
				'cc'      => '81',
				'name'    => 'Japan',
			),
			'KR' => array(
				'pattern' => '/^0\d{8,10}$/',
				'strip'   => '/^0/',
				'cc'      => '82',
				'name'    => 'South Korea',
			),
			'AE' => array(
				'pattern' => '/^0?5\d{8}$/',
				'strip'   => '/^0/',
				'cc'      => '971',
				'name'    => 'United Arab Emirates',
			),
			'SA' => array(
				'pattern' => '/^0?5\d{8}$/',
				'strip'   => '/^0/',
				'cc'      => '966',
				'name'    => 'Saudi Arabia',
			),
			'IL' => array(
				'pattern' => '/^0\d{8,9}$/',
				'strip'   => '/^0/',
				'cc'      => '972',
				'name'    => 'Israel',
			),
			'TR' => array(
				'pattern' => '/^0\d{10}$/',
				'strip'   => '/^0/',
				'cc'      => '90',
				'name'    => 'Turkey',
			),
			'NG' => array(
				'pattern' => '/^0[7-9]\d{9}$/',
				'strip'   => '/^0/',
				'cc'      => '234',
				'name'    => 'Nigeria',
			),
			'KE' => array(
				'pattern' => '/^0\d{8,9}$/',
				'strip'   => '/^0/',
				'cc'      => '254',
				'name'    => 'Kenya',
			),
			'EG' => array(
				'pattern' => '/^0\d{9,10}$/',
				'strip'   => '/^0/',
				'cc'      => '20',
				'name'    => 'Egypt',
			),
			'DE' => array(
				'pattern' => '/^0\d{9,11}$/',
				'strip'   => '/^0/',
				'cc'      => '49',
				'name'    => 'Germany',
			),
			'FR' => array(
				'pattern' => '/^0[1-9]\d{8}$/',
				'strip'   => '/^0/',
				'cc'      => '33',
				'name'    => 'France',
			),
			'ES' => array(
				'pattern' => '/^[6-9]\d{8}$/',
				'strip'   => null,
				'cc'      => '34',
				'name'    => 'Spain',
			),
			'IT' => array(
				'pattern' => '/^\d{9,10}$/',
				'strip'   => null,
				'cc'      => '39',
				'name'    => 'Italy',
			),
			'NL' => array(
				'pattern' => '/^0\d{9}$/',
				'strip'   => '/^0/',
				'cc'      => '31',
				'name'    => 'Netherlands',
			),
			'BE' => array(
				'pattern' => '/^0\d{8,9}$/',
				'strip'   => '/^0/',
				'cc'      => '32',
				'name'    => 'Belgium',
			),
			'CH' => array(
				'pattern' => '/^0\d{9}$/',
				'strip'   => '/^0/',
				'cc'      => '41',
				'name'    => 'Switzerland',
			),
			'AT' => array(
				'pattern' => '/^0\d{8,12}$/',
				'strip'   => '/^0/',
				'cc'      => '43',
				'name'    => 'Austria',
			),
			'PT' => array(
				'pattern' => '/^\d{9}$/',
				'strip'   => null,
				'cc'      => '351',
				'name'    => 'Portugal',
			),
			'PL' => array(
				'pattern' => '/^\d{9}$/',
				'strip'   => null,
				'cc'      => '48',
				'name'    => 'Poland',
			),
			'SE' => array(
				'pattern' => '/^0\d{8,9}$/',
				'strip'   => '/^0/',
				'cc'      => '46',
				'name'    => 'Sweden',
			),
			'NO' => array(
				'pattern' => '/^\d{8}$/',
				'strip'   => null,
				'cc'      => '47',
				'name'    => 'Norway',
			),
			'DK' => array(
				'pattern' => '/^\d{8}$/',
				'strip'   => null,
				'cc'      => '45',
				'name'    => 'Denmark',
			),
			'FI' => array(
				'pattern' => '/^0\d{6,11}$/',
				'strip'   => '/^0/',
				'cc'      => '358',
				'name'    => 'Finland',
			),
			'BR' => array(
				'pattern' => '/^\d{10,11}$/',
				'strip'   => null,
				'cc'      => '55',
				'name'    => 'Brazil',
			),
			'MX' => array(
				'pattern' => '/^\d{10}$/',
				'strip'   => null,
				'cc'      => '52',
				'name'    => 'Mexico',
			),
			'AR' => array(
				'pattern' => '/^\d{10,11}$/',
				'strip'   => null,
				'cc'      => '54',
				'name'    => 'Argentina',
			),
		);
	}

	/**
	 * Public list of supported regions for UI consumption (country pickers
	 * in plugin settings and per-field settings) and for the JS validator's
	 * region map. Stable shape: [ region_code => [ 'cc' => …, 'name' => … ] ].
	 *
	 * @return array<string,array{cc:string,name:string}>
	 */
	public static function supported_regions() {
		$rules = self::rules();
		$out   = array();
		foreach ( $rules as $code => $rule ) {
			$out[ $code ] = array(
				'cc'   => $rule['cc'],
				'name' => $rule['name'],
			);
		}
		return $out;
	}

	/**
	 * JS-shaped per-region rules for the public-form Submitter Validator.
	 * Used by the field's enqueue path to wp_localize_script the rules
	 * so the JS validator stays in lockstep with this PHP source of truth.
	 *
	 * @return array<string,array{pattern:string,strip:string|null,cc:string}>
	 */
	public static function js_rules() {
		$rules = self::rules();
		$out   = array();
		foreach ( $rules as $code => $rule ) {
			$out[ $code ] = array(
				// Pattern stripped of PHP delimiters and modifiers — JS uses
				// it as `new RegExp(<pattern>)` source.
				'pattern' => trim( $rule['pattern'], '/' ),
				'strip'   => null !== $rule['strip'] ? trim( $rule['strip'], '/' ) : null,
				'cc'      => $rule['cc'],
			);
		}
		return $out;
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

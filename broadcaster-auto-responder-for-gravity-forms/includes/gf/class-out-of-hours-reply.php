<?php
/**
 * The feed's "Outside business hours" choice (BRO-1878).
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\GF;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Works out which template names a feed sends for each slot.
 *
 * Since BRO-1877 Broadcaster answers each slot with its own template only: an out-of-hours slot
 * left out sends nothing outside business hours. The feed therefore says what should happen
 * outside business hours: reply with the in-hours template, reply with a different one, or not
 * reply at all.
 *
 * Feeds saved by 1.1.x have no stored choice. It is read from what they hold, every time the feed
 * is read, so no feed is missed by a one-off migration. 1.1.x replied at all hours with a lone
 * in-hours template, so that reads as "same template"; so do both slots holding one name.
 */
final class Out_Of_Hours_Reply {

	const SETTING   = 'out_of_hours_reply';
	const SAME      = 'same';
	const DIFFERENT = 'different';
	const NONE      = 'none';

	/**
	 * The feed's choice: the stored one, or for a feed without one, the one its templates imply.
	 *
	 * @param array $meta Feed meta.
	 * @return string One of SAME, DIFFERENT, NONE.
	 */
	public static function choice( array $meta ) {
		$stored = isset( $meta[ self::SETTING ] ) ? (string) $meta[ self::SETTING ] : '';
		if ( in_array( $stored, array( self::SAME, self::DIFFERENT, self::NONE ), true ) ) {
			return $stored;
		}

		$in_hours     = self::template( $meta, 'in_hours_template_name' );
		$out_of_hours = self::template( $meta, 'out_of_hours_template_name' );

		if ( '' === $out_of_hours || $out_of_hours === $in_hours ) {
			return self::SAME;
		}

		return self::DIFFERENT;
	}

	/**
	 * The template names to send. An empty name means the slot is left out of the request.
	 *
	 * @param array $meta Feed meta.
	 * @return array{in_hours_template_name: string, out_of_hours_template_name: string}
	 */
	public static function template_names( array $meta ) {
		$in_hours = self::template( $meta, 'in_hours_template_name' );

		switch ( self::choice( $meta ) ) {
			case self::DIFFERENT:
				$out_of_hours = self::template( $meta, 'out_of_hours_template_name' );
				break;
			case self::NONE:
				$out_of_hours = '';
				break;
			default:
				$out_of_hours = $in_hours;
		}

		return array(
			'in_hours_template_name'     => $in_hours,
			'out_of_hours_template_name' => $out_of_hours,
		);
	}

	/**
	 * The template fields for the request to Broadcaster. A slot with no template is left out, which
	 * Broadcaster reads as "send nothing in those hours".
	 *
	 * @param array $meta Feed meta.
	 * @return array
	 */
	public static function payload_fields( array $meta ) {
		return array_filter(
			self::template_names( $meta ),
			static function ( $name ) {
				return '' !== $name;
			}
		);
	}

	/**
	 * Why the out-of-hours template cannot be saved, or null when it can. Only asked with "different
	 * template": the field is not shown, and not checked, otherwise. An empty one is refused, never saved
	 * quietly as "don't reply"; only the business chooses that.
	 *
	 * @param mixed $value Posted out-of-hours template name.
	 * @return string|null
	 */
	public static function out_of_hours_template_error( $value ) {
		if ( '' === trim( (string) $value ) ) {
			return esc_html__( 'Enter the template to send outside business hours.', 'broadcaster-auto-responder-for-gravity-forms' );
		}
		return null;
	}

	/**
	 * A trimmed template name from feed meta.
	 *
	 * @param array  $meta Feed meta.
	 * @param string $key  Setting name.
	 * @return string
	 */
	private static function template( array $meta, $key ) {
		return isset( $meta[ $key ] ) ? trim( (string) $meta[ $key ] ) : '';
	}
}

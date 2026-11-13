<?php
/**
 * The feed's "Marketing opt-in field" (BRO-2014).
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\GF;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the person's answer to the form's own marketing opt-in box and turns it into the fields Broadcaster records.
 *
 * Broadcaster (BRO-2012, Product Owner decision 12 Nov 2026) records a ticked box as marketing agreed and keeps the
 * proof: the words beside the box, whether it started ticked, and a link back to the Gravity Forms entry, which
 * holds the IP address and the rest of the evidence. An unticked box keeps marketing off. With no field mapped
 * nothing is sent at all: no box was shown, which is not the same as an unticked one.
 *
 * The plugin sets no default tick and adds no wording: the business builds the box, its label and its starting
 * state in Gravity Forms. The mapped field is a checkbox field (one of its choices, or the whole field when it has
 * just one) or a consent field.
 *
 * Fields are read as arrays, which GF_Field supports, so the class can be tested without Gravity Forms.
 */
final class Marketing_Opt_In {

	const SETTING = 'marketing_opt_in_field';

	/** Broadcaster refuses longer values with 422, which would lose the whole enquiry, so they are cut to fit. */
	const WORDING_MAX   = 255;
	const REFERENCE_MAX = 500;

	/**
	 * Why a mapping cannot be used, or null when it can (or nothing is mapped).
	 *
	 * @param string           $mapped Field or input id from the feed setting.
	 * @param array|\ArrayAccess[] $fields The form's fields.
	 *
	 * @return string|null
	 */
	public static function mapping_error( $mapped, $fields ) {
		$mapped = trim( (string) $mapped );
		if ( '' === $mapped ) {
			return null;
		}
		$target = self::target( $mapped, $fields );
		if ( null !== $target ) {
			return null;
		}
		$field = self::field( $mapped, $fields );
		if ( null !== $field && 'checkbox' === self::input_type( $field ) && false === strpos( $mapped, '.' ) ) {
			return __( 'This checkbox field has more than one box. Choose the single box that is the marketing opt-in.', 'broadcaster-auto-responder-for-gravity-forms' );
		}
		return __( 'Choose a checkbox, or the box of a consent field, as the marketing opt-in.', 'broadcaster-auto-responder-for-gravity-forms' );
	}

	/**
	 * The fields to add to the incoming-message payload: empty when nothing usable is mapped.
	 *
	 * @param string               $mapped    Field or input id from the feed setting.
	 * @param array|\ArrayAccess[] $fields    The form's fields.
	 * @param array                $entry     The entry.
	 * @param string               $reference Where the answer can be found (the entry's admin URL).
	 *
	 * @return array<string,bool|string>
	 */
	public static function payload_fields( $mapped, $fields, array $entry, $reference ) {
		$mapped = trim( (string) $mapped );
		if ( '' === $mapped ) {
			return array();
		}
		$target = self::target( $mapped, $fields );
		if ( null === $target ) {
			return array();
		}

		$field = $target['field'];
		$id    = (string) self::get( $field, 'id' );

		if ( 'consent' === self::input_type( $field ) ) {
			$ticked = '1' === (string) ( $entry[ $id . '.1' ] ?? '' );
			// Ticked, the entry keeps the checkbox text the visitor saw; unticked, the field's own is what was shown.
			$shown      = $ticked && '' !== trim( (string) ( $entry[ $id . '.2' ] ?? '' ) ) ? $entry[ $id . '.2' ] : self::get( $field, 'checkboxLabel' );
			$prechecked = false; // Gravity Forms has no default tick for a consent field.
		} else {
			$ticked     = '' !== trim( (string) ( $entry[ $target['input'] ] ?? '' ) );
			$shown      = self::get( $target['choice'], 'text' );
			$prechecked = ! empty( self::get( $target['choice'], 'isSelected' ) );
		}

		$wording = self::plain( $shown );
		if ( '' === $wording ) {
			$wording = self::plain( self::get( $field, 'label' ) );
		}
		if ( '' === $wording ) {
			// Broadcaster requires the wording with the answer. Sending neither keeps the enquiry.
			return array();
		}

		$out = array(
			'marketing_opt_in'            => $ticked,
			'marketing_opt_in_wording'    => self::cut( $wording, self::WORDING_MAX ),
			'marketing_opt_in_prechecked' => $prechecked,
		);
		$reference = trim( (string) $reference );
		if ( '' !== $reference ) {
			$out['marketing_opt_in_reference'] = self::cut( $reference, self::REFERENCE_MAX );
		}
		return $out;
	}

	/**
	 * The admin URL of an entry, the proof Broadcaster links back to.
	 *
	 * @param string $admin_base admin_url( 'admin.php' ).
	 * @param int    $form_id    Form id.
	 * @param int    $entry_id   Entry id.
	 *
	 * @return string
	 */
	public static function entry_reference( $admin_base, $form_id, $entry_id ) {
		return $admin_base . '?page=gf_entries&view=entry&id=' . (int) $form_id . '&lid=' . (int) $entry_id;
	}

	/**
	 * The box a mapping points at: the field, plus the input id and choice for a checkbox. Null when unusable.
	 *
	 * @param string               $mapped Field or input id.
	 * @param array|\ArrayAccess[] $fields The form's fields.
	 *
	 * @return array{field:mixed,input:string,choice:mixed}|null
	 */
	private static function target( $mapped, $fields ) {
		$field = self::field( $mapped, $fields );
		if ( null === $field ) {
			return null;
		}
		$id   = (string) self::get( $field, 'id' );
		$type = self::input_type( $field );

		if ( 'consent' === $type ) {
			// The field, or its Consent input. Its Text and Description inputs are not the box.
			return ( $mapped === $id || $mapped === $id . '.1' ) ? array( 'field' => $field, 'input' => $id . '.1', 'choice' => null ) : null;
		}
		if ( 'checkbox' !== $type ) {
			return null;
		}

		$inputs  = array_values( (array) self::get( $field, 'inputs' ) );
		$choices = array_values( (array) self::get( $field, 'choices' ) );
		if ( $mapped === $id ) {
			// The whole field is a single box only when it has one choice.
			return 1 === count( $inputs ) && isset( $choices[0] ) ? array( 'field' => $field, 'input' => (string) self::get( $inputs[0], 'id' ), 'choice' => $choices[0] ) : null;
		}
		foreach ( $inputs as $i => $input ) {
			if ( (string) self::get( $input, 'id' ) === $mapped && isset( $choices[ $i ] ) ) {
				return array( 'field' => $field, 'input' => $mapped, 'choice' => $choices[ $i ] );
			}
		}
		return null;
	}

	/**
	 * The form field a field or input id belongs to.
	 *
	 * @param string               $mapped Field or input id.
	 * @param array|\ArrayAccess[] $fields The form's fields.
	 *
	 * @return mixed|null
	 */
	private static function field( $mapped, $fields ) {
		$field_id = (string) (int) $mapped;
		foreach ( (array) $fields as $field ) {
			if ( (string) self::get( $field, 'id' ) === $field_id ) {
				return $field;
			}
		}
		return null;
	}

	private static function input_type( $field ) {
		$input_type = (string) self::get( $field, 'inputType' );
		return '' !== $input_type ? $input_type : (string) self::get( $field, 'type' );
	}

	private static function get( $item, $key ) {
		if ( is_array( $item ) || $item instanceof \ArrayAccess ) {
			return isset( $item[ $key ] ) ? $item[ $key ] : null;
		}
		return null;
	}

	/** Words as the visitor read them: no markup, entities decoded, one line. */
	private static function plain( $text ) {
		$text = html_entity_decode( (string) preg_replace( '/<[^>]*>/', ' ', (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	private static function cut( $text, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max, 'UTF-8' ) : substr( $text, 0, $max );
	}
}

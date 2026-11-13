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
 * state in Gravity Forms. The mapped field is a standard Checkboxes field (one of its boxes, or the whole field when
 * it has just one), a Multiple Choice field set to checkboxes, or a Consent field.
 *
 * The saved input id alone is not reliable: Gravity Forms renumbers a Checkboxes field's inputs by position
 * whenever its choices change, and a Multiple Choice field reorders its choices. So the feed also saves the chosen
 * box's value and text, and the box is found by its value. A value that is gone, or shared by two boxes, sends
 * nothing rather than a guess.
 *
 * The answer is taken when the form is submitted and kept on the entry as a snapshot. A feed that runs later (an
 * entry marked Not spam, a delayed or payment feed) sends what the visitor saw then, not the form as it is now.
 *
 * Fields are read as arrays, which GF_Field supports, so the class can be tested without Gravity Forms.
 */
final class Marketing_Opt_In {

	const SETTING = 'marketing_opt_in_field';

	/** Feed meta: the chosen box's value and text, saved with the mapping. */
	const CHOICE_SETTING = 'marketing_opt_in_choice';

	/** Entry meta: the snapshot taken at submission, keyed by feed id. */
	const META_KEY = 'broadcaster_marketing_opt_in';

	/** Snapshot states. */
	const OK        = 'ok';
	const HIDDEN    = 'hidden';
	const NOT_FOUND = 'not_found';
	const NO_WORDS  = 'no_words';
	const BUNDLED   = 'bundled';

	/** Gravity Forms field types the mapping accepts. Product option, survey, quiz and poll checkboxes are not opt-ins. */
	const FIELD_TYPES = array( 'checkbox', 'multi_choice', 'consent' );

	/** Broadcaster refuses longer values with 422, which would lose the whole enquiry, so they are cut to fit. */
	const WORDING_MAX   = 255;
	const REFERENCE_MAX = 500;

	/**
	 * Why a mapping cannot be used, or null when it can (or nothing is mapped).
	 *
	 * @param string               $mapped Field or input id from the feed setting.
	 * @param array|\ArrayAccess[] $fields The form's fields.
	 *
	 * @return string|null
	 */
	public static function mapping_error( $mapped, $fields ) {
		$mapped = trim( (string) $mapped );
		if ( '' === $mapped ) {
			return null;
		}
		$field = self::field( $mapped, $fields );
		if ( null === $field || ! self::is_opt_in_type( $field ) ) {
			return __( 'Choose a box from a Checkboxes, Multiple Choice or Consent field as the marketing opt-in.', 'broadcaster-auto-responder-for-gravity-forms' );
		}
		if ( self::unseen( $field ) ) {
			return __( 'Visitors never see this field (its Visibility is Administrative or Hidden), so it cannot hold their answer. Choose a field they see.', 'broadcaster-auto-responder-for-gravity-forms' );
		}
		if ( self::selects_all( $field ) ) {
			return __( 'This field has Select All turned on. Ticking every box at once is not a specific agreement to marketing, so turn Select All off in the form editor or choose a box in another field.', 'broadcaster-auto-responder-for-gravity-forms' );
		}
		if ( 'consent' === self::input_type( $field ) ) {
			$id = (string) self::get( $field, 'id' );
			return ( $mapped === $id || $mapped === $id . '.1' ) ? null : __( 'Choose the consent field itself, not its text or description.', 'broadcaster-auto-responder-for-gravity-forms' );
		}
		$choice = self::choice_at( $mapped, $field );
		if ( null === $choice ) {
			if ( false === strpos( $mapped, '.' ) ) {
				return __( 'This checkbox field has more than one box. Choose the single box that is the marketing opt-in.', 'broadcaster-auto-responder-for-gravity-forms' );
			}
			return __( 'Choose a box from a Checkboxes, Multiple Choice or Consent field as the marketing opt-in.', 'broadcaster-auto-responder-for-gravity-forms' );
		}
		$value = (string) self::get( $choice, 'value' );
		if ( '' === $value || 1 !== count( self::choices_with_value( $field, $value ) ) ) {
			return __( 'This box has no value, or the same value as another box in the field, so its answer cannot be told apart. Give it its own value in the form editor.', 'broadcaster-auto-responder-for-gravity-forms' );
		}
		return null;
	}

	/**
	 * The chosen box's value and text, saved with the mapping. Empty for a consent field or nothing usable.
	 *
	 * @param string               $mapped Field or input id from the feed setting.
	 * @param array|\ArrayAccess[] $fields The form's fields.
	 *
	 * @return array<string,string>
	 */
	public static function choice_setting( $mapped, $fields ) {
		$mapped = trim( (string) $mapped );
		if ( '' === $mapped || null !== self::mapping_error( $mapped, $fields ) ) {
			return array();
		}
		$field = self::field( $mapped, $fields );
		if ( 'consent' === self::input_type( $field ) ) {
			return array();
		}
		$choice = self::choice_at( $mapped, $field );
		return array(
			'value' => (string) self::get( $choice, 'value' ),
			'text'  => (string) self::get( $choice, 'text' ),
		);
	}

	/**
	 * Why the saved mapping no longer points at the box chosen when it was saved, or null when it still does.
	 *
	 * Shown on the feed page, and used to refuse a save that would quietly bind the old input id to another box.
	 *
	 * @param string               $mapped Field or input id from the feed setting.
	 * @param mixed                $saved  The choice saved with the mapping.
	 * @param array|\ArrayAccess[] $fields The form's fields.
	 *
	 * @return string|null
	 */
	public static function moved_notice( $mapped, $saved, $fields ) {
		$mapped = trim( (string) $mapped );
		$field  = '' === $mapped ? null : self::field( $mapped, $fields );
		if ( null === $field || ! self::is_opt_in_type( $field ) || 'consent' === self::input_type( $field ) ) {
			return null;
		}
		if ( self::selects_all( $field ) ) {
			return __( 'The field with the marketing opt-in box now has Select All turned on, so no opt-in is sent: ticking every box at once is not a specific agreement to marketing. Turn Select All off in the form editor.', 'broadcaster-auto-responder-for-gravity-forms' );
		}
		$value = (string) self::get( $saved, 'value' );
		if ( '' === $value ) {
			return __( 'Choose the marketing opt-in box again and save this feed: the box it uses was not saved with it, so no opt-in is sent.', 'broadcaster-auto-responder-for-gravity-forms' );
		}
		if ( self::in_place( $mapped, $field, $value ) ) {
			$was = self::plain( self::get( $saved, 'text' ) );
			if ( '' === $was || self::plain( self::get( self::choice_at( $mapped, $field ), 'text' ) ) === $was ) {
				return null;
			}
			// The record holds what the visitor saw either way; this only tells the admin.
			return sprintf(
				/* translators: %s: the words beside the box when the feed was saved. */
				__( 'The words beside the marketing opt-in box this feed uses have changed in the form editor (they were "%s"). Its answer is still sent, with the words visitors now see. Check them, then save this feed.', 'broadcaster-auto-responder-for-gravity-forms' ),
				$was
			);
		}
		if ( 1 === count( self::choices_with_value( $field, $value ) ) ) {
			return sprintf(
				/* translators: %s: the words beside the box when the feed was saved. */
				__( 'The marketing opt-in box this feed uses ("%s") has moved in the form editor. Its answer is still sent, but choose the box again here and save this feed.', 'broadcaster-auto-responder-for-gravity-forms' ),
				self::plain( self::get( $saved, 'text' ) )
			);
		}
		return sprintf(
			/* translators: %s: the words beside the box when the feed was saved. */
			__( 'The marketing opt-in box this feed uses ("%s") has been removed or changed in the form editor, so no opt-in is sent. Choose the box again and save this feed. To use whichever box is now in its old place, clear this setting and save first.', 'broadcaster-auto-responder-for-gravity-forms' ),
			self::plain( self::get( $saved, 'text' ) )
		);
	}

	/**
	 * Why saving this mapping must be refused because it would bind the saved input id to a different box.
	 *
	 * A feed with no box saved with it is let through: saving it records the box now chosen.
	 *
	 * @param string               $value    The mapping being saved.
	 * @param array                $previous The feed's settings before this save.
	 * @param array|\ArrayAccess[] $fields   The form's fields.
	 *
	 * @return string|null
	 */
	public static function resave_error( $value, $previous, $fields ) {
		$value    = trim( (string) $value );
		$previous = is_array( $previous ) ? $previous : array();
		$saved    = $previous[ self::CHOICE_SETTING ] ?? array();
		if ( '' === $value || trim( (string) ( $previous[ self::SETTING ] ?? '' ) ) !== $value || '' === (string) self::get( $saved, 'value' ) ) {
			return null;
		}
		$field = self::field( $value, $fields );
		if ( null !== $field && self::in_place( $value, $field, (string) self::get( $saved, 'value' ) ) ) {
			// Same box, perhaps with new words: saving records them.
			return null;
		}
		return self::moved_notice( $value, $saved, $fields );
	}

	/**
	 * The answer as the visitor gave it, taken when the form is submitted.
	 *
	 * Prechecked leans towards true: a box the business set to start ticked, or one in a field that allows dynamic
	 * population, counts as started ticked. Claiming an active tick that was not one is the worse error in a
	 * consent record.
	 *
	 * @param string               $mapped Field or input id from the feed setting.
	 * @param mixed                $saved  The choice saved with the mapping.
	 * @param array|\ArrayAccess[] $fields The form's fields as submitted.
	 * @param array|\ArrayAccess[] $shown  The form's fields as rendered (gform_pre_render), or $fields when unknown.
	 * @param array                $entry  The entry.
	 * @param callable             $hidden Called with the field: true when conditional logic hid it.
	 *
	 * @return array<string,bool|string>
	 */
	public static function snapshot( $mapped, $saved, $fields, $shown, array $entry, $hidden ) {
		$mapped = trim( (string) $mapped );
		$field  = '' === $mapped ? null : self::field( $mapped, $fields );
		if ( null === $field || ! self::is_opt_in_type( $field ) ) {
			return array( 'state' => self::NOT_FOUND );
		}
		if ( self::unseen( $field ) || call_user_func( $hidden, $field ) ) {
			// The visitor never saw the box, so there is no answer to send.
			return array( 'state' => self::HIDDEN );
		}
		$id          = (string) self::get( $field, 'id' );
		$shown_field = self::field( $id, $shown );
		if ( null === $shown_field ) {
			$shown_field = $field;
		}
		$prepopulated = ! empty( self::get( $field, 'allowsPrepopulate' ) ) || ! empty( self::get( $shown_field, 'allowsPrepopulate' ) );

		if ( 'consent' === self::input_type( $field ) ) {
			if ( null !== self::mapping_error( $mapped, $fields ) ) {
				return array( 'state' => self::NOT_FOUND );
			}
			$ticked = '1' === (string) ( $entry[ $id . '.1' ] ?? '' );
			// Ticked, the entry keeps the checkbox text the visitor saw (Gravity Forms refuses a changed one);
			// unticked, the field's own is what was shown.
			$words      = $ticked && '' !== trim( (string) ( $entry[ $id . '.2' ] ?? '' ) ) ? $entry[ $id . '.2' ] : self::get( $shown_field, 'checkboxLabel' );
			$choices    = array_values( (array) self::get( $shown_field, 'choices' ) );
			$prechecked = $prepopulated || ( isset( $choices[0] ) && ! empty( self::get( $choices[0], 'isSelected' ) ) );
		} else {
			if ( self::selects_all( $field ) || self::selects_all( $shown_field ) ) {
				return array( 'state' => self::BUNDLED );
			}
			$value = (string) self::get( $saved, 'value' );
			if ( '' === $value ) {
				return array( 'state' => self::NOT_FOUND );
			}
			$matches = self::choices_with_value( $shown_field, $value );
			if ( 1 !== count( $matches ) ) {
				$matches = self::choices_with_value( $field, $value );
			}
			if ( 1 !== count( $matches ) ) {
				return array( 'state' => self::NOT_FOUND );
			}
			$choice = $matches[0];
			// A ticked box stores its choice's value in the entry, whatever input number it now has.
			$ticked = false;
			foreach ( (array) self::get( $field, 'inputs' ) as $input ) {
				if ( (string) ( $entry[ (string) self::get( $input, 'id' ) ] ?? '' ) === $value ) {
					$ticked = true;
					break;
				}
			}
			$words      = self::get( $choice, 'text' );
			$prechecked = $prepopulated || ! empty( self::get( $choice, 'isSelected' ) );
		}

		$wording = self::plain( $words );
		if ( '' === $wording ) {
			$wording = self::plain( self::get( $shown_field, 'label' ) );
		}
		if ( '' === $wording ) {
			// Broadcaster requires the wording with the answer. Sending neither keeps the enquiry.
			return array( 'state' => self::NO_WORDS );
		}
		return array(
			'state'      => self::OK,
			'ticked'     => $ticked,
			'wording'    => self::cut( $wording, self::WORDING_MAX ),
			'prechecked' => $prechecked,
		);
	}

	/**
	 * The fields to add to the incoming-message payload, from the snapshot only: empty unless it holds an answer.
	 *
	 * @param mixed  $snapshot  The snapshot taken at submission, or null when there is none.
	 * @param string $reference Where the answer can be found (the entry's admin URL).
	 *
	 * @return array<string,bool|string>
	 */
	public static function payload_fields( $snapshot, $reference ) {
		if ( ! is_array( $snapshot ) || self::OK !== ( $snapshot['state'] ?? '' ) || ! is_bool( $snapshot['ticked'] ?? null ) ) {
			return array();
		}
		// Already plain text from snapshot(): cleaning it again would decode entities the visitor saw as text.
		$wording = self::cut( trim( (string) ( $snapshot['wording'] ?? '' ) ), self::WORDING_MAX );
		if ( '' === $wording ) {
			return array();
		}
		$out       = array(
			'marketing_opt_in'            => $snapshot['ticked'],
			'marketing_opt_in_wording'    => $wording,
			'marketing_opt_in_prechecked' => ! empty( $snapshot['prechecked'] ),
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
	 * Words as the visitor read them: no markup, entities decoded, one line. A "<" that starts no tag is kept.
	 *
	 * @param mixed $text Label text, possibly with markup.
	 *
	 * @return string
	 */
	public static function plain( $text ) {
		$text = (string) preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', ' ', (string) $text );
		$text = (string) preg_replace( '#<(?:/?[a-zA-Z][^>]*|!--.*?--|![^>]*|\?[^>]*)>#s', ' ', $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * The choice at a mapped checkbox input, or the only choice when the whole field is mapped. Null when none.
	 *
	 * Gravity Forms 2.9+ Multiple Choice fields link an input to its choice by key, and moving a choice in the form
	 * editor reorders the choices but not the inputs. A keyed input with no matching choice gets none rather than a
	 * guess. Classic Checkboxes fields have no keys, and their inputs follow choice order.
	 *
	 * @param string $mapped Field or input id.
	 * @param mixed  $field  The field.
	 *
	 * @return mixed|null
	 */
	private static function choice_at( $mapped, $field ) {
		$inputs  = array_values( (array) self::get( $field, 'inputs' ) );
		$choices = array_values( (array) self::get( $field, 'choices' ) );
		if ( $mapped === (string) self::get( $field, 'id' ) ) {
			if ( 1 !== count( $inputs ) || 1 !== count( $choices ) ) {
				return null;
			}
			return self::choice_for( $inputs[0], 0, $choices );
		}
		foreach ( $inputs as $i => $input ) {
			if ( (string) self::get( $input, 'id' ) === $mapped ) {
				return self::choice_for( $input, $i, $choices );
			}
		}
		return null;
	}

	private static function choice_for( $input, $index, array $choices ) {
		$key = (string) self::get( $input, 'key' );
		if ( '' !== $key ) {
			foreach ( $choices as $choice ) {
				if ( (string) self::get( $choice, 'key' ) === $key ) {
					return $choice;
				}
			}
			return null;
		}
		return isset( $choices[ $index ] ) ? $choices[ $index ] : null;
	}

	private static function choices_with_value( $field, $value ) {
		$out = array();
		foreach ( (array) self::get( $field, 'choices' ) as $choice ) {
			if ( (string) self::get( $choice, 'value' ) === $value ) {
				$out[] = $choice;
			}
		}
		return $out;
	}

	/**
	 * Whether a Checkboxes field offers Select All, which ticks every box at once.
	 *
	 * @param array|\ArrayAccess $field Field.
	 *
	 * @return bool
	 */
	private static function selects_all( $field ) {
		return ! empty( self::get( $field, 'enableSelectAll' ) );
	}

	/**
	 * Whether the box at the mapped input still holds the saved value.
	 *
	 * @param string             $mapped Input id.
	 * @param array|\ArrayAccess $field  Field.
	 * @param string             $value  Saved value.
	 *
	 * @return bool
	 */
	private static function in_place( $mapped, $field, $value ) {
		$choice = self::choice_at( $mapped, $field );
		return null !== $choice && (string) self::get( $choice, 'value' ) === $value;
	}

	/**
	 * A copy of the form whose fields can be changed without touching the form being submitted.
	 *
	 * Gravity Forms fields are objects, so gform_pre_render callbacks that change one in place would otherwise
	 * reach notifications and other feeds in the same request.
	 *
	 * @param mixed $form The form.
	 *
	 * @return mixed
	 */
	public static function form_copy( $form ) {
		if ( ! is_array( $form ) || ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $form;
		}
		foreach ( $form['fields'] as $key => $field ) {
			if ( is_object( $field ) ) {
				$form['fields'][ $key ] = clone $field;
			}
		}
		return $form;
	}

	/**
	 * Whether visitors never see the field, whatever its conditional logic.
	 *
	 * @param array|\ArrayAccess $field Field.
	 *
	 * @return bool
	 */
	private static function unseen( $field ) {
		// Gravity Forms saves an Administrative field's default, and a Hidden one submits its preset ticks.
		return in_array( (string) self::get( $field, 'visibility' ), array( 'administrative', 'hidden' ), true );
	}

	/**
	 * Whether the field is a Checkboxes, Multiple Choice (checkbox) or Consent field.
	 *
	 * @param array|\ArrayAccess $field Field.
	 *
	 * @return bool
	 */
	private static function is_opt_in_type( $field ) {
		$type = (string) self::get( $field, 'type' );
		if ( ! in_array( $type, self::FIELD_TYPES, true ) ) {
			return false;
		}
		$input_type = self::input_type( $field );
		return 'consent' === $type ? 'consent' === $input_type : 'checkbox' === $input_type;
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

	private static function cut( $text, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max, 'UTF-8' ) : substr( $text, 0, $max );
	}
}

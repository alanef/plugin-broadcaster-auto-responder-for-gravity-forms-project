<?php
/**
 * BRO-2014 wp-env probe: the marketing opt-in on real Gravity Forms objects. Not part of the unit suite.
 *
 * Run: npx wp-env run cli wp eval-file /var/www/html/tests/probes/marketing-opt-in-probe.php
 * (with tests/ mounted, or the file copied into the container). HTTP to Broadcaster is faked; the phone
 * number is a fixture (+447700900123, Ofcom drama range). Each line prints PASS or FAIL.
 *
 * @package BroadcasterGF
 */

// phpcs:ignoreFile -- developer probe, not shipped (tests/ is export-ignored).

use BroadcasterGF\GF\Marketing_Opt_In;

if ( ! defined( 'BROADCASTER_API_KEY' ) ) {
	define( 'BROADCASTER_API_KEY', 'probe-key-not-real' );
}
$GLOBALS['bro_bodies'] = array();
$GLOBALS['bro_fails']  = 0;
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( false !== strpos( $url, 'messages/incoming' ) ) {
			$GLOBALS['bro_bodies'][] = json_decode( $args['body'], true );
			return array( 'headers' => array(), 'body' => '{"success":true,"data":{}}', 'response' => array( 'code' => 201, 'message' => 'Created' ), 'cookies' => array(), 'filename' => null );
		}
		return array( 'headers' => array(), 'body' => '{"success":true,"data":{"sendable":true}}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	},
	10,
	3
);

function bro_optin( $body ) {
	$keys = array( 'marketing_opt_in', 'marketing_opt_in_wording', 'marketing_opt_in_prechecked' );
	return array_intersect_key( (array) $body, array_flip( $keys ) );
}
function bro_check( $name, $got, $want ) {
	$ok = $got === $want;
	if ( ! $ok ) {
		++$GLOBALS['bro_fails'];
	}
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . ': ' . wp_json_encode( $got, JSON_UNESCAPED_UNICODE ) . ( $ok ? '' : '  want ' . wp_json_encode( $want, JSON_UNESCAPED_UNICODE ) ) . "\n";
}
function bro_reset() {
	$_POST = array();
	GFCache::flush();
	GFFormDisplay::$submission = array();
	if ( method_exists( 'GFFormDisplay', 'flush_cached_forms' ) ) {
		GFFormDisplay::flush_cached_forms();
	}
}
function bro_submit( $form_id, $values ) {
	bro_reset();
	$form  = GFAPI::get_form( $form_id );
	$state = array( 'state_' . $form_id => GFFormDisplay::get_state( $form, array() ) );
	$n     = count( $GLOBALS['bro_bodies'] );
	$r     = GFAPI::submit_form( $form_id, array( 'input_2' => '+447700900123' ) + $values + $state );
	return array( $r, array_slice( $GLOBALS['bro_bodies'], $n ) );
}
function bro_set_choices( $form_id, $field_id, $choices ) {
	$form = GFAPI::get_form( $form_id );
	foreach ( $form['fields'] as $field ) {
		if ( (int) $field->id === $field_id ) {
			$inputs = array();
			foreach ( array_values( $choices ) as $i => $c ) {
				$inputs[] = array( 'id' => $field_id . '.' . ( $i + 1 ), 'label' => $c['text'] );
			}
			$field->choices = $choices;
			$field->inputs  = $inputs; // As the form editor renumbers them, by position.
		}
	}
	GFAPI::update_form( $form );
	bro_reset();
}

$addon   = Broadcaster_GF_FeedAddOn::get_instance();
$slug    = 'broadcaster-auto-responder-for-gravity-forms';
$form_id = GFAPI::add_form(
	array(
		'title'  => 'BRO-2014 probe ' . time(),
		'fields' => array(
			array( 'id' => 2, 'type' => 'phone', 'label' => 'Phone', 'phoneFormat' => 'international' ),
			array( 'id' => 3, 'type' => 'checkbox', 'label' => 'Keep in touch', 'choices' => array( array( 'text' => 'Send me offers', 'value' => 'offers' ) ), 'inputs' => array( array( 'id' => '3.1', 'label' => 'Send me offers' ) ) ),
			array( 'id' => 4, 'type' => 'consent', 'inputType' => 'consent', 'label' => 'Marketing', 'checkboxLabel' => 'I agree to marketing', 'description' => 'Fixture terms', 'choices' => array( array( 'text' => 'Checked', 'value' => '1', 'isSelected' => false ) ), 'inputs' => array( array( 'id' => '4.1', 'label' => 'Consent' ), array( 'id' => '4.2', 'label' => 'Text', 'isHidden' => true ), array( 'id' => '4.3', 'label' => 'Description', 'isHidden' => true ) ) ),
			array( 'id' => 5, 'type' => 'text', 'label' => 'Show offers?' ),
			array(
				'id'               => 6,
				'type'             => 'checkbox',
				'label'            => 'Offers',
				'allowsPrepopulate' => true,
				'choices'          => array( array( 'text' => 'Yes, offers', 'value' => 'yes' ) ),
				'inputs'           => array( array( 'id' => '6.1', 'label' => 'Yes, offers', 'name' => 'offers' ) ),
				'conditionalLogic' => array( 'actionType' => 'show', 'logicType' => 'all', 'rules' => array( array( 'fieldId' => '5', 'operator' => 'is', 'value' => 'show' ) ) ),
			),
			array( 'id' => 7, 'type' => 'option', 'inputType' => 'checkbox', 'label' => 'Extras', 'productField' => 2, 'choices' => array( array( 'text' => 'Gift wrap', 'value' => 'gift', 'price' => '1' ) ), 'inputs' => array( array( 'id' => '7.1', 'label' => 'Gift wrap' ) ) ),
		),
	)
);
$form   = GFAPI::get_form( $form_id );
$fields = $form['fields'];
echo 'Gravity Forms ' . GFForms::$version . ', field class ' . get_class( $fields[1] ) . "\n";

// Feed save stores the chosen box's value and text (through the add-on's save_feed_settings).
$feed_id = $addon->save_feed_settings( 0, $form_id, array( 'feedName' => 'probe', 'recipient_field' => '2', 'message_text' => 'probe', 'feed_condition' => '', Marketing_Opt_In::SETTING => '3.1' ) );
$feed    = GFAPI::get_feed( $feed_id );
bro_check( 'S1 feed save stores the box', $feed['meta'][ Marketing_Opt_In::CHOICE_SETTING ], array( 'value' => 'offers', 'text' => 'Send me offers' ) );

// Finding 5: only checkbox, multiple choice (checkbox) and consent fields.
bro_check( 'F5 product option refused', null !== Marketing_Opt_In::mapping_error( '7.1', $fields ), true );
bro_check( 'F5 standard checkbox accepted', Marketing_Opt_In::mapping_error( '3.1', $fields ), null );
bro_check( 'F5 consent accepted', Marketing_Opt_In::mapping_error( '4', $fields ), null );

// Baseline: ticked and unticked.
list( $r, $b ) = bro_submit( $form_id, array( 'input_3_1' => 'offers' ) );
bro_check( 'B1 ticked', bro_optin( $b[0] ?? null ), array( 'marketing_opt_in' => true, 'marketing_opt_in_wording' => 'Send me offers', 'marketing_opt_in_prechecked' => false ) );
bro_check( 'B1 snapshot stored on the entry', (string) rgar( (array) gform_get_meta( $r['entry_id'], Marketing_Opt_In::META_KEY ), (string) $feed_id )['state'], 'ok' );
list( , $b ) = bro_submit( $form_id, array() );
bro_check( 'B2 unticked', bro_optin( $b[0] ?? null ), array( 'marketing_opt_in' => false, 'marketing_opt_in_wording' => 'Send me offers', 'marketing_opt_in_prechecked' => false ) );

// Finding 2a: spam at submission, relabelled, then marked Not spam: the words the visitor saw.
$spam = function () {
	return true;
};
add_filter( 'gform_entry_is_spam', $spam );
list( $r, $b ) = bro_submit( $form_id, array( 'input_3_1' => 'offers' ) );
remove_filter( 'gform_entry_is_spam', $spam );
bro_check( 'F2a spam submission sends', count( $b ), 0 );
bro_set_choices( $form_id, 3, array( array( 'text' => 'Send me offers and partner promotions', 'value' => 'offers' ) ) );
$n = count( $GLOBALS['bro_bodies'] );
GFAPI::update_entry_property( $r['entry_id'], 'status', 'active' );
bro_check( 'F2a after Not spam', bro_optin( $GLOBALS['bro_bodies'][ $n ] ?? null ), array( 'marketing_opt_in' => true, 'marketing_opt_in_wording' => 'Send me offers', 'marketing_opt_in_prechecked' => false ) );
bro_set_choices( $form_id, 3, array( array( 'text' => 'Send me offers', 'value' => 'offers' ) ) );

// Finding 2b and 3: words and a tick put there by gform_pre_render (a translation plugin, pre-ticking code).
$fr = function ( $f ) {
	foreach ( $f['fields'] as $fl ) {
		if ( 3 === (int) $fl->id ) {
			$fl->choices = array( array( 'text' => 'Envoyez-moi des offres', 'value' => 'offers', 'isSelected' => true ) );
		}
	}
	return $f;
};
$seen = function ( $entry, $f ) {
	foreach ( $f['fields'] as $fl ) {
		if ( 3 === (int) $fl->id ) {
			$GLOBALS['bro_after'] = $fl->choices[0]['text'];
		}
	}
	return $entry;
};
add_filter( 'gform_pre_render', $fr );
add_filter( 'gform_entry_post_save', $seen, 1, 2 );
list( , $b ) = bro_submit( $form_id, array( 'input_3_1' => 'offers' ) );
remove_filter( 'gform_pre_render', $fr );
remove_filter( 'gform_entry_post_save', $seen, 1 );
bro_check( 'D1 pre_render changes stay off the submitted form (notifications, other feeds)', $GLOBALS['bro_after'] ?? null, 'Send me offers' );
bro_check( 'F2b/F3 pre_render words and tick', bro_optin( $b[0] ?? null ), array( 'marketing_opt_in' => true, 'marketing_opt_in_wording' => 'Envoyez-moi des offres', 'marketing_opt_in_prechecked' => true ) );

// Select All ticks every box at once: refused at save, and nothing is sent if it is turned on later.
$f = GFAPI::get_form( $form_id );
foreach ( $f['fields'] as $fl ) {
	if ( 3 === (int) $fl->id ) {
		$fl->enableSelectAll = true;
	}
}
GFAPI::update_form( $f );
bro_check( 'A1 Select All refused at save', null !== Marketing_Opt_In::mapping_error( '3.1', GFAPI::get_form( $form_id )['fields'] ), true );
list( , $b ) = bro_submit( $form_id, array( 'input_3_1' => 'offers' ) );
bro_check( 'A1 Select All: message sent, no opt-in', array( count( $b ), bro_optin( $b[0] ?? null ) ), array( 1, array() ) );
$f = GFAPI::get_form( $form_id );
foreach ( $f['fields'] as $fl ) {
	if ( 3 === (int) $fl->id ) {
		$fl->enableSelectAll = false;
	}
}
GFAPI::update_form( $f );

// Finding 2: an entry with no snapshot (added by the API, not submitted) sends no opt-in.
$entry_id = GFAPI::add_entry( array( 'form_id' => $form_id, '2' => '+447700900123', '3.1' => 'offers' ) );
$n        = count( $GLOBALS['bro_bodies'] );
$addon->process_feed( GFAPI::get_feed( $feed_id ), GFAPI::get_entry( $entry_id ), GFAPI::get_form( $form_id ) );
$sent = array_slice( $GLOBALS['bro_bodies'], $n );
bro_check( 'F2 no snapshot: message sent, no opt-in', array( count( $sent ), bro_optin( $sent[0] ?? null ) ), array( 1, array() ) );

// Finding 1: a terms box added above; the editor renumbers, so 3.1 is now the terms box.
bro_set_choices( $form_id, 3, array( array( 'text' => 'I accept the terms', 'value' => 'terms' ), array( 'text' => 'Send me offers', 'value' => 'offers' ) ) );
list( , $b ) = bro_submit( $form_id, array( 'input_3_1' => 'terms' ) );
bro_check( 'F1 moved: terms only ticked', bro_optin( $b[0] ?? null ), array( 'marketing_opt_in' => false, 'marketing_opt_in_wording' => 'Send me offers', 'marketing_opt_in_prechecked' => false ) );
list( , $b ) = bro_submit( $form_id, array( 'input_3_2' => 'offers' ) );
bro_check( 'F1 moved: offers ticked', bro_optin( $b[0] ?? null ), array( 'marketing_opt_in' => true, 'marketing_opt_in_wording' => 'Send me offers', 'marketing_opt_in_prechecked' => false ) );
$fields = GFAPI::get_form( $form_id )['fields'];
bro_check( 'F1 feed page notice shown', null !== Marketing_Opt_In::moved_notice( '3.1', GFAPI::get_feed( $feed_id )['meta'][ Marketing_Opt_In::CHOICE_SETTING ], $fields ), true );

// Finding 1: the box removed: nothing sent, a note on the entry.
bro_set_choices( $form_id, 3, array( array( 'text' => 'I accept the terms', 'value' => 'terms' ) ) );
list( $r, $b ) = bro_submit( $form_id, array( 'input_3_1' => 'terms' ) );
$notes = array_map(
	function ( $note ) {
		return $note->value;
	},
	(array) GFAPI::get_notes( array( 'entry_id' => $r['entry_id'] ) )
);
bro_check( 'F1 removed: message sent, no opt-in', array( count( $b ), bro_optin( $b[0] ?? null ) ), array( 1, array() ) );
bro_check( 'F1 removed: note asks for a re-save', 1 === count( preg_grep( '/choose the Marketing opt-in field again/', $notes ) ), true );

// Finding 3: dynamic population enabled; finding 4: hidden by conditional logic.
$m                                    = GFAPI::get_feed( $feed_id )['meta'];
$m[ Marketing_Opt_In::SETTING ]        = '6.1';
$m[ Marketing_Opt_In::CHOICE_SETTING ] = array( 'value' => 'yes', 'text' => 'Yes, offers' );
GFAPI::update_feed( $feed_id, $m, $form_id );
list( , $b ) = bro_submit( $form_id, array( 'input_5' => 'show', 'input_6_1' => 'yes' ) );
bro_check( 'F3 dynamic population: may have started ticked', bro_optin( $b[0] ?? null ), array( 'marketing_opt_in' => true, 'marketing_opt_in_wording' => 'Yes, offers', 'marketing_opt_in_prechecked' => true ) );
list( , $b ) = bro_submit( $form_id, array( 'input_5' => 'hide', 'input_6_1' => 'yes' ) );
bro_check( 'F4 hidden: message sent, no opt-in', array( count( $b ), bro_optin( $b[0] ?? null ) ), array( 1, array() ) );

// Two feeds on one form, each with its own box: each sends its own answer.
$feed2 = $addon->save_feed_settings( 0, $form_id, array( 'feedName' => 'probe 2', 'recipient_field' => '2', 'message_text' => 'probe', 'feed_condition' => '', Marketing_Opt_In::SETTING => '6.1' ) );
$m[ Marketing_Opt_In::SETTING ]        = '3.1';
$m[ Marketing_Opt_In::CHOICE_SETTING ] = array( 'value' => 'terms', 'text' => 'I accept the terms' );
GFAPI::update_feed( $feed_id, $m, $form_id );
list( , $b ) = bro_submit( $form_id, array( 'input_3_1' => 'terms', 'input_5' => 'show' ) );
$got = array_map( 'bro_optin', $b );
usort(
	$got,
	function ( $x, $y ) {
		return strcmp( (string) ( $x['marketing_opt_in_wording'] ?? '' ), (string) ( $y['marketing_opt_in_wording'] ?? '' ) );
	}
);
bro_check(
	'M1 two feeds, two answers',
	$got,
	array(
		array( 'marketing_opt_in' => true, 'marketing_opt_in_wording' => 'I accept the terms', 'marketing_opt_in_prechecked' => false ),
		array( 'marketing_opt_in' => false, 'marketing_opt_in_wording' => 'Yes, offers', 'marketing_opt_in_prechecked' => true ),
	)
);

// A field visitors never see, set to start ticked: Gravity Forms stores the tick; nothing is sent.
GFAPI::delete_feed( $feed_id );
$f = GFAPI::get_form( $form_id );
foreach ( $f['fields'] as $fl ) {
	if ( 6 === (int) $fl->id ) {
		$fl->visibility       = 'administrative';
		$fl->conditionalLogic = null;
		$fl->choices          = array( array( 'text' => 'Yes, offers', 'value' => 'yes', 'isSelected' => true ) );
	}
}
GFAPI::update_form( $f );
list( $r, $b ) = bro_submit( $form_id, array() );
bro_check( 'V1 administrative field: tick stored, message sent, no opt-in', array( (string) rgar( GFAPI::get_entry( $r['entry_id'] ), '6.1' ), count( $b ), bro_optin( $b[0] ?? null ) ), array( 'yes', 1, array() ) );
bro_check( 'V1 administrative field refused at save', null !== Marketing_Opt_In::mapping_error( '6.1', GFAPI::get_form( $form_id )['fields'] ), true );

// Consent unchanged: the entry's .2 is the text shown. Last: a refused consent submission leaves state in the
// request that fails later submissions of this form.
GFAPI::delete_feed( $feed2 );
$addon->save_feed_settings( 0, $form_id, array( 'feedName' => 'probe consent', 'recipient_field' => '2', 'message_text' => 'probe', 'feed_condition' => '', Marketing_Opt_In::SETTING => '4' ) );
bro_reset();
$html = GFFormDisplay::get_form( $form_id, false, false );
preg_match( "/name='input_4\\.3' value='([^']*)'/", $html, $rev );
list( , $b ) = bro_submit( $form_id, array( 'input_4_1' => '1', 'input_4_2' => 'I agree to marketing', 'input_4_3' => $rev[1] ?? '' ) );
bro_check( 'C1 consent ticked', bro_optin( $b[0] ?? null ), array( 'marketing_opt_in' => true, 'marketing_opt_in_wording' => 'I agree to marketing', 'marketing_opt_in_prechecked' => false ) );
list( $r ) = bro_submit( $form_id, array( 'input_4_1' => '1', 'input_4_2' => 'I agree to NOTHING', 'input_4_3' => $rev[1] ?? '' ) );
bro_check( 'C2 tampered consent text refused by Gravity Forms', empty( $r['entry_id'] ), true );

GFAPI::delete_form( $form_id );
echo $GLOBALS['bro_fails'] ? "FAILURES: {$GLOBALS['bro_fails']}\n" : "ALL PASS\n";

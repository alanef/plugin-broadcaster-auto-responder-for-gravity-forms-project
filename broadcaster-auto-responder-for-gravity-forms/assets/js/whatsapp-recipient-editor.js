/**
 * Form-editor glue for the WhatsApp Recipient field.
 *
 * Replaces the inline <script> block previously printed on gform_editor_js, so
 * the plugin's editor JavaScript is included through wp_enqueue_script per the
 * WordPress.org guidelines. Dynamic values (the flag-conditional default label
 * and helper text) are injected ahead of this file with wp_add_inline_script
 * as window.BroadcasterGFRecipientEditor.
 *
 * @package BroadcasterGF
 */

( function ( $ ) {
	'use strict';

	var cfg = window.BroadcasterGFRecipientEditor || {};

	// Called by Gravity Forms when a whatsapp_recipient field is dropped onto
	// the form. Sets sensible defaults; the designer can still edit them via
	// the standard Label / Description / Size settings.
	window.SetDefaultValues_whatsapp_recipient = function ( field ) {
		field.label       = cfg.defaultLabel || '';
		field.description = cfg.defaultDescription || '';
		field.size        = 'large';
		return field;
	};

	if ( ! $ ) {
		return;
	}

	// Populate the per-field Default Country picker when the sidebar opens for
	// a whatsapp_recipient field.
	$( document ).on( 'gform_load_field_settings', function ( event, field ) {
		if ( field && 'whatsapp_recipient' === field.type ) {
			$( '#broadcastergf_default_phone_country' ).val( field.defaultPhoneCountry || '' );
		}
	} );

	// Persist the per-field override. Replaces the inline onchange handler that
	// used to live on the <select> element.
	$( document ).on( 'change', '#broadcastergf_default_phone_country', function () {
		if ( 'function' === typeof window.SetFieldProperty ) {
			window.SetFieldProperty( 'defaultPhoneCountry', this.value );
		}
	} );
} )( window.jQuery );

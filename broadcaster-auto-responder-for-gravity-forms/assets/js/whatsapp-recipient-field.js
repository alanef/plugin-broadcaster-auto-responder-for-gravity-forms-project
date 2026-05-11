/**
 * Public-form Submitter Validator for the WhatsApp Recipient field.
 *
 * Mirrors the PHP-side Recipient_Discriminator + Phone_Validator +
 * Username_Validator so what the form accepts is exactly what the API
 * will accept. Hand-rolled (no libphonenumber-js dependency) per BRO-902
 * Q1: Broadcaster delivery is async via the GF feed, so client-side
 * validation has to actually validate — and a small per-region rule set
 * keeps the public-form bundle ~2 KB.
 *
 * Vanilla JS, no jQuery dependency. Hooks the form's submit event,
 * validates any whatsapp_recipient fields on the form, and prevents
 * submission with an inline error if any are invalid.
 *
 * @package BroadcasterGF
 */

( function () {
	'use strict';

	// Per-region phone rules are served from PHP via wp_localize_script
	// (`BroadcasterGFRecipientFieldL10n.regions`) so a single PHP source
	// of truth (Phone_Validator::js_rules) drives both PHP and JS
	// validation. The fallback to {} keeps the file useful in cases
	// where it's loaded without the L10N (no field on the page) — every
	// region lookup just returns null and the validator falls back to
	// E.164-shape only on `+`-prefixed input.
	function getRegionRule( region ) {
		var l10n = window.BroadcasterGFRecipientFieldL10n || {};
		var data = l10n.regions || {};
		var entry = data[ String( region || '' ).toUpperCase() ];
		if ( ! entry ) {
			return null;
		}
		// Compile-once cache.
		if ( ! entry._compiled ) {
			entry._compiled = {
				pattern: new RegExp( entry.pattern ),
				strip: entry.strip ? new RegExp( entry.strip ) : null,
				cc: entry.cc
			};
		}
		return entry._compiled;
	}

	// Username TLD blocklist — Meta's enumerated list + Broadcaster's extras.
	var USERNAME_TLDS = [
		'com', 'org', 'net', 'int', 'edu', 'gov', 'mil', 'us', 'in', 'html',
		'io', 'co', 'uk', 'de', 'fr', 'es', 'it', 'nl', 'be', 'au', 'ca',
		'info', 'biz'
	];
	var USERNAME_TLD_REGEX = new RegExp( '\\.(' + USERNAME_TLDS.join( '|' ) + ')$', 'i' );

	function stripFormatting( input ) {
		var trimmed = String( input == null ? '' : input ).trim();
		if ( trimmed === '' ) {
			return '';
		}
		var hasPlus = trimmed.charAt( 0 ) === '+';
		var digits = trimmed.replace( /\D/g, '' );
		return ( hasPlus && digits !== '' ) ? '+' + digits : digits;
	}

	function normalisePhone( input, region ) {
		var cleaned = stripFormatting( input );
		if ( cleaned === '' ) {
			return null;
		}
		if ( cleaned.charAt( 0 ) === '+' ) {
			return /^\+[1-9]\d{6,14}$/.test( cleaned ) ? cleaned : null;
		}
		var rule = getRegionRule( region );
		if ( ! rule ) {
			return null;
		}
		if ( ! rule.pattern.test( cleaned ) ) {
			return null;
		}
		var national = rule.strip ? cleaned.replace( rule.strip, '' ) : cleaned;
		return '+' + rule.cc + national;
	}

	function isValidUsername( username ) {
		if ( username.length < 3 || username.length > 35 ) {
			return false;
		}
		if ( ! /^[a-zA-Z0-9._]+$/.test( username ) ) {
			return false;
		}
		if ( ! /[a-zA-Z]/.test( username ) ) {
			return false;
		}
		if ( username.charAt( 0 ) === '.' || username.charAt( username.length - 1 ) === '.' ) {
			return false;
		}
		if ( username.indexOf( '..' ) !== -1 ) {
			return false;
		}
		if ( /^www/i.test( username ) ) {
			return false;
		}
		if ( USERNAME_TLD_REGEX.test( username ) ) {
			return false;
		}
		return true;
	}

	function discriminate( input, usernamesEnabled ) {
		var trimmed = String( input == null ? '' : input ).replace( /^\s+/, '' );
		if ( trimmed === '' ) {
			return 'invalid';
		}
		if ( trimmed.charAt( 0 ) === '@' ) {
			return usernamesEnabled ? 'username' : 'invalid';
		}
		return 'phone';
	}

	function validate( input, region, usernamesEnabled ) {
		var kind = discriminate( input, usernamesEnabled );
		if ( kind === 'invalid' ) {
			return false;
		}
		if ( kind === 'username' ) {
			var trimmed = String( input ).replace( /^\s+/, '' );
			var withoutAt = trimmed.charAt( 0 ) === '@' ? trimmed.slice( 1 ) : trimmed;
			return isValidUsername( withoutAt );
		}
		return normalisePhone( input, region ) !== null;
	}

	function getMessage( usernamesEnabled ) {
		// Fallback strings when wp_localize_script hasn't injected
		// translated copy. Production wiring localises via
		// BroadcasterGFRecipientFieldL10n (see field's enqueue method).
		var l10n = window.BroadcasterGFRecipientFieldL10n || {};
		if ( usernamesEnabled ) {
			return l10n.invalidPhoneOrUsername ||
				'Please enter a valid phone number, or a WhatsApp username starting with @.';
		}
		return l10n.invalidPhone || 'Please enter a valid phone number.';
	}

	function showError( container, message ) {
		container.classList.add( 'broadcastergf-recipient-invalid' );
		var existing = container.querySelector( '.broadcastergf-recipient-error' );
		if ( existing ) {
			existing.textContent = message;
			return;
		}
		var span = document.createElement( 'span' );
		span.className = 'broadcastergf-recipient-error';
		span.setAttribute( 'role', 'alert' );
		span.textContent = message;
		container.appendChild( span );
	}

	function clearError( container ) {
		container.classList.remove( 'broadcastergf-recipient-invalid' );
		var existing = container.querySelector( '.broadcastergf-recipient-error' );
		if ( existing ) {
			existing.parentNode.removeChild( existing );
		}
	}

	function validateContainer( container ) {
		var input = container.querySelector( 'input[type="text"]' );
		if ( ! input ) {
			return true;
		}
		// Empty input — let server handle required-field rejection.
		if ( input.value.trim() === '' ) {
			clearError( container );
			return true;
		}
		var region = container.getAttribute( 'data-region' ) || 'GB';
		var usernamesEnabled = container.getAttribute( 'data-usernames-enabled' ) === '1';

		if ( validate( input.value, region, usernamesEnabled ) ) {
			clearError( container );
			return true;
		}
		showError( container, getMessage( usernamesEnabled ) );
		return false;
	}

	function attachToForm( form ) {
		// Validate any of our containers on submit; cancel submission
		// if any are invalid. GF's own validation runs after this in the
		// submit chain; if everything passes here, GF's submit chain
		// continues and the server-side validator (Field_Submission_Dispatcher)
		// applies the same rules as a defence-in-depth check.
		form.addEventListener( 'submit', function ( event ) {
			var containers = form.querySelectorAll( '.broadcastergf-recipient-field' );
			var hasErrors = false;
			for ( var i = 0; i < containers.length; i++ ) {
				if ( ! validateContainer( containers[ i ] ) ) {
					hasErrors = true;
				}
			}
			if ( hasErrors ) {
				event.preventDefault();
				event.stopPropagation();
				// Scroll first invalid field into view.
				var first = form.querySelector( '.broadcastergf-recipient-invalid' );
				if ( first && first.scrollIntoView ) {
					first.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				}
			}
		}, true );

		// Live-clear errors as the submitter types/edits.
		form.addEventListener( 'input', function ( event ) {
			var target = event.target;
			if ( ! target || ! target.closest ) {
				return;
			}
			var container = target.closest( '.broadcastergf-recipient-field' );
			if ( container ) {
				clearError( container );
			}
		} );

		// On blur, if the input holds a phone number we can confidently
		// normalise, rewrite it to E.164 (+<cc><national>) so the
		// internationalisation assumption is visible to the submitter —
		// "07714681600" becomes "+447714681600" when they leave the field.
		// The @username path (and anything we can't normalise) is left
		// exactly as typed; `focusout` is used because `blur` doesn't
		// bubble up to the form.
		form.addEventListener( 'focusout', function ( event ) {
			var target = event.target;
			if ( ! target || ! target.closest || target.tagName !== 'INPUT' ) {
				return;
			}
			var container = target.closest( '.broadcastergf-recipient-field' );
			if ( ! container ) {
				return;
			}
			var current = target.value.trim();
			if ( current === '' ) {
				return;
			}
			var usernamesEnabled = container.getAttribute( 'data-usernames-enabled' ) === '1';
			// Only ever touch the phone branch — never @usernames.
			if ( discriminate( current, usernamesEnabled ) !== 'phone' ) {
				return;
			}
			var region = container.getAttribute( 'data-region' ) || 'GB';
			var normalised = normalisePhone( current, region );
			if ( normalised && normalised !== target.value ) {
				target.value = normalised;
				clearError( container );
			}
		} );
	}

	function init() {
		// Find every form that contains at least one of our fields.
		var seen = [];
		var fields = document.querySelectorAll( '.broadcastergf-recipient-field' );
		for ( var i = 0; i < fields.length; i++ ) {
			var form = fields[ i ].closest( 'form' );
			if ( form && seen.indexOf( form ) === -1 ) {
				seen.push( form );
				attachToForm( form );
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();

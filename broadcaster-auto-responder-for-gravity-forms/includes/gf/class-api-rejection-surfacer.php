<?php
/**
 * API Rejection Surfacer — when Broadcaster declines a recipient, makes the
 * decline visible to the site administrator without blocking form submission.
 *
 * BRO-902 design D8 + Q2: rejections never block submission, but they must
 * stop being silent. The Surfacer attaches a structured marker to the entry
 * (read later by the WhatsApp Recipient Field's entry-detail render path so
 * it shows inline beside the field — Q2), adds a regular Gravity Forms
 * entry note for audit trail, and writes a one-line log entry through the
 * feed addon's logger.
 *
 * "Rejection" means either:
 *   - HTTP 422 from Broadcaster (validation rejected the recipient), or
 *   - response `auto_response.error_code` of `recipient_not_deliverable`
 *     (the API accepted the request but the auto-response could not be
 *     dispatched because the recipient isn't reachable).
 *
 * Side effects are orchestrated through injected callables so the class is
 * unit-testable without Gravity Forms or WordPress loaded. The feed addon
 * is responsible for wiring the production callables (gform_update_meta,
 * GFAPI::add_note, the addon's own log_error).
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\GF;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Surfaces Broadcaster recipient rejections without blocking form submission.
 */
final class Api_Rejection_Surfacer {

	/**
	 * Entry meta key prefix. Final key is `<prefix><field_id>`.
	 */
	const META_KEY_PREFIX = '_broadcastergf_rejection_';

	/**
	 * The error_code Broadcaster returns when the recipient is unreachable.
	 */
	const ERROR_CODE_RECIPIENT_NOT_DELIVERABLE = 'recipient_not_deliverable';

	/**
	 * Inspect an API response; if it's a rejection, surface it.
	 *
	 * @param array    $api_response Return shape from Client::send_incoming_message().
	 * @param int      $entry_id     Gravity Forms entry ID.
	 * @param int      $field_id     The WhatsApp Recipient field's ID on the form.
	 * @param callable $logger       Receives ( string $message ).
	 * @param callable $meta_setter  Receives ( int $entry_id, string $key, mixed $value ).
	 * @param callable $note_adder   Receives ( int $entry_id, int $user_id, string $user_name, string $note ).
	 * @return bool True if a rejection was surfaced; false if no rejection.
	 */
	public static function surface(
		array $api_response,
		$entry_id,
		$field_id,
		callable $logger,
		callable $meta_setter,
		callable $note_adder
	) {
		if ( ! self::is_rejection( $api_response ) ) {
			return false;
		}

		$entry_id = (int) $entry_id;
		$field_id = (int) $field_id;

		$summary       = self::extract_rejection_summary( $api_response );
		$summary['at'] = time();

		// Persist as entry meta — the WhatsApp Recipient Field's entry-detail
		// render reads this to surface the marker inline beside the field.
		call_user_func(
			$meta_setter,
			$entry_id,
			self::META_KEY_PREFIX . $field_id,
			$summary
		);

		$note = sprintf(
			/* translators: 1: HTTP status code or 'n/a', 2: error_code or 'n/a', 3: human-readable message */
			__( 'Broadcaster declined the recipient. HTTP %1$s, code %2$s. %3$s', 'broadcaster-auto-responder-for-gravity-forms' ),
			null !== $summary['http_code'] ? (string) $summary['http_code'] : 'n/a',
			null !== $summary['error_code'] ? $summary['error_code'] : 'n/a',
			$summary['message']
		);
		call_user_func( $note_adder, $entry_id, 0, 'Broadcaster', $note );

		$log_line = sprintf(
			'recipient rejection: entry=%d field=%d http=%s code=%s message=%s',
			$entry_id,
			$field_id,
			null !== $summary['http_code'] ? (string) $summary['http_code'] : 'n/a',
			null !== $summary['error_code'] ? $summary['error_code'] : 'n/a',
			$summary['message']
		);
		call_user_func( $logger, $log_line );

		return true;
	}

	/**
	 * Decide whether an API response represents a recipient rejection.
	 *
	 * @param array $api_response Client return shape.
	 * @return bool
	 */
	public static function is_rejection( array $api_response ) {
		if ( isset( $api_response['http_code'] ) && 422 === (int) $api_response['http_code'] ) {
			return true;
		}

		if ( self::ERROR_CODE_RECIPIENT_NOT_DELIVERABLE === self::extract_error_code( $api_response ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Build a structured rejection summary from an API response.
	 *
	 * @param array $api_response Client return shape.
	 * @return array{http_code:int|null,error_code:string|null,message:string}
	 */
	public static function extract_rejection_summary( array $api_response ) {
		$http_code = null;
		if ( isset( $api_response['http_code'] ) ) {
			$http_code = (int) $api_response['http_code'];
		}

		$message = '';
		if ( isset( $api_response['message'] ) && is_string( $api_response['message'] ) ) {
			$message = sanitize_text_field( $api_response['message'] );
		}

		$error_code = self::extract_error_code( $api_response );
		if ( null !== $error_code ) {
			$error_code = sanitize_text_field( $error_code );
		}

		return array(
			'http_code'  => $http_code,
			'error_code' => $error_code,
			'message'    => $message,
		);
	}

	/**
	 * Extract the auto_response.error_code from an API response.
	 *
	 * Prefers the top-level `error_code` key (populated by the Client when
	 * task 5 lands) and falls back to digging into the raw response body so
	 * the Surfacer is correct whether or not the Client extension has run.
	 *
	 * @param array $api_response Client return shape.
	 * @return string|null
	 */
	private static function extract_error_code( array $api_response ) {
		if ( isset( $api_response['error_code'] ) && is_string( $api_response['error_code'] ) ) {
			return $api_response['error_code'];
		}

		// BRO-905: auto_response lives under `data` in the response body
		// ({ success, data: { ... auto_response } }); check there, then the
		// (legacy) top-level position, so the Surfacer is correct even if the
		// Client's top-level error_code lift hasn't run.
		foreach ( array( array( 'response', 'data', 'auto_response', 'error_code' ), array( 'response', 'auto_response', 'error_code' ) ) as $path ) {
			$node = $api_response;
			foreach ( $path as $key ) {
				if ( ! is_array( $node ) || ! isset( $node[ $key ] ) ) {
					$node = null;
					break;
				}
				$node = $node[ $key ];
			}
			if ( is_string( $node ) ) {
				return $node;
			}
		}

		return null;
	}
}

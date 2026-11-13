<?php
/**
 * Broadcaster's warning about a reply template, shown beside the template choice (BRO-2003).
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\GF;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns Broadcaster's answer about a reply template into the template field's description.
 *
 * Broadcaster's template check carries a `warning` when the template can be sent but a brand-new enquirer will
 * never get it (a marketing template: a new enquirer has not agreed to marketing). The business must not find
 * out by silence, so the feed shows Broadcaster's own words beside the template, unchanged. Gravity Forms prints
 * a field description as HTML, so the text is escaped here and nothing from the API is rendered as markup.
 */
final class Reply_Template_Warning {

	/**
	 * The field description for a template check, or null when there is nothing to warn about.
	 *
	 * A refused template gets no warning: the refusal is the field's error, and saying both would be noise.
	 * An unanswered check, or a Broadcaster too old to send the field, shows nothing.
	 *
	 * @param array|null $check Client::check_reply_template() result, or null when no check was made.
	 *
	 * @return string|null Escaped HTML.
	 */
	public static function description( $check ) {
		if ( ! is_array( $check ) || false === ( $check['usable'] ?? null ) ) {
			return null;
		}
		$warning = $check['warning'] ?? null;
		if ( ! is_string( $warning ) || '' === trim( $warning ) ) {
			return null;
		}
		return '<span class="broadcaster-template-warning" role="alert" style="color:#b32d2e;">' . esc_html( $warning ) . '</span>';
	}
}

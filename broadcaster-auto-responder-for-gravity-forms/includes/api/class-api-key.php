<?php
/**
 * Where the Broadcaster API key comes from.
 *
 * @package BroadcasterGF
 */

namespace BroadcasterGF\Api;

defined( 'ABSPATH' ) || exit;

/**
 * BRO-1468: the API key may be defined in wp-config.php instead of being saved in the database.
 *
 *     define( 'BROADCASTER_API_KEY', 'your-key' );
 *
 * A key in the options table is in every database backup, staging clone and migration export. One in wp-config.php
 * is not. When the constant is defined and not blank it wins over any saved key, and the settings screen neither
 * shows it nor saves one. Without it, the saved key is used exactly as before.
 *
 * EVERY READ OF THE KEY GOES THROUGH resolve(). The "not configured" notice once read the raw option directly, so
 * a site using the constant would have shown that notice for ever. Three readers in the add-on and that one; a
 * fifth that reads the option or get_plugin_setting( 'api_key' ) directly reintroduces exactly that.
 */
class Api_Key {

	/**
	 * The wp-config.php constant.
	 */
	const CONSTANT = 'BROADCASTER_API_KEY';

	/**
	 * The key from wp-config.php, trimmed, or '' when it is not defined, not a string, or blank.
	 */
	public static function from_constant(): string {
		if ( ! defined( self::CONSTANT ) ) {
			return '';
		}
		$value = constant( self::CONSTANT );

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * True while wp-config.php supplies the key.
	 */
	public static function is_from_constant(): bool {
		return '' !== self::from_constant();
	}

	/**
	 * The key to use: wp-config.php's when it gives one, otherwise the saved one.
	 *
	 * @param mixed $saved The saved setting, as stored (may be missing or not a string).
	 */
	public static function resolve( $saved ): string {
		$constant = self::from_constant();
		if ( '' !== $constant ) {
			return $constant;
		}

		return is_string( $saved ) ? trim( $saved ) : '';
	}

	/**
	 * The settings field the key is typed into. Never a stored setting, so Gravity Forms always renders it empty:
	 * the saved key is never sent back to the browser (BRO-1468).
	 */
	const FIELD = 'api_key_new';

	/**
	 * What the settings page says about the saved key: its last four characters only, or nothing when none is saved.
	 * A key too short to hide most of it says only that one is saved.
	 *
	 * @param mixed $saved The saved setting, as stored.
	 */
	public static function saved_hint( $saved ): string {
		$saved = is_string( $saved ) ? trim( $saved ) : '';
		if ( '' === $saved ) {
			return '';
		}
		if ( strlen( $saved ) < 12 ) {
			return __( 'A key is saved.', 'broadcaster-auto-responder-for-gravity-forms' );
		}

		/* translators: %s: the last four characters of the saved API key */
		return sprintf( __( 'Saved key ending %s.', 'broadcaster-auto-responder-for-gravity-forms' ), substr( $saved, -4 ) );
	}

	/**
	 * The settings to write when the settings screen is saved.
	 *
	 * The key is typed into FIELD, which starts empty. Something typed there replaces the saved key; a blank submit
	 * keeps it. Gravity Forms replaces the whole stored array on save, so the earlier key is always put back
	 * explicitly. An `api_key` arriving in the post is never written: it can only be forged.
	 *
	 * While wp-config.php supplies the key the screen has no key field at all, nothing new is written, and the
	 * earlier key is kept, so removing the constant later falls back to it, or to unconfigured if there was none.
	 *
	 * @param array $settings      Settings about to be written.
	 * @param mixed $previous_key  The key saved before this save, as stored.
	 */
	public static function settings_to_save( array $settings, $previous_key ): array {
		$typed = isset( $settings[ self::FIELD ] ) && is_string( $settings[ self::FIELD ] ) ? trim( $settings[ self::FIELD ] ) : '';
		unset( $settings['api_key'], $settings[ self::FIELD ] );

		if ( '' !== $typed && ! self::is_from_constant() ) {
			$settings['api_key'] = $typed;
		} elseif ( is_string( $previous_key ) && '' !== $previous_key ) {
			$settings['api_key'] = $previous_key;
		}

		return $settings;
	}
}

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
	 * The settings to write when the settings screen is saved.
	 *
	 * While wp-config.php supplies the key, the screen has no key field, so the posted settings carry none, and
	 * Gravity Forms replaces the whole stored array. Whatever key was saved before is put back unchanged: nothing
	 * new is written, and removing the constant later falls back to it, or to unconfigured if there was none.
	 *
	 * @param array $settings      Settings about to be written.
	 * @param mixed $previous_key  The key saved before this save, as stored.
	 */
	public static function settings_to_save( array $settings, $previous_key ): array {
		if ( ! self::is_from_constant() ) {
			return $settings;
		}
		unset( $settings['api_key'] );
		if ( is_string( $previous_key ) && '' !== $previous_key ) {
			$settings['api_key'] = $previous_key;
		}

		return $settings;
	}
}

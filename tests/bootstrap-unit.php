<?php
/**
 * Bootstrap for pure unit tests.
 *
 * Pure unit tests (in tests/unit/) cover plugin classes that have no
 * WordPress or Gravity Forms dependencies — validators, discriminator,
 * dispatcher, etc. They don't need the WP test framework, so this bootstrap
 * loads only the dev composer autoload + a tiny SPL autoloader for the
 * plugin's classmap convention.
 *
 * Integration tests (in tests/integration/) use bootstrap.php instead, which
 * spins up the full WP test environment.
 *
 * @package BroadcasterGF
 */

// Defines that plugin source files expect at the top-of-file ABSPATH guard.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

if ( ! defined( 'BROADCASTERGF_ENABLE_USERNAMES' ) ) {
	define( 'BROADCASTERGF_ENABLE_USERNAMES', false );
}

/*
 * Tiny stubs for WordPress translation functions so plugin classes that
 * call `__()` can be loaded and exercised without booting WP. Production
 * uses the real WP implementations; these are pass-throughs for tests.
 */
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.WP.I18n
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore WordPress.WP.I18n
		return $text;
	}
}

/*
 * Stand-in for sanitize_text_field() so classes that sanitise external data
 * (e.g. Api_Rejection_Surfacer) can run without WP. Approximates core's
 * behaviour: strip tags, convert newlines/tabs to spaces, collapse runs of
 * spaces, and trim. Clean single-spaced strings pass through unchanged.
 */
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = (string) $str;
		$str = preg_replace( '/<[^>]*>/', '', $str );
		$str = preg_replace( '/[\r\n\t]+/', ' ', $str );
		$str = preg_replace( '/ {2,}/', ' ', $str );
		return trim( $str );
	}
}

// Load PHPUnit + dev tooling.
$dev_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! file_exists( $dev_autoload ) ) {
	fwrite( STDERR, "Run `composer install` at the repository root before running unit tests.\n" );
	exit( 1 );
}
require $dev_autoload;

/*
 * Tiny autoloader for plugin classes. Mirrors the plugin's classmap convention:
 *   BroadcasterGF\Foo\Bar_Baz  →  includes/foo/class-bar-baz.php
 *
 * This avoids forcing testers to run `composer install` inside the plugin
 * subdirectory before the pure unit tests can run. The plugin's own composer
 * autoloader is for the deployed plugin; this autoloader is just for tests.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'BroadcasterGF\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );

		$base_filename = 'class-' . str_replace( '_', '-', strtolower( array_pop( $parts ) ) ) . '.php';
		$subdir        = '';
		if ( ! empty( $parts ) ) {
			$subdir = implode( '/', array_map( 'strtolower', $parts ) ) . '/';
		}

		$plugin_includes = dirname( __DIR__ ) . '/broadcaster-auto-responder-for-gravity-forms/includes';
		$file            = $plugin_includes . '/' . $subdir . $base_filename;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

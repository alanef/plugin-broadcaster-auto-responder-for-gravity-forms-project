<?php
/**
 * BRO-1468: the "not configured" notice counts a key in wp-config.php as configured.
 *
 * It read the raw option, so a site with BROADCASTER_API_KEY in wp-config.php and nothing saved would have shown
 * "not configured" for ever. Each case runs in its own process: the constant cannot be undefined, and get_option()
 * is stubbed for the case. Fixture keys only.
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\Admin\Notices;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BroadcasterGF\Admin\Notices
 */
final class NoticesApiConfiguredTest extends TestCase {

	private function configured( $option ): bool {
		$GLOBALS['broadcastergf_fixture_option'] = $option;
		if ( ! function_exists( 'get_option' ) ) {
			eval( 'function get_option( $name, $default = false ) { return $GLOBALS["broadcastergf_fixture_option"] ?? $default; }' ); // phpcs:ignore Squiz.PHP.Eval
		}
		$method = new ReflectionMethod( Notices::class, 'api_configured' );
		$method->setAccessible( true );

		return $method->invoke( new Notices() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_key_in_wp_config_with_nothing_saved_is_configured(): void {
		define( 'BROADCASTER_API_KEY', 'config-fixture-key' );

		$this->assertTrue( $this->configured( array() ) );
		$this->assertTrue( $this->configured( false ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_without_the_constant_a_saved_key_is_configured_and_none_is_not(): void {
		$this->assertTrue( $this->configured( array( 'api_key' => 'saved-fixture-key' ) ) );
		$this->assertFalse( $this->configured( array( 'api_key' => '   ' ) ) );
		$this->assertFalse( $this->configured( array() ) );
		$this->assertFalse( $this->configured( 'not an array' ) );
	}
}

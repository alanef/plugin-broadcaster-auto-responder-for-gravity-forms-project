<?php
/**
 * Unit tests for Api_Key (BRO-1468): the API key may come from BROADCASTER_API_KEY in wp-config.php.
 *
 * A constant cannot be undefined once defined, so every case that defines it runs in its own process. Fixture
 * keys only.
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\Api\Api_Key;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BroadcasterGF\Api\Api_Key
 */
final class ApiKeyTest extends TestCase {

	// --- Without the constant: exactly as before. ---

	public function test_without_the_constant_the_saved_key_is_used_trimmed(): void {
		$this->assertFalse( Api_Key::is_from_constant() );
		$this->assertSame( 'saved-fixture-key', Api_Key::resolve( '  saved-fixture-key ' ) );
	}

	public function test_without_the_constant_or_a_saved_key_there_is_no_key(): void {
		$this->assertSame( '', Api_Key::resolve( '' ) );
		$this->assertSame( '', Api_Key::resolve( null ) );
		$this->assertSame( '', Api_Key::resolve( array( 'not', 'a', 'key' ) ) );
	}

	public function test_without_the_constant_saving_writes_what_was_posted(): void {
		$posted = array( 'api_key' => 'new-fixture-key', 'default_phone_country' => 'GB' );

		$this->assertSame( $posted, Api_Key::settings_to_save( $posted, 'old-fixture-key' ) );
	}

	// --- With the constant. ---

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_constant_wins_over_a_saved_key(): void {
		define( 'BROADCASTER_API_KEY', ' config-fixture-key ' );

		$this->assertTrue( Api_Key::is_from_constant() );
		$this->assertSame( 'config-fixture-key', Api_Key::resolve( 'saved-fixture-key' ) );
		$this->assertSame( 'config-fixture-key', Api_Key::resolve( '' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_blank_constant_is_not_a_key_and_the_saved_one_is_used(): void {
		define( 'BROADCASTER_API_KEY', '   ' );

		$this->assertFalse( Api_Key::is_from_constant() );
		$this->assertSame( 'saved-fixture-key', Api_Key::resolve( 'saved-fixture-key' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_constant_that_is_not_a_string_is_not_a_key(): void {
		define( 'BROADCASTER_API_KEY', true );

		$this->assertFalse( Api_Key::is_from_constant() );
		$this->assertSame( 'saved-fixture-key', Api_Key::resolve( 'saved-fixture-key' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_with_the_constant_saving_writes_no_new_key_and_keeps_the_earlier_one(): void {
		define( 'BROADCASTER_API_KEY', 'config-fixture-key' );

		// The screen has no key field, so the post carries none; a forged one is not written either.
		$posted = array( 'api_key' => 'forged-fixture-key', 'default_phone_country' => 'GB' );

		$this->assertSame(
			array( 'default_phone_country' => 'GB', 'api_key' => 'old-fixture-key' ),
			Api_Key::settings_to_save( $posted, 'old-fixture-key' )
		);
		// The constant's value is never what gets written.
		$this->assertNotContains( 'config-fixture-key', Api_Key::settings_to_save( $posted, 'old-fixture-key' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_with_the_constant_and_nothing_saved_before_saving_writes_no_key(): void {
		define( 'BROADCASTER_API_KEY', 'config-fixture-key' );

		$this->assertSame(
			array( 'default_phone_country' => 'GB' ),
			Api_Key::settings_to_save( array( 'default_phone_country' => 'GB' ), '' )
		);
	}

	// --- Every read goes through Api_Key. ---

	/**
	 * The "not configured" notice read the raw option, so a site with the key in wp-config.php would have shown it
	 * for ever. Any read of the key outside Api_Key and the add-on's get_api_key() fails here.
	 */
	public function test_no_code_reads_the_key_except_through_api_key(): void {
		$root  = dirname( __DIR__, 2 ) . '/broadcaster-auto-responder-for-gravity-forms';
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );

		$scanned = 0;
		$reads   = array();
		foreach ( $files as $file ) {
			$path = (string) $file;
			if ( '.php' !== substr( $path, -4 ) || false !== strpos( $path, '/vendor/' ) ) {
				continue;
			}
			++$scanned;
			foreach ( file( $path ) as $n => $line ) {
				if ( preg_match( "/get_plugin_setting\\(\\s*'api_key'\\s*\\)|\\[\\s*'api_key'\\s*\\]/", $line ) ) {
					$reads[] = substr( $path, strlen( $root ) + 1 ) . ':' . ( $n + 1 ) . ': ' . trim( $line );
				}
			}
		}

		// Positive control: the search reaches the plugin, and finds the reads that are allowed, by file and code.
		$this->assertGreaterThan( 10, $scanned );
		$strip = static function ( string $read ): string {
			return preg_replace( '/:\d+: /', ': ', $read );
		};
		$sorted = static function ( array $list ): array {
			sort( $list );
			return $list;
		};
		$this->assertSame(
			$sorted( array(
				"includes/admin/class-notices.php: \$saved = is_array( \$opt ) && isset( \$opt['api_key'] ) ? \$opt['api_key'] : '';",
				"includes/api/class-api-key.php: * fifth that reads the option or get_plugin_setting( 'api_key' ) directly reintroduces exactly that.",
				"includes/api/class-api-key.php: unset( \$settings['api_key'] );",
				"includes/api/class-api-key.php: \$settings['api_key'] = \$previous_key;",
				"includes/gf/class-broadcaster-gf-feedaddon.php: return \\BroadcasterGF\\Api\\Api_Key::resolve( \$this->get_plugin_setting( 'api_key' ) );",
				"includes/gf/class-broadcaster-gf-feedaddon.php: \$settings = \\BroadcasterGF\\Api\\Api_Key::settings_to_save( (array) \$settings, \$this->get_plugin_setting( 'api_key' ) );",
			) ),
			$sorted( array_map( $strip, $reads ) ),
			'A read of the API key bypasses Api_Key'
		);
	}
}

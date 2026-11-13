<?php
/**
 * BRO-2003: a spam-marked entry never runs the feed.
 *
 * Gravity Forms does the gating: GFFeedAddOn::maybe_process_feed() returns early for an entry whose status is spam,
 * and process_feed_when_unspammed() runs feeds only when an admin marks an entry "Not spam". Measured against
 * Gravity Forms 2.10.2 in wp-env on 13 Nov 2026 (honeypot, gform_entry_is_spam, resend notifications, not spam,
 * trash and restore, honeypot abort), recorded on BRO-2003. Gravity Forms is not loaded in the unit suite, so these
 * tests keep the add-on from stepping around that gate: it must not run feeds from its own hooks.
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SpamGateTest extends TestCase {

	private function plugin_php(): array {
		$root  = dirname( __DIR__, 2 ) . '/broadcaster-auto-responder-for-gravity-forms';
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		$out   = array();
		foreach ( $files as $file ) {
			$path = (string) $file;
			if ( '.php' === substr( $path, -4 ) && false === strpos( $path, '/vendor/' ) ) {
				$out[ substr( $path, strlen( $root ) + 1 ) ] = (string) file_get_contents( $path );
			}
		}
		return $out;
	}

	public function test_the_add_on_leaves_feed_dispatch_to_gravity_forms(): void {
		$files = $this->plugin_php();

		// Positive control: the scan reaches the add-on and sees its process_feed().
		$this->assertArrayHasKey( 'includes/gf/class-broadcaster-gf-feedaddon.php', $files );
		$this->assertMatchesRegularExpression( '/function\s+process_feed\s*\(/', $files['includes/gf/class-broadcaster-gf-feedaddon.php'] );

		$found = array();
		foreach ( $files as $name => $src ) {
			// Overriding these replaces the framework's spam check.
			if ( preg_match( '/function\s+(maybe_process_feed|process_feed_when_unspammed|process_feeds|maybe_delay_feed)\s*\(/', $src, $m ) ) {
				$found[] = "$name overrides {$m[1]}()";
			}
			// Hooking a submission or status change ourselves would run outside it.
			if ( preg_match( "/add_(?:action|filter)\\(\\s*'(gform_entry_post_save|gform_after_submission[^']*|gform_update_status|gform_post_update_entry[^']*)'/", $src, $m ) ) {
				$found[] = "$name hooks {$m[1]}";
			}
			// Calling process_feed() directly skips maybe_process_feed().
			if ( preg_match( '/->\s*process_feed\s*\(/', $src ) ) {
				$found[] = "$name calls process_feed() directly";
			}
		}
		$this->assertSame( array(), $found, 'A path that can run the feed without Gravity Forms\' spam check' );
	}

	public function test_feed_settings_say_who_gets_the_reply_and_recommend_spam_protection(): void {
		$src = $this->plugin_php()['includes/gf/class-broadcaster-gf-feedaddon.php'];
		$this->assertStringContainsString( 'An automatic reply goes to whatever number is typed into the form.', $src );
		$this->assertStringContainsString( 'turn on the anti-spam honeypot', $src );
		$this->assertStringContainsString( 'add a CAPTCHA field', $src );
	}
}

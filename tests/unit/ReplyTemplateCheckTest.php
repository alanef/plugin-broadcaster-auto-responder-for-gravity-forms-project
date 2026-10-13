<?php
/**
 * Unit tests for Client::check_reply_template (BRO-1831).
 *
 * Broadcaster answers GET /api/v1/reply-templates/{name} with whether a template can be the automatic reply to a
 * form. The feed is refused at save only on a real "no"; anything that is not an answer must read as unknown, so
 * a network blip never blocks a save and never passes for a "yes".
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\Api\Client;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' );
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		$GLOBALS['brogf_test_last_get_url'] = $url;
		return $GLOBALS['brogf_test_get_response'] ?? array( 'code' => 200, 'body' => '{}' );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return $response['code'] ?? 0;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return $response['body'] ?? '';
	}
}

/**
 * @covers \BroadcasterGF\Api\Client::check_reply_template
 */
final class ReplyTemplateCheckTest extends TestCase {

	private function ask( int $code, string $body, string $name = 'contact_form_reply' ): array {
		$GLOBALS['brogf_test_get_response'] = array( 'code' => $code, 'body' => $body );
		return ( new Client( 'https://broadcaster.example', 'fixture-key' ) )->check_reply_template( $name );
	}

	public function test_a_no_comes_back_with_broadcasters_reason(): void {
		$reason = 'This template uses {{client_first_name}}. An automatic reply to a form can only use your business details and opening hours, because anything else could come from the visitor. Choose a template without it.';
		$result = $this->ask( 200, json_encode( array( 'name' => 'contact_form_reply', 'usable' => false, 'reason' => $reason, 'cannot_fill' => array( 'client_first_name' ) ) ) );

		$this->assertSame( array( 'usable' => false, 'reason' => $reason ), $result );
	}

	public function test_a_yes_is_a_yes(): void {
		$this->assertSame(
			array( 'usable' => true, 'reason' => null ),
			$this->ask( 200, json_encode( array( 'name' => 'contact_form_reply', 'usable' => true, 'reason' => null, 'cannot_fill' => array() ) ) )
		);
	}

	public function test_no_approved_template_by_that_name_is_a_no(): void {
		$result = $this->ask( 404, json_encode( array( 'name' => 'nope', 'usable' => false, 'reason' => 'There is no approved template with that name.', 'cannot_fill' => array() ) ), 'nope' );

		$this->assertFalse( $result['usable'] );
		$this->assertSame( 'There is no approved template with that name.', $result['reason'] );
	}

	public function test_anything_that_is_not_an_answer_is_unknown_never_a_yes(): void {
		// An older Broadcaster without the endpoint, a key without message intake, an outage, a non-JSON page.
		foreach ( array( array( 404, '<html>Not Found</html>' ), array( 403, '{"message":"Forbidden"}' ), array( 500, '{}' ), array( 200, 'not json' ) ) as $case ) {
			$this->assertNull( $this->ask( $case[0], $case[1] )['usable'], 'HTTP ' . $case[0] . ' ' . $case[1] );
		}
	}

	public function test_the_name_is_encoded_into_the_path(): void {
		$this->ask( 200, json_encode( array( 'usable' => true, 'reason' => null ) ), 'a name/with ?odd&chars' );

		$this->assertSame( 'https://broadcaster.example/api/v1/reply-templates/a%20name%2Fwith%20%3Fodd%26chars', $GLOBALS['brogf_test_last_get_url'] );
	}
}

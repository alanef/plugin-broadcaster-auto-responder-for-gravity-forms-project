<?php
/**
 * Unit tests for Reply_Template_Warning (BRO-2003).
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\GF\Reply_Template_Warning;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BroadcasterGF\GF\Reply_Template_Warning
 */
final class ReplyTemplateWarningTest extends TestCase {

	private const WARNING = "New enquirers won't get this, because they haven't agreed to marketing. Choose a Utility template.";

	public function test_a_warning_is_shown_in_broadcasters_words(): void {
		$html = Reply_Template_Warning::description( array( 'usable' => true, 'reason' => null, 'warning' => self::WARNING ) );

		$this->assertSame(
			'<span class="broadcaster-template-warning" role="alert" style="color:#b32d2e;">New enquirers won&#039;t get this, because they haven&#039;t agreed to marketing. Choose a Utility template.</span>',
			$html
		);
		$this->assertSame( self::WARNING, html_entity_decode( strip_tags( (string) $html ), ENT_QUOTES ) );
	}

	public function test_no_warning_shows_nothing(): void {
		$this->assertNull( Reply_Template_Warning::description( array( 'usable' => true, 'reason' => null, 'warning' => null ) ) );
		$this->assertNull( Reply_Template_Warning::description( array( 'usable' => true, 'reason' => null ) ) );
		$this->assertNull( Reply_Template_Warning::description( array( 'usable' => true, 'reason' => null, 'warning' => ' ' ) ) );
	}

	public function test_no_check_or_an_unanswered_one_shows_nothing(): void {
		$this->assertNull( Reply_Template_Warning::description( null ) );
		$this->assertNull( Reply_Template_Warning::description( array( 'usable' => null, 'reason' => null, 'warning' => null ) ) );
	}

	public function test_a_refused_template_shows_its_error_not_a_warning(): void {
		$this->assertNull( Reply_Template_Warning::description( array( 'usable' => false, 'reason' => 'No.', 'warning' => self::WARNING ) ) );
	}

	public function test_markup_from_the_api_is_shown_as_text_never_rendered(): void {
		$html = (string) Reply_Template_Warning::description( array( 'usable' => true, 'reason' => null, 'warning' => '<img src=x onerror=alert(1)><a href="https://example.test">Choose</a>' ) );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringContainsString( '&lt;img src=x onerror=alert(1)&gt;', $html );
		$this->assertSame( 1, substr_count( $html, '<' ) - substr_count( $html, '</' ), 'Only our own span opens a tag' );
	}
}

<?php
/**
 * Unit tests for Out_Of_Hours_Reply (BRO-1878).
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\GF\Out_Of_Hours_Reply;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BroadcasterGF\GF\Out_Of_Hours_Reply
 */
final class OutOfHoursReplyTest extends TestCase {

	// --- The three choices: what each sends ---

	public function test_same_template_sends_the_in_hours_name_in_both_fields(): void {
		$meta = array(
			'out_of_hours_reply'         => 'same',
			'in_hours_template_name'     => 'contact_form_reply',
			'out_of_hours_template_name' => 'stale_name_from_before',
		);
		$this->assertSame(
			array(
				'in_hours_template_name'     => 'contact_form_reply',
				'out_of_hours_template_name' => 'contact_form_reply',
			),
			Out_Of_Hours_Reply::payload_fields( $meta )
		);
	}

	public function test_different_template_sends_the_out_of_hours_name(): void {
		$meta = array(
			'out_of_hours_reply'         => 'different',
			'in_hours_template_name'     => ' contact_form_reply ',
			'out_of_hours_template_name' => ' closed_reply ',
		);
		$this->assertSame(
			array(
				'in_hours_template_name'     => 'contact_form_reply',
				'out_of_hours_template_name' => 'closed_reply',
			),
			Out_Of_Hours_Reply::payload_fields( $meta )
		);
	}

	public function test_dont_reply_leaves_the_out_of_hours_field_out(): void {
		$meta = array(
			'out_of_hours_reply'         => 'none',
			'in_hours_template_name'     => 'contact_form_reply',
			'out_of_hours_template_name' => 'closed_reply',
		);
		$this->assertSame(
			array( 'in_hours_template_name' => 'contact_form_reply' ),
			Out_Of_Hours_Reply::payload_fields( $meta )
		);
	}

	public function test_no_templates_sends_no_template_fields(): void {
		$this->assertSame( array(), Out_Of_Hours_Reply::payload_fields( array( 'out_of_hours_reply' => 'same' ) ) );
	}

	public function test_different_template_alone_sends_only_out_of_hours(): void {
		$meta = array(
			'out_of_hours_reply'         => 'different',
			'in_hours_template_name'     => '',
			'out_of_hours_template_name' => 'closed_reply',
		);
		$this->assertSame(
			array( 'out_of_hours_template_name' => 'closed_reply' ),
			Out_Of_Hours_Reply::payload_fields( $meta )
		);
	}

	// --- Default and 1.1.x feeds (no stored choice) ---

	public function test_new_feed_defaults_to_same_template(): void {
		$this->assertSame( 'same', Out_Of_Hours_Reply::choice( array() ) );
	}

	public function test_1_1_feed_with_in_hours_only_reads_as_same_template(): void {
		$meta = array( 'in_hours_template_name' => 'contact_form_reply', 'out_of_hours_template_name' => '' );
		$this->assertSame( 'same', Out_Of_Hours_Reply::choice( $meta ) );
		$this->assertSame(
			array(
				'in_hours_template_name'     => 'contact_form_reply',
				'out_of_hours_template_name' => 'contact_form_reply',
			),
			Out_Of_Hours_Reply::payload_fields( $meta )
		);
	}

	public function test_1_1_feed_with_both_slots_different_reads_as_different_template(): void {
		$meta = array( 'in_hours_template_name' => 'contact_form_reply', 'out_of_hours_template_name' => 'closed_reply' );
		$this->assertSame( 'different', Out_Of_Hours_Reply::choice( $meta ) );
	}

	public function test_1_1_feed_with_both_slots_the_same_reads_as_same_template(): void {
		$meta = array( 'in_hours_template_name' => 'contact_form_reply', 'out_of_hours_template_name' => ' contact_form_reply ' );
		$this->assertSame( 'same', Out_Of_Hours_Reply::choice( $meta ) );
	}

	public function test_1_1_feed_with_out_of_hours_only_reads_as_different_template(): void {
		$meta = array( 'in_hours_template_name' => '', 'out_of_hours_template_name' => 'closed_reply' );
		$this->assertSame( 'different', Out_Of_Hours_Reply::choice( $meta ) );
	}

	public function test_unrecognised_stored_choice_is_read_from_the_templates(): void {
		$meta = array(
			'out_of_hours_reply'         => 'sometimes',
			'in_hours_template_name'     => 'contact_form_reply',
			'out_of_hours_template_name' => 'closed_reply',
		);
		$this->assertSame( 'different', Out_Of_Hours_Reply::choice( $meta ) );
	}

	public function test_stored_choice_wins_over_what_the_templates_imply(): void {
		$meta = array(
			'out_of_hours_reply'         => 'none',
			'in_hours_template_name'     => 'contact_form_reply',
			'out_of_hours_template_name' => '',
		);
		$this->assertSame( 'none', Out_Of_Hours_Reply::choice( $meta ) );
	}

	// --- Saving: "different template" needs a template ---

	public function test_empty_out_of_hours_template_is_refused_with_a_message(): void {
		$this->assertSame( 'Enter the name of the template to send outside business hours.', Out_Of_Hours_Reply::out_of_hours_template_error( '' ) );
		$this->assertSame( 'Enter the name of the template to send outside business hours.', Out_Of_Hours_Reply::out_of_hours_template_error( '   ' ) );
		$this->assertSame( 'Enter the name of the template to send outside business hours.', Out_Of_Hours_Reply::out_of_hours_template_error( null ) );
	}

	public function test_named_out_of_hours_template_is_not_refused(): void {
		$this->assertNull( Out_Of_Hours_Reply::out_of_hours_template_error( 'closed_reply' ) );
	}
}

<?php
/**
 * Unit tests for Marketing_Opt_In (BRO-2014).
 *
 * Fixture forms only: no real person's data.
 *
 * @package BroadcasterGF
 */

declare(strict_types=1);

use BroadcasterGF\GF\Marketing_Opt_In;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BroadcasterGF\GF\Marketing_Opt_In
 */
final class MarketingOptInTest extends TestCase {

	private const REF = 'https://example.test/wp-admin/admin.php?page=gf_entries&view=entry&id=3&lid=41';

	private function checkbox( array $choices, int $id = 5, string $label = 'Keep in touch' ): array {
		$inputs = array();
		$n      = 0;
		foreach ( $choices as $i => $c ) {
			++$n;
			if ( 0 === $n % 10 ) {
				++$n; // Gravity Forms skips x.10, x.20...
			}
			$inputs[] = array( 'id' => $id . '.' . $n, 'label' => $c['text'] );
		}
		return array( 'id' => $id, 'type' => 'checkbox', 'label' => $label, 'choices' => $choices, 'inputs' => $inputs );
	}

	private function consent( int $id = 7, string $box = 'Send me offers by WhatsApp' ): array {
		return array(
			'id'            => $id,
			'type'          => 'consent',
			'label'         => 'Marketing',
			'checkboxLabel' => $box,
			'inputs'        => array( array( 'id' => $id . '.1' ), array( 'id' => $id . '.2', 'isHidden' => true ), array( 'id' => $id . '.3', 'isHidden' => true ) ),
		);
	}

	// --- Unmapped: nothing at all ---

	public function test_unmapped_sends_none_of_the_fields(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Yes', 'value' => 'Yes' ) ) ) );
		$this->assertSame( array(), Marketing_Opt_In::payload_fields( '', $fields, array( '5.1' => 'Yes' ), self::REF ) );
		$this->assertSame( array(), Marketing_Opt_In::payload_fields( '  ', $fields, array( '5.1' => 'Yes' ), self::REF ) );
		$this->assertNull( Marketing_Opt_In::mapping_error( '', $fields ) );
	}

	// --- A checkbox choice ---

	public function test_a_ticked_checkbox_choice_is_true_with_its_words_and_the_entry_link(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Tell me about offers', 'value' => 'offers' ), array( 'text' => 'Other', 'value' => 'other' ) ) ) );

		$this->assertSame(
			array(
				'marketing_opt_in'            => true,
				'marketing_opt_in_wording'    => 'Tell me about offers',
				'marketing_opt_in_prechecked' => false,
				'marketing_opt_in_reference'  => self::REF,
			),
			Marketing_Opt_In::payload_fields( '5.1', $fields, array( '5.1' => 'offers', '5.2' => '' ), self::REF )
		);
	}

	public function test_an_unticked_checkbox_choice_is_false_with_the_same_words(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Tell me about offers', 'value' => 'offers' ), array( 'text' => 'Other', 'value' => 'other' ) ) ) );
		$out    = Marketing_Opt_In::payload_fields( '5.1', $fields, array( '5.1' => '', '5.2' => 'other' ), self::REF );

		$this->assertFalse( $out['marketing_opt_in'] );
		$this->assertSame( 'Tell me about offers', $out['marketing_opt_in_wording'] );
		$this->assertSame( self::REF, $out['marketing_opt_in_reference'] );
	}

	public function test_the_second_choice_reads_its_own_input_and_words(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Newsletter', 'value' => 'n' ), array( 'text' => 'WhatsApp offers', 'value' => 'w' ) ) ) );

		$out = Marketing_Opt_In::payload_fields( '5.2', $fields, array( '5.1' => 'n', '5.2' => '' ), self::REF );
		$this->assertFalse( $out['marketing_opt_in'] );
		$this->assertSame( 'WhatsApp offers', $out['marketing_opt_in_wording'] );
	}

	public function test_a_choice_past_the_ninth_follows_gravity_forms_input_numbering(): void {
		$choices = array();
		for ( $i = 1; $i <= 10; $i++ ) {
			$choices[] = array( 'text' => 'Choice ' . $i, 'value' => 'c' . $i );
		}
		$fields = array( $this->checkbox( $choices ) );

		// The tenth choice is input 5.11.
		$out = Marketing_Opt_In::payload_fields( '5.11', $fields, array( '5.11' => 'c10' ), self::REF );
		$this->assertTrue( $out['marketing_opt_in'] );
		$this->assertSame( 'Choice 10', $out['marketing_opt_in_wording'] );
	}

	public function test_a_box_the_business_set_to_start_ticked_says_so(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Yes please', 'value' => 'y', 'isSelected' => true ) ) ) );

		$left  = Marketing_Opt_In::payload_fields( '5.1', $fields, array( '5.1' => 'y' ), self::REF );
		$unset = Marketing_Opt_In::payload_fields( '5.1', $fields, array( '5.1' => '' ), self::REF );
		$this->assertSame( array( true, true ), array( $left['marketing_opt_in'], $left['marketing_opt_in_prechecked'] ) );
		$this->assertSame( array( false, true ), array( $unset['marketing_opt_in'], $unset['marketing_opt_in_prechecked'] ) );
	}

	public function test_the_whole_field_works_only_when_it_is_one_box(): void {
		$one = array( $this->checkbox( array( array( 'text' => 'Yes please', 'value' => 'y' ) ) ) );
		$this->assertTrue( Marketing_Opt_In::payload_fields( '5', $one, array( '5.1' => 'y' ), self::REF )['marketing_opt_in'] );
		$this->assertNull( Marketing_Opt_In::mapping_error( '5', $one ) );

		$two = array( $this->checkbox( array( array( 'text' => 'A', 'value' => 'a' ), array( 'text' => 'B', 'value' => 'b' ) ) ) );
		$this->assertSame( array(), Marketing_Opt_In::payload_fields( '5', $two, array( '5.1' => 'a' ), self::REF ) );
		$this->assertStringContainsString( 'more than one box', (string) Marketing_Opt_In::mapping_error( '5', $two ) );
	}

	// --- A consent field ---

	public function test_a_ticked_consent_field_sends_the_text_the_visitor_saw(): void {
		$fields = array( $this->consent() );
		// The entry keeps the checkbox text shown at the time; the field's label may have changed since.
		$entry = array( '7.1' => '1', '7.2' => 'Send me offers by WhatsApp (as shown)', '7.3' => '4' );

		$this->assertSame(
			array(
				'marketing_opt_in'            => true,
				'marketing_opt_in_wording'    => 'Send me offers by WhatsApp (as shown)',
				'marketing_opt_in_prechecked' => false,
				'marketing_opt_in_reference'  => self::REF,
			),
			Marketing_Opt_In::payload_fields( '7.1', $fields, $entry, self::REF )
		);
	}

	public function test_an_unticked_consent_field_is_false_with_the_fields_checkbox_text(): void {
		$fields = array( $this->consent() );
		$out    = Marketing_Opt_In::payload_fields( '7', $fields, array( '7.1' => '', '7.2' => '', '7.3' => '' ), self::REF );

		$this->assertFalse( $out['marketing_opt_in'] );
		$this->assertSame( 'Send me offers by WhatsApp', $out['marketing_opt_in_wording'] );
		$this->assertFalse( $out['marketing_opt_in_prechecked'] );
	}

	public function test_a_consent_fields_text_or_description_input_is_not_the_box(): void {
		$fields = array( $this->consent() );
		foreach ( array( '7.2', '7.3' ) as $mapped ) {
			$this->assertSame( array(), Marketing_Opt_In::payload_fields( $mapped, $fields, array( '7.1' => '1' ), self::REF ), $mapped );
			$this->assertNotNull( Marketing_Opt_In::mapping_error( $mapped, $fields ), $mapped );
		}
		$this->assertNull( Marketing_Opt_In::mapping_error( '7.1', $fields ) );
		$this->assertNull( Marketing_Opt_In::mapping_error( '7', $fields ) );
	}

	// --- What is not a box ---

	public function test_another_field_type_or_a_field_no_longer_on_the_form_sends_nothing(): void {
		$fields = array( array( 'id' => 2, 'type' => 'text', 'label' => 'Name' ) );
		$this->assertSame( array(), Marketing_Opt_In::payload_fields( '2', $fields, array( '2' => 'x' ), self::REF ) );
		$this->assertNotNull( Marketing_Opt_In::mapping_error( '2', $fields ) );
		$this->assertSame( array(), Marketing_Opt_In::payload_fields( '9.1', $fields, array( '9.1' => 'x' ), self::REF ) );
		$this->assertNotNull( Marketing_Opt_In::mapping_error( '9.1', $fields ) );
	}

	// --- The words, and the limits Broadcaster enforces with 422 ---

	public function test_markup_in_the_label_is_sent_as_the_words_read(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Yes, send me <a href="https://example.test/privacy">offers</a> &amp; news', 'value' => 'y' ) ) ) );
		$this->assertSame( 'Yes, send me offers & news', Marketing_Opt_In::payload_fields( '5.1', $fields, array( '5.1' => 'y' ), self::REF )['marketing_opt_in_wording'] );
	}

	public function test_wording_is_cut_to_255_characters_not_bytes(): void {
		$long   = str_repeat( 'é', 300 );
		$fields = array( $this->checkbox( array( array( 'text' => $long, 'value' => 'y' ) ) ) );
		$out    = Marketing_Opt_In::payload_fields( '5.1', $fields, array( '5.1' => 'y' ), self::REF );

		$this->assertSame( 255, mb_strlen( $out['marketing_opt_in_wording'], 'UTF-8' ) );
		$this->assertTrue( mb_check_encoding( $out['marketing_opt_in_wording'], 'UTF-8' ) );
	}

	public function test_the_reference_is_cut_to_500(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Yes', 'value' => 'y' ) ) ) );
		$out    = Marketing_Opt_In::payload_fields( '5.1', $fields, array( '5.1' => 'y' ), 'https://example.test/' . str_repeat( 'a', 600 ) );
		$this->assertSame( 500, strlen( $out['marketing_opt_in_reference'] ) );
	}

	public function test_a_box_with_no_words_falls_back_to_the_field_label_then_sends_nothing(): void {
		$labelled = array( $this->checkbox( array( array( 'text' => '  ', 'value' => 'y' ) ), 5, 'Marketing messages' ) );
		$this->assertSame( 'Marketing messages', Marketing_Opt_In::payload_fields( '5.1', $labelled, array( '5.1' => 'y' ), self::REF )['marketing_opt_in_wording'] );

		// Broadcaster requires the words with the answer: sending the answer alone would be refused, losing the enquiry.
		$bare = array( $this->checkbox( array( array( 'text' => '', 'value' => 'y' ) ), 5, '' ) );
		$this->assertSame( array(), Marketing_Opt_In::payload_fields( '5.1', $bare, array( '5.1' => 'y' ), self::REF ) );
	}

	public function test_no_reference_leaves_the_key_out(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Yes', 'value' => 'y' ) ) ) );
		$this->assertArrayNotHasKey( 'marketing_opt_in_reference', Marketing_Opt_In::payload_fields( '5.1', $fields, array( '5.1' => 'y' ), '' ) );
	}

	public function test_the_entry_reference_is_the_entry_admin_url(): void {
		$this->assertSame(
			'https://example.test/wp-admin/admin.php?page=gf_entries&view=entry&id=3&lid=41',
			Marketing_Opt_In::entry_reference( 'https://example.test/wp-admin/admin.php', 3, 41 )
		);
	}
}

<?php
/**
 * Unit tests for Marketing_Opt_In (BRO-2014).
 *
 * Fixture forms only: no real person's data. Real GF_Field objects are covered by the wp-env probe
 * (tests/probes/marketing-opt-in-probe.php).
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

	private function checkbox( array $choices, int $id = 5, string $label = 'Keep in touch', string $type = 'checkbox' ): array {
		$inputs = array();
		$n      = 0;
		foreach ( $choices as $c ) {
			++$n;
			if ( 0 === $n % 10 ) {
				++$n; // Gravity Forms skips x.10, x.20...
			}
			$inputs[] = array( 'id' => $id . '.' . $n, 'label' => $c['text'] );
		}
		return array( 'id' => $id, 'type' => $type, 'inputType' => 'checkbox', 'label' => $label, 'choices' => $choices, 'inputs' => $inputs );
	}

	private function consent( int $id = 7, string $box = 'Send me offers by WhatsApp' ): array {
		return array(
			'id'            => $id,
			'type'          => 'consent',
			'inputType'     => 'consent',
			'label'         => 'Marketing',
			'checkboxLabel' => $box,
			'choices'       => array( array( 'text' => 'Checked', 'value' => '1', 'isSelected' => false ) ),
			'inputs'        => array( array( 'id' => $id . '.1' ), array( 'id' => $id . '.2', 'isHidden' => true ), array( 'id' => $id . '.3', 'isHidden' => true ) ),
		);
	}

	/** Snapshot at submission as the add-on takes it: choice saved with the feed, not hidden, rendered = submitted. */
	private function snap( string $mapped, array $fields, array $entry, ?array $saved = null, bool $hidden = false, ?array $shown = null ): array {
		$saved = $saved ?? Marketing_Opt_In::choice_setting( $mapped, $fields );
		return Marketing_Opt_In::snapshot(
			$mapped,
			$saved,
			$fields,
			$shown ?? $fields,
			$entry,
			function () use ( $hidden ) {
				return $hidden;
			}
		);
	}

	private function send( string $mapped, array $fields, array $entry, ?array $saved = null ): array {
		return Marketing_Opt_In::payload_fields( $this->snap( $mapped, $fields, $entry, $saved ), self::REF );
	}

	// --- Unmapped, or no snapshot: nothing at all ---

	public function test_unmapped_sends_none_of_the_fields(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Yes', 'value' => 'Yes' ) ) ) );
		$this->assertSame( array(), $this->send( '', $fields, array( '5.1' => 'Yes' ) ) );
		$this->assertSame( array(), $this->send( '  ', $fields, array( '5.1' => 'Yes' ) ) );
		$this->assertNull( Marketing_Opt_In::mapping_error( '', $fields ) );
	}

	public function test_without_a_snapshot_from_submission_nothing_is_sent(): void {
		// An entry made some other way (an import, a feed added after the entry) has no answer taken at submission.
		$this->assertSame( array(), Marketing_Opt_In::payload_fields( null, self::REF ) );
		$this->assertSame( array(), Marketing_Opt_In::payload_fields( '', self::REF ) );
		$this->assertSame( array(), Marketing_Opt_In::payload_fields( array( 'state' => 'ok', 'wording' => 'Yes' ), self::REF ) );
		// Only a snapshot that holds an answer is sent, whatever else it carries.
		foreach ( array( null, Marketing_Opt_In::HIDDEN, Marketing_Opt_In::NOT_FOUND, Marketing_Opt_In::NO_WORDS, Marketing_Opt_In::BUNDLED ) as $state ) {
			$snapshot = array( 'ticked' => true, 'wording' => 'Yes', 'prechecked' => false ) + ( null === $state ? array() : array( 'state' => $state ) );
			$this->assertSame( array(), Marketing_Opt_In::payload_fields( $snapshot, self::REF ), (string) $state );
		}
		$this->assertTrue( Marketing_Opt_In::payload_fields( array( 'state' => Marketing_Opt_In::OK, 'ticked' => true, 'wording' => 'Yes', 'prechecked' => false ), self::REF )['marketing_opt_in'] );
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
			$this->send( '5.1', $fields, array( '5.1' => 'offers', '5.2' => '' ) )
		);
	}

	public function test_an_unticked_checkbox_choice_is_false_with_the_same_words(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Tell me about offers', 'value' => 'offers' ), array( 'text' => 'Other', 'value' => 'other' ) ) ) );
		$out    = $this->send( '5.1', $fields, array( '5.1' => '', '5.2' => 'other' ) );

		$this->assertFalse( $out['marketing_opt_in'] );
		$this->assertSame( 'Tell me about offers', $out['marketing_opt_in_wording'] );
		$this->assertSame( self::REF, $out['marketing_opt_in_reference'] );
	}

	public function test_the_second_choice_reads_its_own_answer_and_words(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Newsletter', 'value' => 'n' ), array( 'text' => 'WhatsApp offers', 'value' => 'w' ) ) ) );

		$out = $this->send( '5.2', $fields, array( '5.1' => 'n', '5.2' => '' ) );
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
		$this->assertSame( array( 'value' => 'c10', 'text' => 'Choice 10' ), Marketing_Opt_In::choice_setting( '5.11', $fields ) );
		$out = $this->send( '5.11', $fields, array( '5.11' => 'c10' ) );
		$this->assertTrue( $out['marketing_opt_in'] );
		$this->assertSame( 'Choice 10', $out['marketing_opt_in_wording'] );
	}

	// --- Finding 1: the saved input id moves when the editor renumbers ---

	public function test_a_box_moved_in_the_editor_is_found_by_its_value_not_its_old_input_id(): void {
		// Saved when 5.1 was "Send me offers"; a terms box was then added above it, and Gravity Forms renumbered
		// the inputs by position. The feed still says 5.1, which is now the terms box.
		$before = array( $this->checkbox( array( array( 'text' => 'Send me offers', 'value' => 'offers' ) ) ) );
		$saved  = Marketing_Opt_In::choice_setting( '5.1', $before );
		$after  = array( $this->checkbox( array( array( 'text' => 'I accept the terms', 'value' => 'terms' ), array( 'text' => 'Send me offers', 'value' => 'offers' ) ) ) );

		// Terms ticked, offers not: marketing is not agreed, and the words are the marketing box's.
		$terms_only = $this->send( '5.1', $after, array( '5.1' => 'terms', '5.2' => '' ), $saved );
		$this->assertSame( array( false, 'Send me offers' ), array( $terms_only['marketing_opt_in'], $terms_only['marketing_opt_in_wording'] ) );

		$offers = $this->send( '5.1', $after, array( '5.1' => '', '5.2' => 'offers' ), $saved );
		$this->assertSame( array( true, 'Send me offers' ), array( $offers['marketing_opt_in'], $offers['marketing_opt_in_wording'] ) );

		// And the feed page says it moved, so the admin chooses the box again.
		$this->assertNotNull( Marketing_Opt_In::moved_notice( '5.1', $saved, $after ) );
		$this->assertNull( Marketing_Opt_In::moved_notice( '5.2', $saved, $after ) );
		$this->assertNull( Marketing_Opt_In::moved_notice( '5.1', $saved, $before ) );
	}

	public function test_a_box_removed_or_made_ambiguous_sends_nothing_and_is_reported(): void {
		$saved = array( 'value' => 'offers', 'text' => 'Send me offers' );

		$removed = array( $this->checkbox( array( array( 'text' => 'I accept the terms', 'value' => 'terms' ) ) ) );
		$this->assertSame( Marketing_Opt_In::NOT_FOUND, $this->snap( '5.1', $removed, array( '5.1' => 'terms' ), $saved )['state'] );
		$this->assertSame( array(), $this->send( '5.1', $removed, array( '5.1' => 'terms' ), $saved ) );
		$this->assertStringContainsString( 'Send me offers', (string) Marketing_Opt_In::moved_notice( '5.1', $saved, $removed ) );

		$twice = array( $this->checkbox( array( array( 'text' => 'Send me offers', 'value' => 'offers' ), array( 'text' => 'Offers again', 'value' => 'offers' ) ) ) );
		$this->assertSame( Marketing_Opt_In::NOT_FOUND, $this->snap( '5.1', $twice, array( '5.1' => 'offers' ), $saved )['state'] );
		$this->assertNotNull( Marketing_Opt_In::mapping_error( '5.1', $twice ) );

		// A feed with no saved box (saved some other way) sends nothing and asks for a re-save.
		$fine = array( $this->checkbox( array( array( 'text' => 'Send me offers', 'value' => 'offers' ) ) ) );
		$this->assertSame( Marketing_Opt_In::NOT_FOUND, $this->snap( '5.1', $fine, array( '5.1' => 'offers' ), array() )['state'] );
		$this->assertNotNull( Marketing_Opt_In::moved_notice( '5.1', array(), $fine ) );
	}

	public function test_a_keyed_choice_reordered_in_the_editor_keeps_its_own_words(): void {
		// Gravity Forms 2.9+ Multiple Choice fields link inputs to choices by key. Moving a choice in the form
		// editor reorders the choices but never the inputs, so position no longer pairs them.
		$fields = array(
			array(
				'id'        => 6,
				'type'      => 'multi_choice',
				'inputType' => 'checkbox',
				'label'     => 'Keep in touch',
				'choices'   => array(
					array( 'text' => 'No marketing please', 'value' => 'b', 'key' => 'kb' ),
					array( 'text' => 'Yes send offers', 'value' => 'a', 'key' => 'ka', 'isSelected' => true ),
				),
				'inputs'    => array(
					array( 'id' => '6.1', 'label' => 'Yes send offers', 'key' => 'ka' ),
					array( 'id' => '6.2', 'label' => 'No marketing please', 'key' => 'kb' ),
				),
			),
		);

		$this->assertSame( array( 'value' => 'a', 'text' => 'Yes send offers' ), Marketing_Opt_In::choice_setting( '6.1', $fields ) );
		$yes = $this->send( '6.1', $fields, array( '6.1' => 'a', '6.2' => '' ) );
		$this->assertSame( array( true, 'Yes send offers', true ), array( $yes['marketing_opt_in'], $yes['marketing_opt_in_wording'], $yes['marketing_opt_in_prechecked'] ) );

		$no = $this->send( '6.2', $fields, array( '6.1' => '', '6.2' => 'b' ) );
		$this->assertSame( array( true, 'No marketing please', false ), array( $no['marketing_opt_in'], $no['marketing_opt_in_wording'], $no['marketing_opt_in_prechecked'] ) );

		// A key with no matching choice is not a box: guessing by position could send the wrong words.
		$fields[0]['inputs'][0]['key'] = 'gone';
		$this->assertNotNull( Marketing_Opt_In::mapping_error( '6.1', $fields ) );
		$this->assertSame( array(), Marketing_Opt_In::choice_setting( '6.1', $fields ) );
	}

	// --- Findings 2 and 3: the answer is the one taken at submission ---

	public function test_the_snapshot_keeps_the_words_shown_however_the_form_changes_later(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Send me offers', 'value' => 'offers' ) ) ) );
		$snap   = $this->snap( '5.1', $fields, array( '5.1' => 'offers' ) );

		// What the feed sends depends on the snapshot alone; the form is not an argument any more.
		$this->assertSame( 'Send me offers', Marketing_Opt_In::payload_fields( $snap, self::REF )['marketing_opt_in_wording'] );
	}

	public function test_the_words_are_those_rendered_when_a_filter_changes_them(): void {
		// A translation plugin, or code on gform_pre_render, changes the words the visitor reads.
		$fields = array( $this->checkbox( array( array( 'text' => 'Send me offers', 'value' => 'offers' ) ) ) );
		$shown  = array( $this->checkbox( array( array( 'text' => 'Envoyez-moi des offres', 'value' => 'offers' ) ) ) );

		$snap = $this->snap( '5.1', $fields, array( '5.1' => 'offers' ), null, false, $shown );
		$this->assertSame( 'Envoyez-moi des offres', $snap['wording'] );
	}

	public function test_a_box_that_may_have_started_ticked_says_so(): void {
		$set = array( $this->checkbox( array( array( 'text' => 'Yes please', 'value' => 'y', 'isSelected' => true ) ) ) );
		$this->assertSame( array( true, true ), array( $this->send( '5.1', $set, array( '5.1' => 'y' ) )['marketing_opt_in'], $this->send( '5.1', $set, array( '5.1' => 'y' ) )['marketing_opt_in_prechecked'] ) );
		$this->assertSame( array( false, true ), array( $this->send( '5.1', $set, array( '5.1' => '' ) )['marketing_opt_in'], $this->send( '5.1', $set, array( '5.1' => '' ) )['marketing_opt_in_prechecked'] ) );

		// Dynamic population can tick the box from the link the visitor followed.
		$populated                         = array( $this->checkbox( array( array( 'text' => 'Yes please', 'value' => 'y' ) ) ) );
		$populated[0]['allowsPrepopulate'] = true;
		$this->assertTrue( $this->send( '5.1', $populated, array( '5.1' => 'y' ) )['marketing_opt_in_prechecked'] );

		// gform_pre_render ticked it.
		$plain  = array( $this->checkbox( array( array( 'text' => 'Yes please', 'value' => 'y' ) ) ) );
		$ticked = array( $this->checkbox( array( array( 'text' => 'Yes please', 'value' => 'y', 'isSelected' => true ) ) ) );
		$this->assertTrue( $this->snap( '5.1', $plain, array( '5.1' => 'y' ), null, false, $ticked )['prechecked'] );

		// Neither: an active tick.
		$this->assertFalse( $this->send( '5.1', $plain, array( '5.1' => 'y' ) )['marketing_opt_in_prechecked'] );
	}

	public function test_saving_the_old_input_id_again_is_refused_only_when_it_now_holds_another_box(): void {
		$before   = array( $this->checkbox( array( array( 'text' => 'Send me offers', 'value' => 'offers' ) ) ) );
		$previous = array( Marketing_Opt_In::SETTING => '5.1', Marketing_Opt_In::CHOICE_SETTING => Marketing_Opt_In::choice_setting( '5.1', $before ) );
		$moved    = array( $this->checkbox( array( array( 'text' => 'I accept the terms', 'value' => 'terms' ), array( 'text' => 'Send me offers', 'value' => 'offers' ) ) ) );

		$this->assertNotNull( Marketing_Opt_In::resave_error( '5.1', $previous, $moved ), 'would bind the feed to the terms box' );
		$this->assertNull( Marketing_Opt_In::resave_error( '5.2', $previous, $moved ), 'the box chosen again' );
		$this->assertNull( Marketing_Opt_In::resave_error( '', $previous, $moved ), 'cleared' );
		$this->assertNull( Marketing_Opt_In::resave_error( '5.1', $previous, $before ), 'nothing moved' );

		// A feed saved without its box (by the API, or an earlier build) can be saved: the save records the box.
		$this->assertNull( Marketing_Opt_In::resave_error( '5.1', array( Marketing_Opt_In::SETTING => '5.1' ), $moved ) );
		$this->assertNull( Marketing_Opt_In::resave_error( '5.1', null, $moved ) );
	}

	public function test_the_notice_says_whether_the_answer_is_still_sent(): void {
		$saved = array( 'value' => 'offers', 'text' => 'Send me offers' );
		$moved = array( $this->checkbox( array( array( 'text' => 'I accept the terms', 'value' => 'terms' ), array( 'text' => 'Send me offers', 'value' => 'offers' ) ) ) );
		$gone  = array( $this->checkbox( array( array( 'text' => 'I accept the terms', 'value' => 'terms' ) ) ) );

		$this->assertStringContainsString( 'still sent', (string) Marketing_Opt_In::moved_notice( '5.1', $saved, $moved ) );
		$this->assertTrue( $this->send( '5.1', $moved, array( '5.2' => 'offers' ), $saved )['marketing_opt_in'] );
		$this->assertStringContainsString( 'no opt-in is sent', (string) Marketing_Opt_In::moved_notice( '5.1', $saved, $gone ) );
		$this->assertSame( array(), $this->send( '5.1', $gone, array( '5.1' => 'terms' ), $saved ) );
	}

	// --- Hidden from the visitor: conditional logic, or a field they never see ---

	public function test_a_field_visitors_never_see_is_refused_and_sends_nothing(): void {
		foreach ( array( 'administrative', 'hidden' ) as $visibility ) {
			// Set to start ticked: Gravity Forms would store the tick though nobody saw the box.
			$fields                  = array( $this->checkbox( array( array( 'text' => 'Send me offers', 'value' => 'offers', 'isSelected' => true ) ) ) );
			$fields[0]['visibility'] = $visibility;
			$this->assertNotNull( Marketing_Opt_In::mapping_error( '5.1', $fields ), $visibility );
			$this->assertSame( Marketing_Opt_In::HIDDEN, $this->snap( '5.1', $fields, array( '5.1' => 'offers' ), array( 'value' => 'offers', 'text' => 'Send me offers' ) )['state'], $visibility );

			$consent                  = array( $this->consent() );
			$consent[0]['visibility'] = $visibility;
			$this->assertNotNull( Marketing_Opt_In::mapping_error( '7', $consent ), $visibility );
			$this->assertSame( Marketing_Opt_In::HIDDEN, $this->snap( '7', $consent, array( '7.1' => '1' ) )['state'], $visibility );
		}
		$visible                  = array( $this->checkbox( array( array( 'text' => 'Send me offers', 'value' => 'offers' ) ) ) );
		$visible[0]['visibility'] = 'visible';
		$this->assertNull( Marketing_Opt_In::mapping_error( '5.1', $visible ) );
	}

	// --- Finding 4: hidden by conditional logic ---

	public function test_a_box_hidden_by_conditional_logic_sends_nothing(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Send me offers', 'value' => 'offers' ) ) ) );
		$snap   = $this->snap( '5.1', $fields, array(), null, true );

		$this->assertSame( Marketing_Opt_In::HIDDEN, $snap['state'] );
		$this->assertSame( array(), Marketing_Opt_In::payload_fields( $snap, self::REF ) );
		$this->assertSame( Marketing_Opt_In::HIDDEN, Marketing_Opt_In::snapshot( '7', array(), array( $this->consent() ), array( $this->consent() ), array(), function () { return true; } )['state'] );
	}

	public function test_the_whole_field_works_only_when_it_is_one_box(): void {
		$one = array( $this->checkbox( array( array( 'text' => 'Yes please', 'value' => 'y' ) ) ) );
		$this->assertTrue( $this->send( '5', $one, array( '5.1' => 'y' ) )['marketing_opt_in'] );
		$this->assertNull( Marketing_Opt_In::mapping_error( '5', $one ) );

		$two = array( $this->checkbox( array( array( 'text' => 'A', 'value' => 'a' ), array( 'text' => 'B', 'value' => 'b' ) ) ) );
		$this->assertSame( array(), Marketing_Opt_In::choice_setting( '5', $two ) );
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
			$this->send( '7.1', $fields, $entry )
		);
	}

	public function test_an_unticked_consent_field_is_false_with_the_fields_checkbox_text(): void {
		$fields = array( $this->consent() );
		$out    = $this->send( '7', $fields, array( '7.1' => '', '7.2' => '', '7.3' => '' ) );

		$this->assertFalse( $out['marketing_opt_in'] );
		$this->assertSame( 'Send me offers by WhatsApp', $out['marketing_opt_in_wording'] );
		$this->assertFalse( $out['marketing_opt_in_prechecked'] );
	}

	public function test_a_consent_fields_text_or_description_input_is_not_the_box(): void {
		$fields = array( $this->consent() );
		foreach ( array( '7.2', '7.3' ) as $mapped ) {
			$this->assertSame( array(), $this->send( $mapped, $fields, array( '7.1' => '1' ) ), $mapped );
			$this->assertNotNull( Marketing_Opt_In::mapping_error( $mapped, $fields ), $mapped );
		}
		$this->assertNull( Marketing_Opt_In::mapping_error( '7.1', $fields ) );
		$this->assertNull( Marketing_Opt_In::mapping_error( '7', $fields ) );
		$this->assertSame( array(), Marketing_Opt_In::choice_setting( '7', $fields ) );
		$this->assertNull( Marketing_Opt_In::moved_notice( '7', array(), $fields ) );
	}

	// --- Finding 5: what is not an opt-in box ---

	public function test_product_survey_quiz_and_poll_checkboxes_are_refused(): void {
		foreach ( array( 'option', 'survey', 'quiz', 'poll' ) as $type ) {
			$fields = array( $this->checkbox( array( array( 'text' => 'Gift wrap', 'value' => 'gift' ) ), 5, 'Extras', $type ) );
			$this->assertNotNull( Marketing_Opt_In::mapping_error( '5.1', $fields ), $type );
			$this->assertSame( array(), $this->send( '5.1', $fields, array( '5.1' => 'gift' ), array( 'value' => 'gift', 'text' => 'Gift wrap' ) ), $type );
		}
		// A Multiple Choice field set to radio buttons is not a box either.
		$radio = array( $this->checkbox( array( array( 'text' => 'Yes', 'value' => 'y' ) ), 5, 'Pick', 'multi_choice' ) );
		$radio[0]['inputType'] = 'radio';
		$this->assertNotNull( Marketing_Opt_In::mapping_error( '5', $radio ) );

		$this->assertNull( Marketing_Opt_In::mapping_error( '5.1', array( $this->checkbox( array( array( 'text' => 'Yes', 'value' => 'y' ) ), 5, 'Pick', 'multi_choice' ) ) ) );
	}

	public function test_another_field_type_or_a_field_no_longer_on_the_form_sends_nothing(): void {
		$fields = array( array( 'id' => 2, 'type' => 'text', 'label' => 'Name' ) );
		$this->assertSame( array(), $this->send( '2', $fields, array( '2' => 'x' ) ) );
		$this->assertNotNull( Marketing_Opt_In::mapping_error( '2', $fields ) );
		$this->assertSame( array(), $this->send( '9.1', $fields, array( '9.1' => 'x' ), array( 'value' => 'x', 'text' => 'X' ) ) );
		$this->assertNotNull( Marketing_Opt_In::mapping_error( '9.1', $fields ) );
	}

	// --- Finding 6, the words, and the limits Broadcaster enforces with 422 ---

	public function test_markup_in_the_label_is_sent_as_the_words_read(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Yes, send me <a href="https://example.test/privacy">offers</a> &amp; news<script>x()</script>', 'value' => 'y' ) ) ) );
		$this->assertSame( 'Yes, send me offers & news', $this->send( '5.1', $fields, array( '5.1' => 'y' ) )['marketing_opt_in_wording'] );
	}

	public function test_a_less_than_sign_that_starts_no_tag_is_kept(): void {
		$this->assertSame( 'Yes, I <3 your offers. Prices < £5 > none', Marketing_Opt_In::plain( 'Yes, I <3 your offers. Prices < £5 > none' ) );
		$this->assertSame( 'a < b > c', Marketing_Opt_In::plain( 'a < b > c' ) );
		$this->assertSame( 'a <b> c', Marketing_Opt_In::plain( 'a &lt;b&gt; c' ) );
		$this->assertSame( 'one two', Marketing_Opt_In::plain( 'one<br/>two<!-- note -->' ) );
	}

	public function test_words_shown_as_literal_markup_reach_broadcaster_as_shown(): void {
		// The label "&lt;b&gt;offers&lt;/b&gt;" shows the visitor the characters <b>offers</b>.
		$fields = array( $this->checkbox( array( array( 'text' => 'Yes, &lt;b&gt;offers&lt;/b&gt; &amp;amp; more', 'value' => 'y' ) ) ) );
		$this->assertSame( 'Yes, <b>offers</b> &amp; more', $this->send( '5.1', $fields, array( '5.1' => 'y' ) )['marketing_opt_in_wording'] );
	}

	public function test_wording_is_cut_to_255_characters_not_bytes(): void {
		$long   = str_repeat( 'é', 300 );
		$fields = array( $this->checkbox( array( array( 'text' => $long, 'value' => 'y' ) ) ) );
		$out    = $this->send( '5.1', $fields, array( '5.1' => 'y' ) );

		$this->assertSame( 255, mb_strlen( $out['marketing_opt_in_wording'], 'UTF-8' ) );
		$this->assertTrue( mb_check_encoding( $out['marketing_opt_in_wording'], 'UTF-8' ) );
	}

	public function test_the_reference_is_cut_to_500(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Yes', 'value' => 'y' ) ) ) );
		$out    = Marketing_Opt_In::payload_fields( $this->snap( '5.1', $fields, array( '5.1' => 'y' ) ), 'https://example.test/' . str_repeat( 'a', 600 ) );
		$this->assertSame( 500, strlen( $out['marketing_opt_in_reference'] ) );
	}

	public function test_a_box_with_no_words_falls_back_to_the_field_label_then_sends_nothing(): void {
		$labelled = array( $this->checkbox( array( array( 'text' => '  ', 'value' => 'y' ) ), 5, 'Marketing messages' ) );
		$this->assertSame( 'Marketing messages', $this->send( '5.1', $labelled, array( '5.1' => 'y' ) )['marketing_opt_in_wording'] );

		// Broadcaster requires the words with the answer: sending the answer alone would be refused, losing the enquiry.
		$bare = array( $this->checkbox( array( array( 'text' => '', 'value' => 'y' ) ), 5, '' ) );
		$this->assertSame( Marketing_Opt_In::NO_WORDS, $this->snap( '5.1', $bare, array( '5.1' => 'y' ) )['state'] );
		$this->assertSame( array(), $this->send( '5.1', $bare, array( '5.1' => 'y' ) ) );
	}

	public function test_no_reference_leaves_the_key_out(): void {
		$fields = array( $this->checkbox( array( array( 'text' => 'Yes', 'value' => 'y' ) ) ) );
		$out    = Marketing_Opt_In::payload_fields( $this->snap( '5.1', $fields, array( '5.1' => 'y' ) ), '' );
		// The answer is still sent: only the reference is missing.
		$this->assertTrue( $out['marketing_opt_in'] );
		$this->assertArrayNotHasKey( 'marketing_opt_in_reference', $out );
	}

	public function test_the_entry_reference_is_the_entry_admin_url(): void {
		$this->assertSame(
			'https://example.test/wp-admin/admin.php?page=gf_entries&view=entry&id=3&lid=41',
			Marketing_Opt_In::entry_reference( 'https://example.test/wp-admin/admin.php', 3, 41 )
		);
	}

	// --- Second reader, pre-release notes ---

	public function test_a_checkboxes_field_with_select_all_is_refused_and_sends_nothing(): void {
		// Ticking every box at once is not a specific agreement to marketing.
		$field                    = $this->checkbox( array( array( 'text' => 'Terms', 'value' => 'terms' ), array( 'text' => 'Send me offers', 'value' => 'offers' ) ) );
		$plain                    = array( $field );
		$field['enableSelectAll'] = true;
		$fields                   = array( $field );
		$saved                    = Marketing_Opt_In::choice_setting( '5.2', $plain );
		$this->assertNotNull( Marketing_Opt_In::mapping_error( '5.2', $fields ) );
		$this->assertSame( array(), Marketing_Opt_In::choice_setting( '5.2', $fields ) );
		$this->assertSame( array(), $this->send( '5.2', $fields, array( '5.2' => 'offers' ), $saved ) );
		$this->assertStringContainsString( 'Select All', (string) Marketing_Opt_In::moved_notice( '5.2', $saved, $fields ) );
	}

	public function test_words_changed_in_the_editor_are_noticed_but_still_sent(): void {
		$before = array( $this->checkbox( array( array( 'text' => 'Send me offers', 'value' => 'offers' ) ) ) );
		$saved  = Marketing_Opt_In::choice_setting( '5.1', $before );
		$after  = array( $this->checkbox( array( array( 'text' => 'Send me <b>deals</b>', 'value' => 'offers' ) ) ) );
		$notice = (string) Marketing_Opt_In::moved_notice( '5.1', $saved, $after );
		$this->assertStringContainsString( 'Send me offers', $notice );
		$this->assertStringContainsString( 'still sent', $notice );
		// Saving again records the new words; it is not refused.
		$this->assertNull( Marketing_Opt_In::resave_error( '5.1', array( Marketing_Opt_In::SETTING => '5.1', Marketing_Opt_In::CHOICE_SETTING => $saved ), $after ) );
		// The record holds what the visitor saw.
		$this->assertSame( 'Send me deals', $this->send( '5.1', $after, array( '5.1' => 'offers' ), $saved )['marketing_opt_in_wording'] ?? null );
		// Markup alone changing is not a change of words.
		$same = array( $this->checkbox( array( array( 'text' => '<span>Send me offers</span>', 'value' => 'offers' ) ) ) );
		$this->assertNull( Marketing_Opt_In::moved_notice( '5.1', $saved, $same ) );
	}

	public function test_rendering_works_on_a_copy_so_changes_do_not_reach_the_submitted_form(): void {
		$field = new ArrayObject( array( 'id' => 5, 'label' => 'Keep in touch', 'choices' => array( array( 'text' => 'Yes', 'value' => 'yes' ) ) ) );
		$form  = array( 'id' => 3, 'fields' => array( $field ) );
		$copy  = Marketing_Opt_In::form_copy( $form );
		$copy['fields'][0]['label']   = 'Restez en contact';
		$copy['fields'][0]['choices'] = array();
		$this->assertSame( 'Keep in touch', $field['label'] );
		$this->assertCount( 1, $field['choices'] );
		$this->assertSame( 3, $copy['id'] );
	}
}

<?php
/**
 * Broadcaster Gravity Forms Feed Add-On.
 *
 * Mirrors the Gravity Forms EmailOctopus add-on pattern: a global (un-namespaced)
 * subclass of GFFeedAddOn registered via GFAddOn::register() at gform_loaded.
 * The framework include happens at file scope so the parent class is already
 * loaded by the time this class definition is parsed.
 *
 * BRO-881 ships only the configuration UI: settings schema, list columns, and
 * placeholder/recipient validation. Submission dispatch (process_feed) is a
 * deliberate no-op here and is filled in by BRO-882, which will use
 * BroadcasterGF\Api\Client to post to /api/v1/messages/incoming.
 *
 * @package BroadcasterGF
 */

defined( 'ABSPATH' ) || exit;

\GFForms::include_feed_addon_framework();

/**
 * Broadcaster delivery as a Gravity Forms feed.
 */
class Broadcaster_GF_FeedAddOn extends \GFFeedAddOn {

	// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore -- GFFeedAddOn requires underscore-prefixed framework properties.

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $_version = BROADCASTERGF_VERSION;

	/**
	 * Minimum supported Gravity Forms version.
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = '2.5';

	/**
	 * Add-on slug — must match plugin slug.
	 *
	 * @var string
	 */
	protected $_slug = 'broadcaster-auto-responder-for-gravity-forms';

	/**
	 * Plugin file path relative to the plugins directory.
	 *
	 * @var string
	 */
	protected $_path = 'broadcaster-auto-responder-for-gravity-forms/broadcaster-auto-responder-for-gravity-forms.php';

	/**
	 * Full path to this class file.
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;

	/**
	 * Add-on website.
	 *
	 * @var string
	 */
	protected $_url = 'https://github.com/alanef/plugin-broadcaster-auto-responder-for-gravity-forms-project';

	/**
	 * Display title.
	 *
	 * @var string
	 */
	protected $_title = 'Broadcaster Auto Responder for Gravity Forms';

	/**
	 * Short title shown in the GF feed list tabs and sidebar.
	 *
	 * @var string
	 */
	protected $_short_title = 'Broadcaster';

	// phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Singleton instance.
	 *
	 * @var Broadcaster_GF_FeedAddOn|null
	 */
	private static $instance = null;

	/**
	 * Singleton accessor (GFAddOn pattern).
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Provide the SVG icon shown in the GF sidebar nav for this add-on.
	 */
	public function get_menu_icon() {
		$svg = $this->get_base_path() . '/../../images/menu-icon.svg';
		if ( file_exists( $svg ) ) {
			return file_get_contents( $svg ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		return parent::get_menu_icon();
	}

	/**
	 * Define the per-feed settings UI shown when editing a feed.
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Broadcaster delivery', 'broadcaster-auto-responder-for-gravity-forms' ),
				'fields' => array(
					array(
						'label'    => esc_html__( 'Feed name', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'     => 'text',
						'name'     => 'feedName',
						'class'    => 'medium',
						'required' => true,
					),
					array(
						'label'   => esc_html__( 'Phone field', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'    => 'field_select',
						'name'    => 'phone_field',
						'tooltip' => esc_html__( 'Form field that holds the contact phone number. Map at least one of phone or WhatsApp username.', 'broadcaster-auto-responder-for-gravity-forms' ),
					),
					array(
						'label'   => esc_html__( 'WhatsApp username field', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'    => 'field_select',
						'name'    => 'whatsapp_username_field',
						'tooltip' => esc_html__( 'Form field that holds the contact WhatsApp BSUID/username. Optional if a phone field is mapped.', 'broadcaster-auto-responder-for-gravity-forms' ),
					),
					array(
						'label'   => esc_html__( 'Submitter name field', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'    => 'field_select',
						'name'    => 'submitter_name_field',
						'tooltip' => esc_html__( 'Form field that holds the submitter full name. Optional.', 'broadcaster-auto-responder-for-gravity-forms' ),
					),
					array(
						'label'    => esc_html__( 'Message text', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'     => 'textarea',
						'name'     => 'message_text',
						'class'    => 'medium merge-tag-support mt-position-right',
						'required' => true,
						'tooltip'  => esc_html__( 'Free-form message body. Use Gravity Forms merge tags such as {Message:5}.', 'broadcaster-auto-responder-for-gravity-forms' ),
					),
				),
			),
			array(
				'title'       => esc_html__( 'Auto-response template — in business hours', 'broadcaster-auto-responder-for-gravity-forms' ),
				'description' => esc_html__( 'If only this template is filled, Broadcaster uses it at all times. If both in-hours and out-of-hours are filled, Broadcaster picks based on company business hours. If both are blank, no auto-response is sent.', 'broadcaster-auto-responder-for-gravity-forms' ),
				'fields'      => array(
					array(
						'label'   => esc_html__( 'In-hours template name', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'    => 'text',
						'name'    => 'in_hours_template_name',
						'class'   => 'medium',
						'tooltip' => esc_html__( 'Internal name of an approved Broadcaster template.', 'broadcaster-auto-responder-for-gravity-forms' ),
					),
					array(
						'label'               => esc_html__( 'In-hours template placeholders', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'                => 'textarea',
						'name'                => 'in_hours_template_placeholders',
						'class'               => 'medium merge-tag-support mt-position-right',
						'tooltip'             => esc_html__( 'Comma-separated key:value pairs, e.g. text_1:{Message:5},client_first_name:{Name (First):1.3}', 'broadcaster-auto-responder-for-gravity-forms' ),
						'validation_callback' => array( $this, 'validate_in_hours_placeholders' ),
					),
				),
			),
			array(
				'title'       => esc_html__( 'Auto-response template — out of business hours', 'broadcaster-auto-responder-for-gravity-forms' ),
				'description' => esc_html__( 'Optional. Only used when an in-hours template is also set.', 'broadcaster-auto-responder-for-gravity-forms' ),
				'fields'      => array(
					array(
						'label' => esc_html__( 'Out-of-hours template name', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'  => 'text',
						'name'  => 'out_of_hours_template_name',
						'class' => 'medium',
					),
					array(
						'label'               => esc_html__( 'Out-of-hours template placeholders', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'                => 'textarea',
						'name'                => 'out_of_hours_template_placeholders',
						'class'               => 'medium merge-tag-support mt-position-right',
						'tooltip'             => esc_html__( 'Same format as the in-hours placeholders.', 'broadcaster-auto-responder-for-gravity-forms' ),
						'validation_callback' => array( $this, 'validate_out_of_hours_placeholders' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Live test', 'broadcaster-auto-responder-for-gravity-forms' ),
				'fields' => array(
					array(
						'label'   => esc_html__( 'Test recipient (E.164 phone)', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'    => 'text',
						'name'    => 'test_recipient',
						'class'   => 'medium',
						'tooltip' => esc_html__( 'Phone number to receive a live feed test. The test trigger ships with BRO-882.', 'broadcaster-auto-responder-for-gravity-forms' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Conditional logic', 'broadcaster-auto-responder-for-gravity-forms' ),
				'fields' => array(
					array(
						'label' => esc_html__( 'Conditional logic', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'  => 'feed_condition',
						'name'  => 'feed_condition',
					),
				),
			),
		);
	}

	/**
	 * Columns shown on the per-form feed list.
	 */
	public function feed_list_columns() {
		return array(
			'feedName'                   => esc_html__( 'Name', 'broadcaster-auto-responder-for-gravity-forms' ),
			'in_hours_template_name'     => esc_html__( 'In-hours template', 'broadcaster-auto-responder-for-gravity-forms' ),
			'out_of_hours_template_name' => esc_html__( 'Out-of-hours template', 'broadcaster-auto-responder-for-gravity-forms' ),
		);
	}

	/**
	 * Submission dispatch is implemented by BRO-882. For BRO-881 this is a
	 * deliberate no-op so saved feeds run safely without throwing.
	 *
	 * @param array $feed  Feed configuration.
	 * @param array $entry Form entry.
	 * @param array $form  Form definition.
	 */
	public function process_feed( $feed, $entry, $form ) {
		$this->log_debug( __METHOD__ . '(): dispatch reserved for BRO-882.' );
		return $entry;
	}

	/**
	 * Whole-feed validation: enforce that at least one recipient field is mapped.
	 *
	 * @param array $settings Submitted feed settings.
	 * @return bool
	 */
	public function feed_settings_validation( $settings ) {
		$valid = parent::feed_settings_validation( $settings );

		$phone    = isset( $settings['phone_field'] ) ? $settings['phone_field'] : '';
		$username = isset( $settings['whatsapp_username_field'] ) ? $settings['whatsapp_username_field'] : '';

		if ( '' === $phone && '' === $username ) {
			$this->set_field_error(
				array( 'name' => 'phone_field' ),
				esc_html__( 'Map at least one of phone or WhatsApp username.', 'broadcaster-auto-responder-for-gravity-forms' )
			);
			$valid = false;
		}

		return $valid;
	}

	/**
	 * Per-field validator for in-hours placeholder text.
	 *
	 * @param array  $field Field config.
	 * @param string $value Submitted value.
	 */
	public function validate_in_hours_placeholders( $field, $value ) {
		$this->validate_placeholder_field( $field, (string) $value );
	}

	/**
	 * Per-field validator for out-of-hours placeholder text.
	 *
	 * @param array  $field Field config.
	 * @param string $value Submitted value.
	 */
	public function validate_out_of_hours_placeholders( $field, $value ) {
		$this->validate_placeholder_field( $field, (string) $value );
	}

	/**
	 * Shared placeholder-text validator: parse and surface any error against the field.
	 *
	 * @param array  $field Field config.
	 * @param string $value Submitted value.
	 */
	private function validate_placeholder_field( $field, $value ) {
		$result = self::parse_placeholders( (string) $value );
		if ( ! $result['ok'] ) {
			$this->set_field_error( $field, $result['error'] );
		}
	}

	/**
	 * Parse a "key1:value1,key2:value2" string into an associative array.
	 *
	 * Static + public so the BRO-882 dispatch path can reuse the same parser
	 * when building the API payload — same rules at save and at send.
	 *
	 * @param string $raw Raw textarea content.
	 * @return array{ok:bool,value:array<string,string>,error:string|null}
	 */
	public static function parse_placeholders( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array(
				'ok'    => true,
				'value' => array(),
				'error' => null,
			);
		}

		$out   = array();
		$pairs = explode( ',', $raw );
		foreach ( $pairs as $pair ) {
			if ( false === strpos( $pair, ':' ) ) {
				return array(
					'ok'    => false,
					'value' => array(),
					'error' => sprintf(
						/* translators: %s: the malformed pair text */
						esc_html__( 'Malformed placeholder pair (no colon): %s', 'broadcaster-auto-responder-for-gravity-forms' ),
						trim( $pair )
					),
				);
			}
			list( $key, $value ) = explode( ':', $pair, 2 );
			$key                 = trim( $key );
			$value               = trim( $value );
			if ( '' === $key ) {
				return array(
					'ok'    => false,
					'value' => array(),
					'error' => esc_html__( 'Empty placeholder key.', 'broadcaster-auto-responder-for-gravity-forms' ),
				);
			}
			if ( array_key_exists( $key, $out ) ) {
				return array(
					'ok'    => false,
					'value' => array(),
					'error' => sprintf(
						/* translators: %s: the duplicated placeholder key */
						esc_html__( 'Duplicate placeholder key: %s', 'broadcaster-auto-responder-for-gravity-forms' ),
						$key
					),
				);
			}
			$out[ $key ] = $value;
		}

		return array(
			'ok'    => true,
			'value' => $out,
			'error' => null,
		);
	}
}

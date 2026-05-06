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
	 * Full path to the main plugin file.
	 *
	 * GFAddOn::get_base_path() does basename(dirname($_full_path)) and
	 * concatenates that to WP_PLUGIN_DIR — so $_full_path must live in the
	 * plugin's root directory, not in includes/gf/. Set in the constructor
	 * because property defaults can't reference runtime constants.
	 *
	 * @var string
	 */
	protected $_full_path;

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
	 * Resolve runtime paths and let GFFeedAddOn finish initialising.
	 */
	public function __construct() {
		$this->_full_path = BROADCASTERGF_PATH . 'broadcaster-auto-responder-for-gravity-forms.php';
		parent::__construct();
	}

	/**
	 * Provide the SVG icon shown in the GF sidebar nav for this add-on.
	 */
	public function get_menu_icon() {
		return file_get_contents( $this->get_base_path() . '/images/menu-icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Live SaaS URL — locked in production, default everywhere else.
	 */
	const LIVE_SAAS_URL = 'https://getbroadcaster.com';

	/**
	 * Per-request cache for the connection test result.
	 *
	 * `feedback_callback` (the inline tick/cross) and the explicit status
	 * line below the key both want the same answer; caching means a single
	 * round-trip to Broadcaster per settings-page render.
	 *
	 * Keyed on `{api_url}|{api_key}` so that when the saved key changes
	 * (e.g. after a Save Settings post-back) the next call re-tests.
	 *
	 * @var array|null
	 */
	private $connection_test_cache = null;

	/**
	 * Cache key of the currently-cached test result.
	 *
	 * @var string|null
	 */
	private $connection_test_cache_key = null;

	/**
	 * Resolve the Broadcaster URL the plugin should call.
	 *
	 * In a production environment we always hit the live SaaS — site
	 * owners can't accidentally point production traffic at a dev or
	 * staging instance. Outside production, the saved value wins (and
	 * falls back to the live URL when nothing has been saved yet so a
	 * fresh install is functional immediately).
	 */
	public function get_api_url(): string {
		if ( $this->is_production() ) {
			return self::LIVE_SAAS_URL;
		}
		$saved = trim( (string) $this->get_plugin_setting( 'api_url' ) );
		return '' !== $saved ? $saved : self::LIVE_SAAS_URL;
	}

	/**
	 * True when WP_ENVIRONMENT_TYPE resolves to 'production'.
	 *
	 * Treats sites with no environment defined as production (matches
	 * WordPress's own default).
	 */
	private function is_production(): bool {
		if ( ! function_exists( 'wp_get_environment_type' ) ) {
			return true;
		}
		return 'production' === wp_get_environment_type();
	}

	/**
	 * Plugin-level settings shown under Forms → Settings → Broadcaster.
	 *
	 * Mirrors the EmailOctopus add-on layout: API URL (only on dev/staging/
	 * local) + API key with a feedback_callback that renders a green tick /
	 * red cross next to the field whenever the page loads.
	 */
	public function plugin_settings_fields() {
		$api_key_field = array(
			'name'              => 'api_key',
			'label'             => esc_html__( 'Broadcaster API key', 'broadcaster-auto-responder-for-gravity-forms' ),
			'type'              => 'text',
			'class'             => 'medium',
			'tooltip'           => esc_html__( 'Generated in Broadcaster under Settings → API Keys. Sent as Authorization: Bearer …', 'broadcaster-auto-responder-for-gravity-forms' ),
			'feedback_callback' => array( $this, 'check_api_credentials' ),
		);

		$status_field = array(
			'name' => 'connection_status',
			'type' => 'html',
			// Pass a callable so the status renders at field-render time
			// (after Save commits), not at schema-build time (before Save).
			'html' => array( $this, 'render_connection_status' ),
		);

		if ( $this->is_production() ) {
			return array(
				array(
					'description' => '<p>' . sprintf(
						/* translators: %s: live Broadcaster URL */
						esc_html__( 'This site is running in production and connects to Broadcaster at %s.', 'broadcaster-auto-responder-for-gravity-forms' ),
						'<code>' . esc_html( self::LIVE_SAAS_URL ) . '</code>'
					) . '</p>',
					'fields'      => array( $api_key_field, $status_field ),
				),
			);
		}

		return array(
			array(
				'description' => '<p>' . esc_html__( 'Connect this site to your Broadcaster account so Gravity Forms submissions can be injected as incoming WhatsApp contact messages.', 'broadcaster-auto-responder-for-gravity-forms' ) . '</p>',
				'fields'      => array(
					array(
						'name'          => 'api_url',
						'label'         => esc_html__( 'Broadcaster site URL', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'          => 'text',
						'class'         => 'medium',
						'default_value' => self::LIVE_SAAS_URL,
						'tooltip'       => esc_html__( 'Override only when testing against a dev or staging Broadcaster. Site root only — do not include /api/v1. Production sites always use the live SaaS regardless of this value.', 'broadcaster-auto-responder-for-gravity-forms' ),
					),
					$api_key_field,
					$status_field,
				),
			),
		);
	}

	/**
	 * Live feedback callback for the API key field.
	 *
	 * Returns true when Broadcaster accepts the key, false when it rejects
	 * it, and null when there's nothing usable to test yet (so GF shows
	 * no feedback rather than a misleading red cross).
	 *
	 * @param string $value The api_key field value being rendered.
	 *
	 * @return bool|null
	 */
	public function check_api_credentials( $value ) {
		$result = $this->run_connection_test( (string) $value );
		if ( null === $result ) {
			return null;
		}
		return (bool) $result['ok'];
	}

	/**
	 * Run the connection test, caching the result keyed on the actual
	 * api_url + api_key so a Save Settings post-back invalidates the
	 * previous render's cache automatically.
	 *
	 * @param string|null $value The api_key being rendered. When null, falls
	 *                           back to the saved value.
	 *
	 * @return array{ok:bool,message:string,http_code:int|null}|null
	 *               Null when there is no key to test.
	 */
	private function run_connection_test( $value = null ): ?array {
		$api_key = null === $value
			? trim( (string) $this->get_plugin_setting( 'api_key' ) )
			: trim( (string) $value );

		if ( '' === $api_key ) {
			return null;
		}

		$api_url   = $this->get_api_url();
		$cache_key = $api_url . '|' . $api_key;
		if ( $cache_key === $this->connection_test_cache_key ) {
			return $this->connection_test_cache;
		}

		$client                          = new \BroadcasterGF\Api\Client( $api_url, $api_key );
		$this->connection_test_cache     = $client->test_connection();
		$this->connection_test_cache_key = $cache_key;
		return $this->connection_test_cache;
	}

	/**
	 * Reset the cached connection-test result.
	 *
	 * Called from update_plugin_settings so the post-save render of the
	 * status field tests against the freshly saved key, not the value
	 * cached at schema-build time.
	 */
	private function reset_connection_test_cache(): void {
		$this->connection_test_cache     = null;
		$this->connection_test_cache_key = null;
	}

	/**
	 * Persist plugin settings, invalidating the connection-test cache so
	 * the next render reflects the just-saved key.
	 *
	 * @param array $settings New settings about to be written.
	 */
	public function update_plugin_settings( $settings ) {
		$this->reset_connection_test_cache();
		return parent::update_plugin_settings( $settings );
	}

	/**
	 * Build the explanatory status line shown below the API key field.
	 *
	 * Empty when no key is saved (the field-level tick already covers it
	 * once a key is entered).
	 *
	 * Public because GF's 'html' field type calls it at field-render time
	 * (post-save), not at schema-build time (pre-save).
	 */
	public function render_connection_status(): string {
		$result = $this->run_connection_test();
		if ( null === $result ) {
			return '';
		}
		if ( $result['ok'] ) {
			return '<p style="margin:6px 0 0;color:#1e7e34;"><strong>'
				. esc_html__( '✓ Connected.', 'broadcaster-auto-responder-for-gravity-forms' )
				. '</strong> '
				. esc_html__( 'Broadcaster accepted the API key.', 'broadcaster-auto-responder-for-gravity-forms' )
				. '</p>';
		}
		return '<p style="margin:6px 0 0;color:#b32d2e;"><strong>'
			. esc_html__( '✗ Not connected.', 'broadcaster-auto-responder-for-gravity-forms' )
			. '</strong> '
			. esc_html( $result['message'] )
			. '</p>';
	}

	/**
	 * Build the ghost-text placeholder for the Source label field.
	 *
	 * Falls back to 'webform' when no form title is available so the field
	 * always shows what the dispatched source will actually look like.
	 */
	private function source_label_placeholder(): string {
		$form  = $this->get_current_form();
		$title = is_array( $form ) ? trim( (string) rgar( $form, 'title' ) ) : '';
		return '' !== $title ? $title : 'webform';
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
						'label'       => esc_html__( 'Source label', 'broadcaster-auto-responder-for-gravity-forms' ),
						'type'        => 'text',
						'name'        => 'source_label',
						'class'       => 'medium',
						'placeholder' => $this->source_label_placeholder(),
						'tooltip'     => esc_html__( 'Shown in Broadcaster chat bubbles to identify where the message came from. Leave blank to use the form name.', 'broadcaster-auto-responder-for-gravity-forms' ),
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
	 * Send the entry to Broadcaster /api/v1/messages/incoming.
	 *
	 * Conditional logic is evaluated by the framework before this runs.
	 * Failures are logged but never block the form: we always return the
	 * entry so Gravity Forms confirmation and notifications proceed.
	 *
	 * @param array $feed  Feed configuration.
	 * @param array $entry Form entry.
	 * @param array $form  Form definition.
	 */
	public function process_feed( $feed, $entry, $form ) {
		$entry_id = rgar( $entry, 'id' );
		$feed_id  = rgar( $feed, 'id' );

		$api_key = trim( (string) $this->get_plugin_setting( 'api_key' ) );
		if ( '' === $api_key ) {
			$this->log_error( __METHOD__ . sprintf( '(): feed %s skipped — Broadcaster API key not configured.', $feed_id ) );
			return $entry;
		}
		$api_url = $this->get_api_url();

		$meta = isset( $feed['meta'] ) && is_array( $feed['meta'] ) ? $feed['meta'] : array();

		$phone    = $this->resolve_field_value( $form, $entry, rgar( $meta, 'phone_field' ) );
		$username = $this->resolve_field_value( $form, $entry, rgar( $meta, 'whatsapp_username_field' ) );
		$name     = $this->resolve_field_value( $form, $entry, rgar( $meta, 'submitter_name_field' ) );

		if ( '' === $phone && '' === $username ) {
			$this->log_error( __METHOD__ . sprintf( '(): feed %s skipped — neither phone nor WhatsApp username resolved from entry %s.', $feed_id, $entry_id ) );
			return $entry;
		}

		$message = $this->render_merge_tags( rgar( $meta, 'message_text', '' ), $form, $entry );

		$source_label = trim( (string) rgar( $meta, 'source_label' ) );
		if ( '' === $source_label ) {
			$source_label = trim( (string) rgar( $form, 'title' ) );
		}
		if ( '' === $source_label ) {
			$source_label = 'webform';
		}

		$payload = array(
			'message'          => $message,
			'source'           => $source_label,
			'form_id'          => (string) rgar( $form, 'id' ),
			'form_name'        => rgar( $form, 'title' ),
			'source_reference' => sprintf( 'gf-form-%d-entry-%s', (int) rgar( $form, 'id' ), $entry_id ),
		);

		if ( '' !== $phone ) {
			$payload['phone'] = $phone;
		}
		if ( '' !== $username ) {
			$payload['whatsapp_username'] = $username;
		}
		if ( '' !== $name ) {
			$payload['submitter_name'] = $name;
		}

		$in_hours_template     = trim( (string) rgar( $meta, 'in_hours_template_name' ) );
		$out_of_hours_template = trim( (string) rgar( $meta, 'out_of_hours_template_name' ) );

		if ( '' !== $in_hours_template ) {
			$payload['in_hours_template_name']         = $in_hours_template;
			$payload['in_hours_template_placeholders'] = $this->build_template_placeholders(
				rgar( $meta, 'in_hours_template_placeholders', '' ),
				$form,
				$entry,
				$feed_id,
				'in_hours'
			);
		}

		if ( '' !== $out_of_hours_template ) {
			$payload['out_of_hours_template_name']         = $out_of_hours_template;
			$payload['out_of_hours_template_placeholders'] = $this->build_template_placeholders(
				rgar( $meta, 'out_of_hours_template_placeholders', '' ),
				$form,
				$entry,
				$feed_id,
				'out_of_hours'
			);
		}

		$this->log_debug( __METHOD__ . sprintf( '(): feed %s dispatching entry %s to Broadcaster.', $feed_id, $entry_id ) );

		$client = new \BroadcasterGF\Api\Client( $api_url, $api_key );
		$result = $client->send_incoming_message( $payload );

		if ( $result['ok'] ) {
			$this->log_debug( __METHOD__ . sprintf( '(): feed %s — Broadcaster accepted entry %s (HTTP %d).', $feed_id, $entry_id, (int) $result['http_code'] ) );
		} else {
			$this->log_error( __METHOD__ . sprintf( '(): feed %s — Broadcaster dispatch failed for entry %s: %s', $feed_id, $entry_id, $result['message'] ) );
		}

		return $entry;
	}

	/**
	 * Read a mapped form field value from an entry, returning '' when the
	 * mapping is empty or the entry has no value at that field id.
	 *
	 * @param array  $form     The form.
	 * @param array  $entry    The entry.
	 * @param string $field_id GF field id (may be a sub-field like "1.3").
	 *
	 * @return string
	 */
	private function resolve_field_value( $form, $entry, $field_id ): string {
		$field_id = (string) $field_id;
		if ( '' === $field_id ) {
			return '';
		}
		$value = $this->get_field_value( $form, $entry, $field_id );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Render Gravity Forms merge tags inside arbitrary text.
	 *
	 * @param string $text The raw text from feed config.
	 * @param array  $form The form.
	 * @param array  $entry The entry.
	 *
	 * @return string
	 */
	private function render_merge_tags( $text, $form, $entry ): string {
		if ( ! is_string( $text ) || '' === $text ) {
			return '';
		}
		if ( ! class_exists( '\\GFCommon' ) ) {
			return $text;
		}
		return (string) \GFCommon::replace_variables( $text, $form, $entry, false, false, false, 'text' );
	}

	/**
	 * Parse a placeholder textarea string and render merge tags inside each
	 * value. Parse errors fall back to an empty map so dispatch doesn't fail
	 * mid-flight — the same string was already validated when the feed was
	 * saved (parse_placeholders).
	 *
	 * @param string $raw     Raw textarea content.
	 * @param array  $form    The form.
	 * @param array  $entry   The entry.
	 * @param string $feed_id Feed id (for error logging).
	 * @param string $slot    'in_hours' or 'out_of_hours' (for error logging).
	 *
	 * @return array<string,string>
	 */
	private function build_template_placeholders( $raw, $form, $entry, $feed_id, $slot ): array {
		$parsed = self::parse_placeholders( (string) $raw );
		if ( ! $parsed['ok'] ) {
			$this->log_error( __METHOD__ . sprintf( '(): feed %s %s placeholders failed to parse at dispatch (was: %s)', $feed_id, $slot, $parsed['error'] ) );
			return array();
		}
		$rendered = array();
		foreach ( $parsed['value'] as $key => $value ) {
			$rendered[ $key ] = $this->render_merge_tags( $value, $form, $entry );
		}
		return $rendered;
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

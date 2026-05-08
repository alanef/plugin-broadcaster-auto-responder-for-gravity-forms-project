<?php
/**
 * Broadcaster_GF_WhatsApp_Recipient_Field — custom Gravity Forms field type
 * that captures a phone number or (when the username feature flag is on) a
 * WhatsApp username, in a single input.
 *
 * Mirrors the un-namespaced global-class pattern used for the feed add-on
 * (Broadcaster_GF_FeedAddOn), because Gravity Forms stores registered
 * fields by class-name string and registration must happen at gform_loaded
 * before this file's class definition is parsed otherwise.
 *
 * BRO-902 references:
 *   - D2 — single combined input; `@`-prefix discriminator
 *   - D5 — country precedence (`+` prefix > field setting > plugin default)
 *   - D6 — single display mode (no visible country selector)
 *   - D11 — entry value is the raw submitter input
 *   - D12 — username surface gated behind BROADCASTERGF_ENABLE_USERNAMES
 *   - Q2 — entry-detail 422 marker is inline beside the field
 *
 * Validation, normalisation, and rejection-surface decisions all live in
 * the BroadcasterGF\GF\* namespaced classes (Field_Submission_Dispatcher,
 * Phone_Validator, Username_Validator, Api_Rejection_Surfacer). This field
 * class is GF integration glue only.
 *
 * @package BroadcasterGF
 */

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp Recipient field for Gravity Forms.
 */
class Broadcaster_GF_WhatsApp_Recipient_Field extends \GF_Field {

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- GF_Field framework properties (isRequired, errorMessage, defaultPhoneCountry, etc.) are camelCase by Gravity Forms convention.

	/**
	 * Field type slug — used by GF for storage and form-editor dispatch.
	 *
	 * @var string
	 */
	public $type = 'whatsapp_recipient';

	/**
	 * Display name shown in the form editor's field picker.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'WhatsApp Recipient', 'broadcaster-auto-responder-for-gravity-forms' );
	}

	/**
	 * Hover-help description in the field picker.
	 *
	 * @return string
	 */
	public function get_form_editor_field_description() {
		return esc_attr__( 'Captures a WhatsApp recipient — phone number, or a @username when WhatsApp usernames are enabled — in a single input. Validates format inline before submission.', 'broadcaster-auto-responder-for-gravity-forms' );
	}

	/**
	 * GF field-picker icon. Reuses GF's built-in phone icon.
	 *
	 * @return string
	 */
	public function get_form_editor_field_icon() {
		return 'gform-icon--phone';
	}

	/**
	 * Where this field lives in the form-editor field picker.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * Which settings panels appear in the field-editor sidebar for this field.
	 *
	 * The custom `broadcastergf_default_country_setting` is rendered by
	 * self::render_default_country_setting() on the gform_field_advanced_settings
	 * hook; including the CSS class here is what tells GF to make that
	 * panel visible when this field is selected.
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'rules_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
			'broadcastergf_default_country_setting',
		);
	}

	/**
	 * Render the public-facing input (and form-editor preview).
	 *
	 * Public-form rendering: a text input + an inline helper-text span
	 * naming the assumed country code (and the `@username` option when the
	 * flag is on). Data attributes carry the per-field config the JS
	 * Submitter Validator (chunk C2) reads to mirror the PHP validation
	 * rules client-side.
	 *
	 * Form-editor preview: same layout; the input is `disabled` so the form
	 * designer sees what the field looks like without it being editable.
	 *
	 * @param array      $form  Form object.
	 * @param string     $value Submitted value or saved entry value.
	 * @param array|null $entry Entry array (when re-rendering on the entry
	 *                          edit screen) or null on a fresh submission.
	 * @return string Rendered input HTML.
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id  = absint( $form['id'] );
		$id       = (int) $this->id;
		$input_id = sprintf( 'input_%d_%d', $form_id, $id );

		$region            = $this->resolve_default_country();
		$dialling          = \BroadcasterGF\GF\Phone_Validator::dialling_code( $region );
		$usernames_enabled = (bool) BROADCASTERGF_ENABLE_USERNAMES;

		$helper_text = $this->build_helper_text( $dialling, $usernames_enabled );

		$value_attr = is_string( $value ) ? esc_attr( $value ) : '';

		$disabled  = $this->is_form_editor() ? 'disabled="disabled"' : '';
		$required  = ! empty( $this->isRequired ) ? 'aria-required="true"' : '';
		$tabindex  = $this->get_tabindex();
		$css_class = $this->get_input_class();

		$container = sprintf(
			'<div class="ginput_container ginput_container_text broadcastergf-recipient-field" data-region="%1$s" data-cc="%2$s" data-usernames-enabled="%3$s">' .
				'<input type="text" name="input_%4$d" id="%5$s" value="%6$s" class="%7$s" %8$s %9$s %10$s />' .
				'<span class="broadcastergf-recipient-helper" id="%5$s_helper">%11$s</span>' .
			'</div>',
			esc_attr( $region ),
			esc_attr( null !== $dialling ? $dialling : '' ),
			$usernames_enabled ? '1' : '0',
			$id,
			esc_attr( $input_id ),
			$value_attr,
			esc_attr( $css_class ),
			$tabindex, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_tabindex returns trusted markup.
			$disabled,
			$required,
			esc_html( $helper_text )
		);

		return $container;
	}

	/**
	 * Server-side validation. Runs after the JS Submitter Validator on form
	 * post; catches the case where JS is disabled or bypassed.
	 *
	 * Delegates the actual decision to Field_Submission_Dispatcher so the
	 * server-side and client-side rules stay in lockstep — both reach the
	 * same answer on the same input.
	 *
	 * @param string $value Raw posted value.
	 * @param array  $form  Form object.
	 * @return void
	 */
	public function validate( $value, $form ) {
		if ( $this->is_form_editor() ) {
			return;
		}

		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			// Empty handling: GF_Field's parent already covers required-field
			// rejection; a non-required empty value is fine.
			return;
		}

		$payload = \BroadcasterGF\GF\Field_Submission_Dispatcher::dispatch(
			$value,
			$this->resolve_default_country(),
			(bool) BROADCASTERGF_ENABLE_USERNAMES
		);

		if ( null === $payload ) {
			$this->failed_validation  = true;
			$default_message          = ( (bool) BROADCASTERGF_ENABLE_USERNAMES )
				? esc_html__( 'Please enter a valid phone number, or a WhatsApp username starting with @.', 'broadcaster-auto-responder-for-gravity-forms' )
				: esc_html__( 'Please enter a valid phone number.', 'broadcaster-auto-responder-for-gravity-forms' );
			$this->validation_message = ! empty( $this->errorMessage )
				? $this->errorMessage
				: $default_message;
		}
	}

	/**
	 * Resolve the effective default-country region for this field.
	 *
	 * Precedence (BRO-902 D5): per-field override > plugin-level default >
	 * hard-coded `GB` fallback. The submitter's explicit `+` prefix on the
	 * input is a third layer that overrides this, but that's handled inside
	 * Phone_Validator::normalize().
	 *
	 * @return string ISO-3166 alpha-2 region (uppercase).
	 */
	private function resolve_default_country() {
		$field_country = ! empty( $this->defaultPhoneCountry ) ? (string) $this->defaultPhoneCountry : '';
		if ( '' !== $field_country ) {
			return strtoupper( $field_country );
		}

		if ( class_exists( 'Broadcaster_GF_FeedAddOn' ) && method_exists( 'Broadcaster_GF_FeedAddOn', 'get_instance' ) ) {
			$plugin_country = \Broadcaster_GF_FeedAddOn::get_instance()->get_plugin_setting( 'default_phone_country' );
			if ( ! empty( $plugin_country ) ) {
				return strtoupper( (string) $plugin_country );
			}
		}

		return 'GB';
	}

	/**
	 * Build the inline helper text shown beneath the input.
	 *
	 * The wording is flag-conditional (D12): when the username surface is
	 * off, the `@username` option is not mentioned. When the configured
	 * region has no known dialling code (unsupported region), the helper
	 * text steers the submitter toward an explicit `+` prefix instead.
	 *
	 * @param string|null $dialling Country dialling code without `+`, or null.
	 * @param bool        $usernames_enabled Whether the username surface is on.
	 * @return string Helper text.
	 */
	private function build_helper_text( $dialling, $usernames_enabled ) {
		if ( null === $dialling || '' === $dialling ) {
			return $usernames_enabled
				? __( 'Use international format starting with + — or enter a WhatsApp username starting with @', 'broadcaster-auto-responder-for-gravity-forms' )
				: __( 'Use international format starting with +', 'broadcaster-auto-responder-for-gravity-forms' );
		}

		if ( $usernames_enabled ) {
			return sprintf(
				/* translators: %s: country dialling code without the + sign */
				__( "We'll assume +%s unless you start with + — or enter a WhatsApp username starting with @", 'broadcaster-auto-responder-for-gravity-forms' ),
				$dialling
			);
		}

		return sprintf(
			/* translators: %s: country dialling code without the + sign */
			__( "We'll assume +%s unless you start with +", 'broadcaster-auto-responder-for-gravity-forms' ),
			$dialling
		);
	}

	// ---------------------------------------------------------------------
	// Static hook handlers — registered by Broadcaster_GF_Bootstrap.
	// ---------------------------------------------------------------------

	/**
	 * Render the field-editor sidebar setting for the per-field default
	 * country override.
	 *
	 * Hooked to `gform_field_advanced_settings`. The matching CSS class is
	 * declared in self::get_form_editor_field_settings() so GF only shows
	 * this panel for whatsapp_recipient fields.
	 *
	 * @param int $position Position index in the editor's settings UI.
	 * @param int $form_id  Form ID being edited.
	 * @return void
	 */
	public static function render_default_country_setting( $position, $form_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 25 !== (int) $position ) {
			return;
		}
		?>
		<li class="broadcastergf_default_country_setting field_setting">
			<label for="broadcastergf_default_phone_country" class="section_label">
				<?php esc_html_e( 'Default phone country (override)', 'broadcaster-auto-responder-for-gravity-forms' ); ?>
			</label>
			<select id="broadcastergf_default_phone_country" onchange="SetFieldProperty('defaultPhoneCountry', this.value);">
				<option value=""><?php esc_html_e( '— Use plugin default —', 'broadcaster-auto-responder-for-gravity-forms' ); ?></option>
				<option value="GB"><?php esc_html_e( 'United Kingdom (+44)', 'broadcaster-auto-responder-for-gravity-forms' ); ?></option>
				<option value="US"><?php esc_html_e( 'United States (+1)', 'broadcaster-auto-responder-for-gravity-forms' ); ?></option>
				<option value="IE"><?php esc_html_e( 'Ireland (+353)', 'broadcaster-auto-responder-for-gravity-forms' ); ?></option>
				<option value="AU"><?php esc_html_e( 'Australia (+61)', 'broadcaster-auto-responder-for-gravity-forms' ); ?></option>
				<option value="IN"><?php esc_html_e( 'India (+91)', 'broadcaster-auto-responder-for-gravity-forms' ); ?></option>
				<option value="ID"><?php esc_html_e( 'Indonesia (+62)', 'broadcaster-auto-responder-for-gravity-forms' ); ?></option>
			</select>
			<p class="description">
				<?php esc_html_e( 'When the submitter types a national-format number, this is the country we will assume. Leave on "Use plugin default" unless this specific form targets a different country than the rest of the site.', 'broadcaster-auto-responder-for-gravity-forms' ); ?>
			</p>
		</li>
		<?php
	}

	/**
	 * Inline JS the form editor needs to load/save the per-field setting and
	 * to register which settings panels apply to the whatsapp_recipient type.
	 *
	 * Hooked to `gform_editor_js`. The fieldSettings entry is GF's mechanism
	 * for telling the editor which CSS-classed settings divs to show for
	 * each field type.
	 *
	 * @return void
	 */
	public static function editor_inline_script() {
		?>
		<script type="text/javascript">
			fieldSettings.whatsapp_recipient =
				'.broadcastergf_default_country_setting,' +
				'.conditional_logic_field_setting,' +
				'.error_message_setting,' +
				'.label_setting,' +
				'.label_placement_setting,' +
				'.admin_label_setting,' +
				'.rules_setting,' +
				'.visibility_setting,' +
				'.description_setting,' +
				'.css_class_setting';

			jQuery( document ).on( 'gform_load_field_settings', function ( event, field ) {
				if ( 'whatsapp_recipient' === field.type ) {
					jQuery( '#broadcastergf_default_phone_country' ).val(
						field.defaultPhoneCountry || ''
					);
				}
			} );
		</script>
		<?php
	}

	/**
	 * Append the API-rejection marker to the entry value when an entry has
	 * a rejection record from the Api_Rejection_Surfacer.
	 *
	 * Hooked to `gform_entry_field_value`. Fires both on the entry detail
	 * view and the entries-list view, so admins see "Broadcaster could not
	 * deliver to this recipient" inline beside the recipient field in
	 * either context (BRO-902 design Q2).
	 *
	 * @param string         $value_html Already-escaped HTML of the field value.
	 * @param \GF_Field|null $field      The field object.
	 * @param array|null     $entry      The entry array.
	 * @param array|null     $form       The form array.
	 * @return string HTML, possibly with the marker appended.
	 */
	public static function render_rejection_marker( $value_html, $field, $entry, $form ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! $field instanceof self ) {
			return $value_html;
		}
		if ( empty( $entry['id'] ) ) {
			return $value_html;
		}

		if ( ! function_exists( 'gform_get_meta' ) ) {
			return $value_html;
		}

		$meta_key = \BroadcasterGF\GF\Api_Rejection_Surfacer::META_KEY_PREFIX . (int) $field->id;
		$meta     = gform_get_meta( $entry['id'], $meta_key );

		if ( empty( $meta ) || ! is_array( $meta ) ) {
			return $value_html;
		}

		$message  = isset( $meta['message'] ) ? (string) $meta['message'] : '';
		$err_code = isset( $meta['error_code'] ) ? (string) $meta['error_code'] : '';

		$marker = sprintf(
			'<div class="broadcastergf-recipient-rejection" role="status">' .
				'<strong>%1$s</strong> %2$s%3$s' .
			'</div>',
			esc_html__( 'Broadcaster did not deliver to this recipient.', 'broadcaster-auto-responder-for-gravity-forms' ),
			esc_html( $message ),
			'' !== $err_code
				? ' <code>' . esc_html( $err_code ) . '</code>'
				: ''
		);

		return $value_html . $marker;
	}
}

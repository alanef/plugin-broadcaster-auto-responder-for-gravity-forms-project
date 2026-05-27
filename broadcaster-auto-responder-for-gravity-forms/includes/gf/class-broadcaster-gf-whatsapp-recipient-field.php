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
			'size_setting',
			'rules_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
			'broadcastergf_default_country_setting',
		);
	}

	/**
	 * Allow this field to participate in conditional logic rules.
	 *
	 * GF_Field's default is false, which is why a whatsapp_recipient field
	 * never appeared in the "based on" dropdown when other fields, feeds,
	 * notifications or confirmations were made conditional. The field stores
	 * a plain string value under `input_{id}`, so GF_Field::is_value_match()
	 * compares it correctly with no extra work.
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return true;
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
		$usernames_enabled = (bool) BROADCASTERGF_ENABLE_USERNAMES;

		$value_attr = is_string( $value ) ? esc_attr( $value ) : '';

		$disabled = $this->is_form_editor() ? 'disabled="disabled"' : '';
		$required = ! empty( $this->isRequired ) ? 'aria-required="true"' : '';

		/*
		 * $tabindex is a COMPLETE, pre-built HTML attribute fragment — e.g.
		 * the literal string `tabindex='3'` (or an empty string when GF has
		 * tabindexing switched off) — NOT a bare value. It is emitted below
		 * (placeholder %7$s) without an escaping function, which is correct
		 * and safe for three independent reasons:
		 *
		 *   1. The value is not user input. GF_Field::get_tabindex() returns
		 *      sprintf( "tabindex='%d'", $n ), where $n is GFCommon's internal
		 *      per-form integer counter. The `%d` cast makes the only variable
		 *      part an integer; no submitter- or request-derived string can
		 *      reach it, so there is no injection surface to escape.
		 *
		 *   2. It cannot be escaped without being corrupted. Running a full
		 *      attribute fragment through esc_attr() encodes the `=` and the
		 *      single quotes — `tabindex='3'` becomes
		 *      `tabindex=&#039;3&#039;` — which is invalid markup and breaks
		 *      the attribute. esc_attr() is for attribute *values*, not whole
		 *      `name='value'` pairs. There is no escaper whose contract fits a
		 *      ready-made attribute fragment.
		 *
		 *   3. This is the canonical Gravity Forms pattern. GF core and every
		 *      shipped GF field render get_tabindex() exactly this way; mirroring
		 *      it keeps tab-order behaviour consistent with the rest of the form.
		 *
		 * The adjacent %8$s ($disabled) and %9$s ($required) are likewise whole
		 * attribute fragments, but built here from hard-coded string literals,
		 * so PHPCS already recognises them as safe and they need no annotation.
		 * Every genuinely dynamic value in the sprintf — $region, $input_id,
		 * $value, $css_class — is passed through esc_attr() at the call site.
		 */
		$tabindex = $this->get_tabindex();
		// GF text-field standard sizing: 'small' | 'medium' | 'large'. Falls
		// back to 'large' to suit a phone-shaped input.
		$size      = ! empty( $this->size ) ? (string) $this->size : 'large';
		$css_class = $size;

		// The field's helper text is rendered by GF's standard
		// `gfield_description` mechanism. SetDefaultValues_whatsapp_recipient
		// (in get_form_editor_inline_script_on_page_render) sets a sensible
		// default description at field-drop time; the form designer can edit
		// it via the standard Description field setting.
		$container = sprintf(
			'<div class="ginput_container ginput_container_text broadcastergf-recipient-field" data-region="%1$s" data-usernames-enabled="%2$s">' .
				'<input type="text" name="input_%3$d" id="%4$s" value="%5$s" class="%6$s" %7$s %8$s %9$s />' .
			'</div>',
			esc_attr( $region ),
			$usernames_enabled ? '1' : '0',
			$id,
			esc_attr( $input_id ),
			$value_attr,
			esc_attr( $css_class ),
			$tabindex, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- GF_Field::get_tabindex() returns a trusted, integer-derived attribute fragment that must not be re-escaped; see the detailed rationale where $tabindex is assigned above.
			$disabled,
			$required
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
	 * Normalise the value written to the entry: phone input is stored in
	 * E.164 (`+<cc><national>`) so the entry, exports and the feed's
	 * recipient mapping all carry an unambiguous international number;
	 * `@username` input — and anything Phone_Validator can't normalise —
	 * is stored exactly as the submitter typed it.
	 *
	 * This mirrors the public-form blur behaviour (assets/js/whatsapp-recipient-field.js):
	 * a JS-disabled or JS-bypassed submission still ends up with the same
	 * normalised value on the entry. validate() has already run and rejected
	 * anything Phone_Validator can't normalise, so the raw-value fallback
	 * below is only ever reached as defence-in-depth.
	 *
	 * Note: the `@username` value is stored verbatim (with the `@`, original
	 * casing) — the lowercase/strip the dispatcher applies is for the
	 * Broadcaster API payload, not the stored entry value.
	 *
	 * @param string $value      Posted field value.
	 * @param array  $form       Form object.
	 * @param string $input_name Input name (`input_{id}`).
	 * @param int    $lead_id    Entry ID.
	 * @param array  $lead       Entry object.
	 * @return string Value to persist on the entry.
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$raw = trim( $value );
		if ( '' === $raw ) {
			return $value;
		}

		// `@username` (or `@`-prefixed input that validate() already
		// rejected) → store exactly as typed.
		if ( '@' === $raw[0] ) {
			return $value;
		}

		$e164 = \BroadcasterGF\GF\Phone_Validator::normalize( $raw, $this->resolve_default_country() );

		return ( null !== $e164 ) ? $e164 : $value;
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
		return self::build_helper_text_static( $dialling, $usernames_enabled );
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
				<?php foreach ( \BroadcasterGF\GF\Phone_Validator::supported_regions() as $region_code => $region_info ) : ?>
					<option value="<?php echo esc_attr( $region_code ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: country name, 2: dialling code without + */
								__( '%1$s (+%2$s)', 'broadcaster-auto-responder-for-gravity-forms' ),
								$region_info['name'],
								$region_info['cc']
							)
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description">
				<?php esc_html_e( 'When the submitter types a national-format number, this is the country we will assume. Leave on "Use plugin default" unless this specific form targets a different country than the rest of the site.', 'broadcaster-auto-responder-for-gravity-forms' ); ?>
			</p>
		</li>
		<?php
	}

	/**
	 * Inline JS for the form editor.
	 *
	 * Hooked to `gform_editor_js` (registered in Broadcaster_GF_Bootstrap)
	 * rather than overriding GF_Field::get_form_editor_inline_script_on_page_render
	 * because the latter only fires when the form already has at least
	 * one field of this type — meaning SetDefaultValues_whatsapp_recipient
	 * wouldn't be defined before the FIRST drop, and the field would land
	 * with GF's generic "Untitled" defaults.
	 *
	 * gform_editor_js fires once per form-editor page load regardless of
	 * which field types are on the form, so both:
	 *
	 *   1. SetDefaultValues_whatsapp_recipient(field) — sets the default
	 *      label, description, and size when the field is first dropped.
	 *   2. gform_load_field_settings handler — populates the per-field
	 *      Default Country picker with the field's saved value when the
	 *      sidebar opens for a whatsapp_recipient field.
	 *
	 * are reliably available from the very first interaction.
	 *
	 * Notably absent: any fieldSettings.whatsapp_recipient = '…' assignment.
	 * GF auto-populates that internally from the array returned by
	 * self::get_form_editor_field_settings(); doing it again in JS races
	 * GF's own initialisation and breaks the form editor for ALL fields
	 * when our script happens to run before form_editor.js initialises
	 * the fieldSettings global.
	 *
	 * @return void
	 */
	public static function editor_inline_script() {
		$usernames_enabled = (bool) BROADCASTERGF_ENABLE_USERNAMES;

		// Default label is flag-conditional. The form designer can rename
		// in the standard Label setting.
		$default_label = $usernames_enabled
			? __( 'WhatsApp Number / Username', 'broadcaster-auto-responder-for-gravity-forms' )
			: __( 'WhatsApp Number', 'broadcaster-auto-responder-for-gravity-forms' );

		// Default description = the helper text, computed using the
		// plugin-level default country (the per-field override doesn't
		// exist yet at field-drop time). Form designer can edit via the
		// standard Description setting; if they don't, GF renders it
		// below the input automatically as the field's description.
		$plugin_country = '';
		if ( class_exists( 'Broadcaster_GF_FeedAddOn' ) ) {
			$plugin_country = (string) \Broadcaster_GF_FeedAddOn::get_instance()->get_plugin_setting( 'default_phone_country' );
		}
		if ( '' === $plugin_country ) {
			$plugin_country = 'GB';
		}
		$dialling            = \BroadcasterGF\GF\Phone_Validator::dialling_code( $plugin_country );
		$default_description = self::build_helper_text_static( $dialling, $usernames_enabled );

		?>
		<script type="text/javascript">
			function SetDefaultValues_whatsapp_recipient( field ) {
				field.label       = '<?php echo esc_js( $default_label ); ?>';
				field.description = '<?php echo esc_js( $default_description ); ?>';
				field.size        = 'large';
			}

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
	 * Static version of build_helper_text — same logic, no $this dependency.
	 * Called from self::editor_inline_script() (a static method) and from
	 * the instance-method version (kept for instance-context callers).
	 *
	 * @param string|null $dialling Country dialling code without `+`, or null.
	 * @param bool        $usernames_enabled Whether the username surface is on.
	 * @return string
	 */
	public static function build_helper_text_static( $dialling, $usernames_enabled ) {
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

	/**
	 * Enqueue the public-form Submitter Validator JS and styles when a
	 * form contains at least one whatsapp_recipient field.
	 *
	 * Hooked to `gform_pre_render`. Pass-through filter — does not modify
	 * the form, only side-effects asset registration. Gated to public
	 * rendering (admin form-editor preview doesn't need the public-form JS).
	 *
	 * @param array $form Form array (unchanged on return).
	 * @return array
	 */
	public static function maybe_enqueue_public_assets( $form ) {
		if ( is_admin() ) {
			return $form;
		}

		if ( ! self::form_has_recipient_field( $form ) ) {
			return $form;
		}

		wp_enqueue_script(
			'broadcastergf-recipient-field',
			BROADCASTERGF_URL . 'assets/js/whatsapp-recipient-field.js',
			array(),
			BROADCASTERGF_VERSION,
			true
		);

		wp_localize_script(
			'broadcastergf-recipient-field',
			'BroadcasterGFRecipientFieldL10n',
			array(
				'invalidPhone'           => __( 'Please enter a valid phone number.', 'broadcaster-auto-responder-for-gravity-forms' ),
				'invalidPhoneOrUsername' => __( 'Please enter a valid phone number, or a WhatsApp username starting with @.', 'broadcaster-auto-responder-for-gravity-forms' ),
				// Per-region rules served from PHP so the JS validator stays
				// in lockstep with Phone_Validator. Each entry is
				// { pattern: regex source string, strip: regex source or null, cc: dialling code }.
				'regions'                => \BroadcasterGF\GF\Phone_Validator::js_rules(),
			)
		);

		wp_enqueue_style(
			'broadcastergf-recipient-field',
			BROADCASTERGF_URL . 'assets/css/whatsapp-recipient-field.css',
			array(),
			BROADCASTERGF_VERSION
		);

		return $form;
	}

	/**
	 * Returns true if a form array contains at least one whatsapp_recipient field.
	 *
	 * @param array $form Form array.
	 * @return bool
	 */
	private static function form_has_recipient_field( $form ) {
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return false;
		}
		foreach ( $form['fields'] as $field ) {
			if ( isset( $field->type ) && 'whatsapp_recipient' === $field->type ) {
				return true;
			}
		}
		return false;
	}
}

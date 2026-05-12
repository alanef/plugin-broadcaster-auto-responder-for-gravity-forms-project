=== Broadcaster Auto Responder for Gravity Forms ===
Contributors: fullworks, alanfuller
Tags: gravity forms, broadcaster, whatsapp, contact form, auto responder
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.1.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Forward Gravity Forms submissions to Broadcaster (paid WhatsApp business platform) as inbound contact messages with optional template reply.

== Description ==

**This is a connector plugin for [Broadcaster](https://getbroadcaster.com), a paid SaaS platform for business WhatsApp management. It is not a free WhatsApp integration — it requires an active Broadcaster account.** If you do not have one, this plugin will not do anything useful on its own.

If you *are* a Broadcaster customer using Gravity Forms on a WordPress site, this plugin forwards each form submission into your Broadcaster inbox as an incoming contact message, and can optionally trigger an approved WhatsApp template reply (in business hours, out of business hours, or both — Broadcaster picks based on your company's business-hours settings).

= What it does =

* For each form you opt in to, the submission becomes an incoming contact message in Broadcaster, attributed to the form so support staff know where the contact came from.
* Each submission can trigger one optional template auto-response — either a single template that always fires, or two templates (in-hours / out-of-hours) and Broadcaster picks based on your company's configured business hours.
* A dedicated **WhatsApp Recipient** custom field (under *Advanced Fields* in the form editor) captures the contact's phone number in a single input, validates the format inline before form submission, and prevents the submitter from accidentally collecting a typo'd number that Broadcaster would later silently fail to deliver to. Or use any standard Gravity Forms field as the recipient mapping for backward compatibility.
* Phone or WhatsApp username (or both) are mapped from form fields you nominate.
* Standard Gravity Forms merge tags work in the message body and in template placeholder values.
* Standard Gravity Forms conditional logic decides whether a feed runs for a given submission.
* Failures never block the form — a Broadcaster outage doesn't stop the user's submission, the email notification, or the confirmation page. When Broadcaster declines a recipient as undeliverable, the failure is surfaced inline on the Gravity Forms entry detail so site administrators can see what didn't deliver and why.

= What it does *not* do =

* It does **not** give you WhatsApp messaging. It just hands form submissions to your Broadcaster account, which is what actually talks to WhatsApp Business.
* It does **not** create a Broadcaster account, manage templates, or open the WhatsApp customer-service window. Templates must already exist and be approved on the Broadcaster side.
* It does **not** do full phone-number validation. The **WhatsApp Recipient** field does normalise national-format numbers to international format (`+44…`, `+1…`) using the country you configure, and catches obvious typos before submit, but Broadcaster's own normalization rules remain the source of truth. For other field types, submit numbers in international format where possible.

= Ownership and affiliation =

This plugin, the **Broadcaster** service it connects to ([getbroadcaster.com](https://getbroadcaster.com)), the **Fullworks** brand ([fullworks.net](https://fullworks.net)) and **Fullworks Plugins** ([fullworksplugins.com](https://fullworksplugins.com)) are all owned and operated by Fullworks Digital Ltd, a company registered in England and Wales — the same company that publishes this plugin on WordPress.org under the account "fullworks". Broadcaster is our own product, not a third party's.

"Gravity Forms" is a trademark of Rocketgenius, Inc. and "WhatsApp" is a trademark of Meta Platforms, Inc. This plugin is an independent, unofficial add-on and uses those names only to describe what it is compatible with.

== Installation ==

= Prerequisites =

1. An active **Broadcaster** account at [getbroadcaster.com](https://getbroadcaster.com).
2. **Gravity Forms** installed and active on your WordPress site (Gravity Forms is a third-party paid plugin).

= Install the plugin =

1. Upload the plugin to `/wp-content/plugins/broadcaster-auto-responder-for-gravity-forms` (or install the zip via *Plugins → Add New → Upload Plugin*) and activate it.
2. In WordPress admin, go to **Forms → Settings → Broadcaster**.
3. Paste your Broadcaster API key (generate one in Broadcaster under *Settings → API Keys*) and click **Save Settings**.
4. After save, the page shows **✓ Connected** if Broadcaster accepts the key, or **✗ Not connected** with a reason if it does not.

The Broadcaster site URL is hardcoded to `https://getbroadcaster.com` on production sites. On dev/staging/local environments (sites where `WP_ENVIRONMENT_TYPE` is not `production`) the URL field is editable so you can point at a non-prod Broadcaster instance.

= Configure a form =

1. Edit the Gravity Form you want to forward.
2. Open **Settings → Broadcaster** in the form editor and click **Add New** to create a feed.
3. Set the feed name and (optionally) a **Source label** — what shows in Broadcaster chat bubbles to identify where the message came from. Defaults to the form's title.
4. Map at least one of:
   * **Phone field** — the form field that holds the contact's phone number.
   * **WhatsApp username field** — for contacts who send via WhatsApp username/BSUID rather than phone.
5. Optionally map a **Submitter name field**.
6. Write the **Message text** the contact's submission becomes inside Broadcaster. Gravity Forms merge tags work — for example `{Message:5}` or `{Name (First):1.3}`.
7. Optionally fill the **In-hours template name** (the *internal* name of an approved Broadcaster template). If only this field is set, Broadcaster uses it at all times. If both this and the out-of-hours template name are set, Broadcaster picks by company business hours. If neither is set, no auto-response is sent.
8. Optionally fill the **In-hours template placeholders** as comma-separated `key:value` pairs. Values may include merge tags. Example:
   `text_1:{Message:5},client_first_name:{Name (First):1.3}`
9. Repeat for **Out-of-hours template name** and **Out-of-hours template placeholders** if you want a different reply outside business hours.
10. (Optional) Use Gravity Forms' standard **Conditional logic** to run the feed only when chosen form fields match.
11. Save the feed. Submit the form to test — the message should appear in Broadcaster within seconds, and the auto-response (if configured) should send shortly after.

== Frequently Asked Questions ==

= Do I need a paid Broadcaster account? =

Yes. This plugin is a connector for [Broadcaster](https://getbroadcaster.com), a paid SaaS platform for business WhatsApp management. Without an active Broadcaster account and an API key, the plugin has nothing to talk to.

= Is this an official Gravity Forms add-on? =

No. This plugin is built on the public Gravity Forms Feed Add-On Framework but is published independently by Fullworks. Gravity Forms itself must be installed separately.

= Will Broadcaster outages break my forms? =

No. If the Broadcaster API is unreachable or rejects the request, the form submission still completes — Gravity Forms confirmations and notification emails proceed as normal. The failed dispatch is recorded under *Forms → Settings → Logging* (when GF logging is enabled).

= Where do template names come from? =

You enter the **internal name** of an approved Broadcaster template (the slug-style name visible inside Broadcaster's template management UI, not the display title). Templates must already exist and be approved on the Broadcaster side — this plugin doesn't create or sync templates.

= What's the difference between "Message text" and the templates? =

The **Message text** is the inbound contact message stored in Broadcaster — what your support staff sees in the chat thread. The **template** is the optional outbound auto-response sent to the contact's WhatsApp. Broadcaster won't send a message to a recipient whose WhatsApp customer-service window isn't open *unless* a template is used; that's why this plugin offers template fields.

= Can I run multiple feeds on one form? =

Yes. Add as many feeds as you like per form. Use Gravity Forms' standard conditional logic to send different submissions to different Broadcaster destinations.

= How do I match by WhatsApp username only (no phone)? =

Map the **WhatsApp username field** but leave the phone field unmapped. Broadcaster will store/match by username. Note that auto-response template delivery currently requires a phone or BSUID Broadcaster can resolve — username-only contacts can be created but template delivery may report `recipient_not_deliverable` until that resolves.

= What's the WhatsApp Recipient field, and should I use it instead of a stock phone field? =

It's a custom field type this plugin adds, found under *Advanced Fields* in the Gravity Forms editor. It accepts a phone number in any reasonable format (with or without a country code), validates inline before submit so the visitor sees typos as a "Please enter a valid phone number" error and corrects them on the spot, and pre-cleans the value to international format so Broadcaster receives clean data. Use it on new forms and on any form where silent lead-drops have been a problem. Existing forms with stock Gravity Forms phone / text fields continue to work exactly as before — the new field is opt-in. (A future release will also accept WhatsApp usernames in the same field, once Meta enables that feature publicly.)

= Phone numbers in national format don't match my existing contacts. Why? =

Broadcaster's phone normalization needs to know which country to interpret a national-format number against. The **WhatsApp Recipient** field handles this for you: configure a default country under *Forms → Settings → Broadcaster → Phone normalisation*, and the field assumes that country whenever a submitter types a national-format number (and overrides it whenever they type an explicit `+` international prefix). For stock Gravity Forms phone fields, configure the field to require international format, or rely on Broadcaster's company-level default phone country.

== Screenshots ==

1. *Forms → Settings → Broadcaster*: paste your API key, see live connection status.
2. *Form Settings → Broadcaster*: per-form feed list.
3. *Add New Feed*: field mappings, message text, in-hours / out-of-hours templates, conditional logic.

(Screenshots are not yet bundled with this release.)

== Changelog ==

= 1.1.2 =
* **The auto-response outcome is now visible in Gravity Forms.** After Broadcaster accepts a submission, the plugin reads the `auto_response` block from the response and writes a clearer entry note: "sent auto-response (`<template>`, slot: `<in_hours|out_of_hours>`)" when the reply went out, "auto-response failed: `<error_code>` — `<message>`" when Broadcaster declined to send it (e.g. a missing template placeholder or an unapproved template), or the existing "received this submission (HTTP 201)." when the feed has no template configured. The full result is also logged under *Forms → Settings → Logging* with the entry ID for correlation.
* **Fixed:** the API response body was parsed from the wrong path, so the `auto_response` error code (used by the WhatsApp Recipient field rejection notices) was never actually read. It now reads `data.auto_response` correctly, so recipient rejections through a WhatsApp Recipient field surface inline as intended.

= 1.1.1 =
* **WhatsApp Recipient field now has a Field Size setting.** Like other Gravity Forms fields, it now shows the Small / Medium / Large size control in the form editor's field settings; previously the size was fixed and could not be changed from the editor. New fields still default to Large.
* **WhatsApp Recipient field can now be used in conditional logic.** It now appears in the "based on" field list when adding conditional logic to other fields, feeds, notifications and confirmations — e.g. show or hide another field depending on whether a WhatsApp recipient was entered. Previously the field was missing from those lists.
* **National-format phone numbers are now shown — and stored — in international format.** When a submitter types a national-format number into a WhatsApp Recipient field and moves to the next field, the value is rewritten to international format (for example `07714681600` becomes `+447714681600`, using the field's or plugin's default country) so the country assumption is visible before submitting. The Gravity Forms entry, exports and the feed's recipient mapping then carry the international number. WhatsApp usernames (`@name`) are never altered — they are stored exactly as entered. If JavaScript is unavailable the number is still normalised when the entry is saved.
* **Fixed:** uninstalling the plugin now removes its stored settings. A stale option name meant the connection, phone-country and related settings were previously left in the database after uninstall.
* **Maintenance:** bundled the translation template (`.pot`), simplified how the add-on menu icon loads, refreshed the plugin home-page link, and added explicit ownership / trademark notes. No functional change.

= 1.1.0 =
* **New WhatsApp Recipient field.** A dedicated custom field type, under *Advanced Fields* in the Gravity Forms editor, that captures the contact's phone number in a single input and validates the format inline before form submission. Submitters see typos as a "Please enter a valid phone number" error and correct them on the spot, instead of having the form succeed and Broadcaster reject the lead silently downstream. Existing forms with stock Gravity Forms phone / text field mappings keep working exactly as before — the new field is opt-in.
* **New "Default phone country" plugin setting** (*Forms → Settings → Broadcaster → Phone normalisation*). When a submitter types a national-format number into a WhatsApp Recipient field, this is the country we will assume. ISO-3166 alpha-2 picker covering United Kingdom, United States, Ireland, Australia, India, Indonesia. Each WhatsApp Recipient field also has its own per-field override for forms that target a different country than the rest of the site.
* **Broadcaster recipient rejections are now visible to administrators.** When Broadcaster declines a recipient through a WhatsApp Recipient field — HTTP 422 on dispatch, or `recipient_not_deliverable` in the response — the rejection is surfaced inline beside the field on the Gravity Forms entry detail view, plus an audit-trail note in the GF notes panel and a one-line log entry under *Forms → Settings → Logging*. Form submission itself is never blocked; this only affects the diagnostic surface administrators see after the fact.

= 1.0.2 =
* **Critical fix:** the v1.0.0 and v1.0.1 release zips were built without the plugin's `vendor/autoload.php`, causing a fatal error when opening *Forms → Settings → Broadcaster* (`Class "BroadcasterGF\Api\Client" not found`). The release workflow now runs `composer install --no-dev --optimize-autoloader` inside the plugin directory before packaging the zip, so the autoloader and namespaced classes ship correctly. Anyone running v1.0.0 or v1.0.1 should upgrade immediately.

= 1.0.1 =
* Tightened the WordPress.org short description to fit within the 150-character limit.
* Release-workflow polish: corrected the `vv1.0.0`-style title in GitHub Release notes, fixed the boilerplate-leftover settings-path text, bumped `actions/checkout` and `softprops/action-gh-release` to versions that run on Node.js 24.
* No functional changes to the plugin code itself.

= 1.0.0 =
* Initial release.
* Per-form Gravity Forms feeds that forward submissions into Broadcaster as incoming WhatsApp contact messages.
* Optional in-hours / out-of-hours template auto-responses with comma-separated `key:value` placeholders and merge-tag support in values.
* Connection settings under *Forms → Settings → Broadcaster* with live ✓/✗ feedback against the saved API key.
* Production-locked Broadcaster URL (`https://getbroadcaster.com`); editable on dev/staging/local.
* Source-label feed setting that populates the chat-bubble origin shown in Broadcaster.
* Failures never block Gravity Forms confirmation or notification emails; diagnostics flow to *Forms → Settings → Logging*.

== Troubleshooting ==

= "Broadcaster API key is not configured" admin notice =

Configure the API key under *Forms → Settings → Broadcaster*.

= "Gravity Forms is not active" admin notice =

Install and activate Gravity Forms. This plugin needs Gravity Forms to do anything; it sits idle until Gravity Forms is present.

= "✗ Not connected. Broadcaster rejected the API key (HTTP 401/403)." =

The saved key is wrong, has been revoked, or belongs to a different company. Re-issue an API key in Broadcaster under *Settings → API Keys*, paste it on the WP side, save.

= "✗ Not connected. Broadcaster URL responded but the messages endpoint was not found (HTTP 404)." =

Only relevant on dev/staging/local. The URL points at a host that isn't a Broadcaster instance, or one that hasn't been updated to a build that includes the contact-form API. Production sites use `https://getbroadcaster.com` and won't see this.

= "✗ Not connected. Cannot reach Broadcaster: …" =

The site can't open an HTTPS connection to Broadcaster. Check outbound firewall / proxy / DNS on the WordPress server.

= Form submission shows no error but nothing arrives in Broadcaster =

Open *Forms → Settings → Logging* and enable the **Broadcaster Auto Responder for Gravity Forms** logger at *Log all messages*. Submit again, then download the log. Common causes:

* Conditional logic on the feed didn't match the submission.
* Neither phone nor WhatsApp username was mapped, or the mapped field was empty.
* Template name in feed config doesn't match an approved Broadcaster template.
* Required template variable missing from placeholder map.
* Phone/username normalization mismatch — contact gets created on the Broadcaster side but template send fails because the recipient can't be resolved.

= "Broadcaster rejected the message (HTTP 422). The form id field must be a string." =

Indicates a plugin/Broadcaster version mismatch. Upgrade this plugin to 1.0.0 or later.

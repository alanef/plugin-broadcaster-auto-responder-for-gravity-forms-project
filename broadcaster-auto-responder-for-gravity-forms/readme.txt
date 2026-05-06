=== Broadcaster Auto Responder for Gravity Forms ===
Contributors: alanef, fullworks
Tags: gravity forms, broadcaster, whatsapp, auto responder, contact form
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sends Gravity Forms submissions to Broadcaster as incoming WhatsApp contact messages and triggers an optional template auto-response.

== Description ==

This plugin connects Gravity Forms to a Broadcaster account so that website contact form submissions are injected into Broadcaster as incoming WhatsApp contact messages. Each submission can also trigger an optional in-business-hours or out-of-business-hours WhatsApp template auto-response.

Site administrators configure the connection once under **Settings → Broadcaster GF**, then enable Broadcaster delivery on individual Gravity Forms via the standard feed configuration.

Broadcaster, Gravity Forms, and WhatsApp are independent products and are referenced here only for compatibility.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/broadcaster-auto-responder-for-gravity-forms` and activate it.
2. Visit **Settings → Broadcaster GF** in the WordPress admin.
3. Enter your Broadcaster site URL and Broadcaster API key, then click **Test connection**.
4. Enable Broadcaster delivery on the Gravity Forms you want to forward (configured per form, see plugin documentation).

== Changelog ==

= 1.0.0 =
* Initial release: settings page, Broadcaster connection validation, Gravity Forms dependency notice.

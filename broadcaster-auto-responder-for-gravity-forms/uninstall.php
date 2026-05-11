<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package BroadcasterGF
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * The plugin's settings (API key, Broadcaster URL, default phone country) are
 * stored by the Gravity Forms add-on framework under the standard
 * `gravityformsaddon_{slug}_settings` option key — see
 * BroadcasterGF\Plugin::SETTINGS_OPTION_KEY. Remove it on uninstall.
 *
 * Per-feed configuration lives in Gravity Forms' own `gf_addon_feed` table and
 * per-entry rejection markers in GF entry meta; both are owned by Gravity Forms
 * and are not touched here.
 */
delete_option( 'gravityformsaddon_broadcaster-auto-responder-for-gravity-forms_settings' );
delete_site_option( 'gravityformsaddon_broadcaster-auto-responder-for-gravity-forms_settings' );

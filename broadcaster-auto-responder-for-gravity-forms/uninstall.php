<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package BroadcasterGF
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'broadcastergf_settings' );
delete_site_option( 'broadcastergf_settings' );

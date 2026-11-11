<?php
/**
 * Removes plugin data when an Administrator deletes the plugin.
 *
 * WordPress runs this file on its own, without the main plugin file. Data is
 * removed only when an Administrator opted in under Settings > Data and cleanup.
 *
 * @package WP_Buyer_Blacklist
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/autoload.php';

( static function (): void {
	if ( ! get_option( 'wp_buyer_blacklist_delete_data_on_uninstall', false ) ) {
		return;
	}

	global $wpdb;

	( new \WP_Buyer_Blacklist\Storage\Schema( $wpdb ) )->uninstall();
} )();

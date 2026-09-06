<?php
/**
 * Plugin Name: WP Buyer Blacklist
 * Description: Blocks specified Buyers from completing WooCommerce checkout.
 * Version: 0.1.0
 * Requires at least: 6.9
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * Text Domain: wp-buyer-blacklist
 *
 * @package WP_Buyer_Blacklist
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_BUYER_BLACKLIST_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_BUYER_BLACKLIST_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/src/EmailCanonicalizer.php';
require_once __DIR__ . '/src/EmailEntry.php';
require_once __DIR__ . '/src/EmailEntryRepository.php';
require_once __DIR__ . '/src/Plugin.php';

/**
 * Creates plugin database tables when WooCommerce is available.
 *
 * @return void
 */
function wp_buyer_blacklist_activate(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	global $wpdb;

	$repository = new \WP_Buyer_Blacklist\EmailEntryRepository( $wpdb );
	$repository->install();
}
register_activation_hook( __FILE__, 'wp_buyer_blacklist_activate' );

/**
 * Registers WooCommerce compatibility before initialization.
 *
 * @return void
 */
function wp_buyer_blacklist_declare_woocommerce_compatibility(): void {
	if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'wp_buyer_blacklist_declare_woocommerce_compatibility' );

/**
 * Starts the plugin once other plugins have loaded.
 *
 * @return void
 */
function wp_buyer_blacklist_boot(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wp_buyer_blacklist_missing_woocommerce_notice' );
		return;
	}

	global $wpdb;

	$repository = new \WP_Buyer_Blacklist\EmailEntryRepository( $wpdb );
	$repository->install();
	$plugin = new \WP_Buyer_Blacklist\Plugin( $repository );
	$plugin->register();
}
add_action( 'plugins_loaded', 'wp_buyer_blacklist_boot' );

/**
 * Shows the administrator-only WooCommerce dependency notice.
 *
 * @return void
 */
function wp_buyer_blacklist_missing_woocommerce_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'WP Buyer Blacklist requires WooCommerce. Blacklist operations are unavailable until WooCommerce is active.', 'wp-buyer-blacklist' )
	);
}

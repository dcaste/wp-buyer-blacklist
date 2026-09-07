<?php
/**
 * Boots WordPress and WooCommerce integration tests.
 *
 * @package WP_Buyer_Blacklist
 */

$wordpress_tests_dir = getenv( 'WP_TESTS_DIR' );
$woocommerce_dir     = getenv( 'WC_PLUGIN_DIR' );

if ( ! is_string( $wordpress_tests_dir ) || ! is_dir( $wordpress_tests_dir ) || ! is_string( $woocommerce_dir ) || ! is_dir( $woocommerce_dir ) ) {
	throw new RuntimeException( 'Set WP_TESTS_DIR and WC_PLUGIN_DIR before running integration tests.' );
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once $wordpress_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $woocommerce_dir ): void {
		require_once $woocommerce_dir . '/woocommerce.php';
		require_once dirname( __DIR__, 2 ) . '/wp-buyer-blacklist.php';
	}
);

require_once $wordpress_tests_dir . '/includes/bootstrap.php';

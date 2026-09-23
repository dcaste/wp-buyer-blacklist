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

// Each test runs inside one transaction that is rolled back afterwards. Code under test that
// opens its own transaction would commit it and leak rows into later tests, so nest it as a savepoint.
add_filter(
	'query',
	static function ( string $query ): string {
		static $open = false;
		$statement   = trim( $query );

		// The test framework's own statements end in a semicolon; a new test must not inherit a savepoint left open.
		if ( 'START TRANSACTION;' === $statement ) {
			$open = false;
			return $query;
		}
		if ( 'START TRANSACTION' === $statement ) {
			$open = true;
			return 'SAVEPOINT wp_buyer_blacklist_test';
		}
		if ( $open && 'COMMIT' === $statement ) {
			$open = false;
			return 'RELEASE SAVEPOINT wp_buyer_blacklist_test';
		}
		if ( $open && 'ROLLBACK' === $statement ) {
			$open = false;
			return 'ROLLBACK TO SAVEPOINT wp_buyer_blacklist_test';
		}

		return $query;
	}
);

require_once __DIR__ . '/support/RedirectException.php';
require_once __DIR__ . '/support/AdminPostRequests.php';
require_once __DIR__ . '/support/AdminInit.php';
require_once __DIR__ . '/support/AdminScreens.php';
require_once __DIR__ . '/support/HtmlSnapshots.php';

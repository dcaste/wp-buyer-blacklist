<?php
/**
 * Tests configurable Blacklist operator access.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\EmailEntryRepository;
use WP_Buyer_Blacklist\Plugin;

/**
 * Covers the administrator-controlled operator setting.
 */
class OperatorAccessIntegrationTest extends WP_UnitTestCase {

	/**
	 * Lets WooCommerce managers operate the Blacklist by default.
	 *
	 * @return void
	 */
	public function test_woocommerce_manager_can_manage_entries_by_default(): void {
		global $wpdb;
		delete_option( 'wp_buyer_blacklist_allow_woocommerce_managers' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$this->assertTrue( ( new Plugin( new EmailEntryRepository( $wpdb ) ) )->can_manage_entries() );
	}

	/**
	 * Restricts entry management to Administrators when disabled.
	 *
	 * @return void
	 */
	public function test_disabled_manager_access_requires_manage_options(): void {
		global $wpdb;
		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', false );
		$plugin = new Plugin( new EmailEntryRepository( $wpdb ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->assertFalse( $plugin->can_manage_entries() );
		$this->assertSame( 'manage_options', ( new \WP_Buyer_Blacklist\OperatorAccess() )->menu_capability() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( $plugin->can_manage_entries() );
		$this->assertSame( 'manage_options', ( new \WP_Buyer_Blacklist\OperatorAccess() )->menu_capability() );
	}

	/**
	 * Registers operator screens for an Administrator without WooCommerce access.
	 *
	 * @return void
	 */
	public function test_administrator_menu_uses_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 'manage_options', ( new \WP_Buyer_Blacklist\OperatorAccess() )->menu_capability() );
	}

	/**
	 * Hides Users-list enrollment actions from a manager when access is disabled.
	 *
	 * @return void
	 */
	public function test_disabled_manager_access_hides_users_list_enrollment_actions(): void {
		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$customer = new WP_User( self::factory()->user->create( array( 'role' => 'customer' ) ) );

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress defines the Users bulk-action hook with a hyphen.
		$this->assertArrayNotHasKey( 'wp_buyer_blacklist_enroll', apply_filters( 'bulk_actions-users', array() ) );
		$this->assertSame( '', apply_filters( 'manage_users_custom_column', '', 'wp_buyer_blacklist', $customer->ID ) );
	}

	/**
	 * Applies the setting to every entry-management REST route.
	 *
	 * @return void
	 */
	public function test_entry_management_rest_routes_use_operator_access_setting(): void {
		global $wpdb;
		$plugin = new Plugin( new EmailEntryRepository( $wpdb ) );
		$plugin->register_rest_routes();
		$routes = rest_get_server()->get_routes();
		$paths  = array(
			'/wp-buyer-blacklist/v1/email-entries',
			'/wp-buyer-blacklist/v1/address-entries',
			'/wp-buyer-blacklist/v1/csv-import/validate',
			'/wp-buyer-blacklist/v1/csv-import/confirm',
			'/wp-buyer-blacklist/v1/email-entries/(?P<id>\\d+)/blocked-attempts',
			'/wp-buyer-blacklist/v1/email-entries/(?P<id>\\d+)',
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		foreach ( $paths as $path ) {
			foreach ( $routes[ $path ] as $endpoint ) {
				$this->assertSame( 'DELETE' !== $endpoint['methods'], call_user_func( $endpoint['permission_callback'] ) );
			}
		}

		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', false );
		foreach ( $paths as $path ) {
			foreach ( $routes[ $path ] as $endpoint ) {
				$this->assertFalse( call_user_func( $endpoint['permission_callback'] ) );
			}
		}
	}

	/**
	 * Clears the access setting after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( 'wp_buyer_blacklist_allow_woocommerce_managers' );
		parent::tear_down();
	}
}

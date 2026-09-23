<?php
/**
 * Tests configurable Blacklist operator access.
 *
 * @package WP_Buyer_Blacklist
 */

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
		delete_option( 'wp_buyer_blacklist_allow_woocommerce_managers' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$this->assertSame( 200, $this->list_entries_status() );
	}

	/**
	 * Restricts entry management to Administrators when disabled.
	 *
	 * @return void
	 */
	public function test_disabled_manager_access_requires_manage_options(): void {
		// false would be skipped as unchanged while the option doesn't exist yet.
		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', '0' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->assertSame( 403, $this->list_entries_status() );
		$this->assertNull( $this->admin_menu_capability() );
		$this->assertArrayHasKey( 'wp-buyer-blacklist', $GLOBALS['_wp_submenu_nopriv']['woocommerce'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 200, $this->list_entries_status() );
		$this->assertSame( 'manage_options', $this->admin_menu_capability() );
	}

	/**
	 * Registers operator screens for an Administrator without WooCommerce access.
	 *
	 * @return void
	 */
	public function test_administrator_menu_uses_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 'manage_options', $this->admin_menu_capability() );
	}

	/**
	 * Hides Users-list enrollment actions from a manager when access is disabled.
	 *
	 * @return void
	 */
	public function test_disabled_manager_access_hides_users_list_enrollment_actions(): void {
		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', '0' );
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
				$this->assertSame( ! isset( $endpoint['methods']['DELETE'] ), call_user_func( $endpoint['permission_callback'] ) );
			}
		}

		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', '0' );
		foreach ( $paths as $path ) {
			foreach ( $routes[ $path ] as $endpoint ) {
				$this->assertFalse( call_user_func( $endpoint['permission_callback'] ) );
			}
		}
	}

	/**
	 * Lists entries through REST as the current user.
	 *
	 * @return int Response status.
	 */
	private function list_entries_status(): int {
		return rest_do_request( new WP_REST_Request( 'GET', '/wp-buyer-blacklist/v1/email-entries' ) )->get_status();
	}

	/**
	 * Builds the admin menu for the current user and finds the Buyer Blacklist page.
	 *
	 * @return string|null The page's capability, or null when the current user can't see it.
	 */
	private function admin_menu_capability(): ?string {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Each check starts from an empty admin menu.
		$GLOBALS['menu']               = array();
		$GLOBALS['submenu']            = array();
		$GLOBALS['_wp_submenu_nopriv'] = array();
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
		do_action( 'admin_menu', '' );

		foreach ( $GLOBALS['submenu']['woocommerce'] ?? array() as $item ) {
			if ( 'wp-buyer-blacklist' === $item[2] ) {
				return $item[1];
			}
		}
		return null;
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

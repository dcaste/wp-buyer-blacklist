<?php
/**
 * Tests Blacklist enrollment actions in WooCommerce administration.
 *
 * @package WP_Buyer_Blacklist
 */

/**
 * Covers the Customer list enrollment action.
 */
class AdminEnrollmentIntegrationTest extends WP_UnitTestCase {

	/**
	 * Adds an enrollment action for a WooCommerce Customer in the Users list.
	 *
	 * @return void
	 */
	public function test_adds_an_enrollment_action_to_a_customer_row(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$customer = new WP_User( self::factory()->user->create( array( 'role' => 'customer' ) ) );

		$actions = apply_filters( 'user_row_actions', array(), $customer );

		$this->assertArrayHasKey( 'wp_buyer_blacklist_enroll', $actions );
		$this->assertStringContainsString( 'Enroll in Blacklist', $actions['wp_buyer_blacklist_enroll'] );
	}

	/**
	 * Marks a Customer with an active matching entry and links to its entries.
	 *
	 * @return void
	 */
	public function test_marks_a_blacklisted_customer_and_links_to_matching_entries(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$customer   = new WP_User(
			self::factory()->user->create(
				array(
					'role'       => 'customer',
					'user_email' => 'buyer@example.com',
				)
			)
		);
		$repository = new \WP_Buyer_Blacklist\EmailEntryRepository( $GLOBALS['wpdb'] );
		$repository->create( \WP_Buyer_Blacklist\EmailEntry::from_email( 'buyer@example.com' ), 'Test entry' );

		$actions = apply_filters( 'user_row_actions', array(), $customer );

		$this->assertStringContainsString( 'Blacklisted', $actions['wp_buyer_blacklist_badge'] );
		$this->assertStringContainsString( 'Manage Blacklist entries', $actions['wp_buyer_blacklist_enroll'] );
		$this->assertStringContainsString( 'page=wp-buyer-blacklist', $actions['wp_buyer_blacklist_enroll'] );
		$this->assertStringContainsString( 'entry_ids', $actions['wp_buyer_blacklist_enroll'] );
	}

	/**
	 * Adds enrollment actions to Customer and Order bulk and detail workflows.
	 *
	 * @return void
	 */
	public function test_adds_customer_and_order_enrollment_actions(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$order = wc_create_order();

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress defines the Users bulk-action hook with a hyphen.
		$this->assertArrayHasKey( 'wp_buyer_blacklist_enroll', apply_filters( 'bulk_actions-users', array() ) );
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress defines the Orders bulk-action hook with a hyphen.
		$this->assertArrayHasKey( 'wp_buyer_blacklist_enroll', apply_filters( 'bulk_actions-edit-shop_order', array() ) );
		$this->assertArrayHasKey( 'wp_buyer_blacklist_enroll', apply_filters( 'woocommerce_order_actions', array(), $order ) );
		$this->assertArrayHasKey( 'wp_buyer_blacklist_enroll', apply_filters( 'woocommerce_admin_order_actions', array(), $order ) );
	}
}

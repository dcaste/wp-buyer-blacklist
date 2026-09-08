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
	 * Adds an enrollment link to the Customer Blacklist column.
	 *
	 * @return void
	 */
	public function test_adds_an_enrollment_link_to_a_customer_column(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$customer = new WP_User( self::factory()->user->create( array( 'role' => 'customer' ) ) );

		$columns = apply_filters( 'manage_users_columns', array() );
		$output  = apply_filters( 'manage_users_custom_column', '', 'wp_buyer_blacklist', $customer->ID );

		$this->assertArrayHasKey( 'wp_buyer_blacklist', $columns );
		$this->assertStringContainsString( 'Enroll in Blacklist', $output );
		$this->assertSame( array(), apply_filters( 'user_row_actions', array(), $customer ) );
	}

	/**
	 * Leaves the column empty for users who are not WooCommerce Customers.
	 *
	 * @return void
	 */
	public function test_leaves_the_column_empty_for_non_customers(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertSame( '', apply_filters( 'manage_users_custom_column', '', 'wp_buyer_blacklist', $user ) );
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

		$output = apply_filters( 'manage_users_custom_column', '', 'wp_buyer_blacklist', $customer->ID );

		$this->assertStringContainsString( 'Blacklisted', $output );
		$this->assertStringContainsString( 'Manage Blacklist entries', $output );
		$this->assertStringContainsString( 'page=wp-buyer-blacklist', $output );
		$this->assertStringContainsString( 'entry_ids', $output );
	}

	/**
	 * Marks a Customer with only inactive matching entries.
	 *
	 * @return void
	 */
	public function test_marks_a_customer_with_only_inactive_entries(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$customer   = new WP_User(
			self::factory()->user->create(
				array(
					'role'       => 'customer',
					'user_email' => 'inactive@example.com',
				)
			)
		);
		$repository = new \WP_Buyer_Blacklist\EmailEntryRepository( $GLOBALS['wpdb'] );
		$repository->create( \WP_Buyer_Blacklist\EmailEntry::from_email( 'inactive@example.com' ), 'Test entry' );
		$entry = $repository->all()[0];
		$repository->set_active( (int) $entry['id'], false );

		$output = apply_filters( 'manage_users_custom_column', '', 'wp_buyer_blacklist', $customer->ID );

		$this->assertStringContainsString( 'Inactive', $output );
		$this->assertStringContainsString( 'Manage Blacklist entries', $output );
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

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

	use AdminPostRequests;

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
		$this->assertStringContainsString( 'Enroll', $output );
		$this->assertStringNotContainsString( 'wp-buyer-blacklist-status-badge', $output );
		$this->assertSame( array(), apply_filters( 'user_row_actions', array(), $customer ) );
	}

	/**
	 * Renders the Users-list modal with the current Buyer data fields.
	 *
	 * @return void
	 */
	public function test_renders_a_customer_enrollment_modal(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$customer_id = self::factory()->user->create(
			array(
				'role'       => 'customer',
				'first_name' => 'Ada',
				'last_name'  => 'Lovelace',
				'user_email' => 'buyer@example.com',
			)
		);
		update_user_meta( $customer_id, 'billing_address_1', '1 Test Street' );
		update_user_meta( $customer_id, 'billing_city', 'Testville' );
		update_user_meta( $customer_id, 'billing_state', 'CA' );
		update_user_meta( $customer_id, 'billing_postcode', '90210' );
		update_user_meta( $customer_id, 'billing_country', 'US' );
		$button = apply_filters( 'manage_users_custom_column', '', 'wp_buyer_blacklist', $customer_id );

		ob_start();
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress defines this Users-screen footer hook with a hyphen and period.
		do_action( 'admin_footer-users.php' );
		$modal = ob_get_clean();

		$this->assertStringContainsString( '<button', $button );
		$this->assertStringContainsString( 'data-buyer=', $button );
		$this->assertStringContainsString( 'buyer@example.com', $button );
		$this->assertStringContainsString( '1 Test Street', $button );
		$this->assertStringContainsString( '<dialog', $modal );
		$this->assertStringContainsString( 'WordPress user ID', $modal );
		$this->assertStringContainsString( 'Billing address', $modal );
		$this->assertStringContainsString( 'Shipping address', $modal );
		$this->assertStringContainsString( 'name="match_type"', $modal );
		$this->assertStringContainsString( 'name="reason" required', $modal );
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
		$this->assertStringContainsString( 'Manage', $output );
		$this->assertStringContainsString( 'This Buyer cannot place new orders.', $output );
		$this->assertStringContainsString( 'wp-buyer-blacklist-status-badge is-active', $output );
		$this->assertStringContainsString( 'wp-buyer-blacklist-manage-customer', $output );
		$this->assertStringNotContainsString( 'page=wp-buyer-blacklist', $output );
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
		$this->assertStringContainsString( 'Manage', $output );
	}

	/**
	 * Includes every matching entry in the Users-list manager selector.
	 *
	 * @return void
	 */
	public function test_lists_multiple_matching_entries_for_management(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$customer_id = self::factory()->user->create(
			array(
				'role'       => 'customer',
				'user_email' => 'multiple@example.com',
			)
		);
		$repository  = new \WP_Buyer_Blacklist\EmailEntryRepository( $GLOBALS['wpdb'] );
		$repository->create( \WP_Buyer_Blacklist\EmailEntry::from_email( 'multiple@example.com' ), 'Email match' );
		$repository->create_customer_id( \WP_Buyer_Blacklist\CustomerIdEntry::from_customer_id( $customer_id ), 'Customer ID match' );

		$output = apply_filters( 'manage_users_custom_column', '', 'wp_buyer_blacklist', $customer_id );

		$this->assertStringContainsString( 'data-entries=', $output );
		$this->assertStringContainsString( 'Email match', $output );
		$this->assertStringContainsString( 'Customer ID match', $output );
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

	/**
	 * Creates one entry from the current Customer data and records its Audit event.
	 *
	 * @return void
	 */
	public function test_enrolls_a_customer_with_one_selected_match_type(): void {
		global $wpdb;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$customer_id = self::factory()->user->create(
			array(
				'role'       => 'customer',
				'user_email' => 'buyer@example.com',
			)
		);
		$repository  = new \WP_Buyer_Blacklist\EmailEntryRepository( $wpdb );

		$this->assertSame( 'created', $this->enroll_customer( $customer_id, 'email', 'Fraud' ) );
		$entry = $repository->all()[0];

		$this->assertSame( 'email', $entry['match_type'] );
		$this->assertSame( 'buyer@example.com', $entry['value'] );
		$this->assertSame( 'created', $repository->audit_events( (int) $entry['id'] )[0]['action'] );
	}

	/**
	 * Rejects an entry whose selected identity already exists.
	 *
	 * @return void
	 */
	public function test_reports_a_duplicate_customer_identity(): void {
		global $wpdb;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$customer_id = self::factory()->user->create(
			array(
				'role'       => 'customer',
				'user_email' => 'buyer@example.com',
			)
		);
		$repository  = new \WP_Buyer_Blacklist\EmailEntryRepository( $wpdb );

		$this->assertSame( 'created', $this->enroll_customer( $customer_id, 'email', 'Fraud' ) );
		$this->assertSame( 'duplicate', $this->enroll_customer( $customer_id, 'email', 'Fraud' ) );
		$this->assertCount( 1, $repository->all() );
	}

	/**
	 * Denies a WooCommerce manager when the operator setting is disabled.
	 *
	 * @return void
	 */
	public function test_denies_customer_enrollment_without_operator_access(): void {
		global $wpdb;
		// false would be skipped as unchanged while the option doesn't exist yet.
		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', '0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$customer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		$repository  = new \WP_Buyer_Blacklist\EmailEntryRepository( $wpdb );

		try {
			$this->enroll_customer( $customer_id, 'customer_id', 'Fraud' );
			$this->fail( 'A manager without operator access enrolled a Customer.' );
		} catch ( WPDieException $denied ) {
			$this->assertSame( 403, $denied->getCode() );
		}
		$this->assertSame( array(), $repository->all() );
	}

	/**
	 * Submits the Users-list enrollment form.
	 *
	 * @param int    $customer_id WordPress user ID.
	 * @param string $match_type  Selected Match type.
	 * @param string $reason      Operator reason.
	 * @return string Enrollment result reported on the Users screen.
	 */
	private function enroll_customer( int $customer_id, string $match_type, string $reason ): string {
		$location = $this->submit_admin_post(
			'wp_buyer_blacklist_enroll_customer',
			array(
				'_wpnonce'    => wp_create_nonce( 'wp_buyer_blacklist_enroll_customer' ),
				'customer_id' => (string) $customer_id,
				'match_type'  => $match_type,
				'reason'      => $reason,
			)
		);
		parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
		$this->assertArrayHasKey( 'wp_buyer_blacklist_enrollment', $query );

		return $query['wp_buyer_blacklist_enrollment'];
	}
}

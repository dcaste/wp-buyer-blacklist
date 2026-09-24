<?php
/**
 * Tests enrollment from the Users and Orders screens and the enrollment page.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Entries\AuditLog;
use WP_Buyer_Blacklist\Enforcement\BlockedAttemptLog;
use WP_Buyer_Blacklist\Entries\EntryRepository;


/**
 * Covers the enrollment hooks not exercised by AdminEnrollmentIntegrationTest.
 */
class EnrollmentScreensIntegrationTest extends WP_UnitTestCase {

	use AdminPostRequests;
	use AdminScreens;
	use HtmlSnapshots;

	/**
	 * Enrollment page URL for Orders 7 and 8.
	 */
	private const ORDER_ENROLLMENT_URL = 'http://example.org/wp-admin/admin.php?page=wp-buyer-blacklist-enroll&source_type=order&source_ids%5B0%5D=7&source_ids%5B1%5D=8';

	/**
	 * Signs in an Administrator.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		WC_Install::create_tables();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Sends selected Customers to the enrollment page.
	 *
	 * @return void
	 */
	public function test_customer_bulk_action_opens_the_enrollment_page(): void {
		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress defines the Users bulk-action hook with a hyphen.
		$this->assertSame(
			'http://example.org/wp-admin/admin.php?page=wp-buyer-blacklist-enroll&source_type=customer&source_ids%5B0%5D=7&source_ids%5B1%5D=8',
			apply_filters( 'handle_bulk_actions-users', 'http://example.org/wp-admin/users.php', 'wp_buyer_blacklist_enroll', array( 7, 8 ) )
		);
		$this->assertSame( 'http://example.org/wp-admin/users.php', apply_filters( 'handle_bulk_actions-users', 'http://example.org/wp-admin/users.php', 'delete', array( 7 ) ) );

		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', '0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->assertSame( 'http://example.org/wp-admin/users.php', apply_filters( 'handle_bulk_actions-users', 'http://example.org/wp-admin/users.php', 'wp_buyer_blacklist_enroll', array( 7 ) ) );
		// phpcs:enable WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Adds an enrollment link to a Customer's profile.
	 *
	 * @return void
	 */
	public function test_customer_profile_shows_an_enrollment_link(): void {
		$customer = new WP_User( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$link     = '<tr><th>Buyer Blacklist</th><td><a href="http://example.org/wp-admin/admin.php?page=wp-buyer-blacklist-enroll&amp;source_type=customer&amp;source_ids%5B0%5D=' . $customer->ID . '">Enroll in Blacklist</a></td></tr>';

		foreach ( array( 'show_user_profile', 'edit_user_profile' ) as $hook ) {
			ob_start();
			do_action( $hook, $customer );
			$this->assertSame( $link, trim( (string) ob_get_clean() ) );
		}

		ob_start();
		do_action( 'edit_user_profile', new WP_User( self::factory()->user->create( array( 'role' => 'subscriber' ) ) ) );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Reports the Users-list enrollment result on the Users screen only.
	 *
	 * @return void
	 */
	public function test_users_screen_reports_the_enrollment_result(): void {
		$this->assertSame( '<div class="notice notice-success is-dismissible"><p>Blacklist entry created.</p></div>', $this->admin_notices( 'users.php', 'created' ) );
		$this->assertSame( '<div class="notice notice-warning is-dismissible"><p>A Blacklist entry with that Match type and value already exists.</p></div>', $this->admin_notices( 'users.php', 'duplicate' ) );
		$this->assertSame( '<div class="notice notice-error is-dismissible"><p>The selected Match type has no usable current value for this Buyer.</p></div>', $this->admin_notices( 'users.php', 'unavailable' ) );
		$this->assertSame( '', $this->admin_notices( 'users.php', 'denied' ) );
		$this->assertSame( '', $this->admin_notices( 'edit.php', 'created' ) );
	}

	/**
	 * Adds the bulk action to both Orders screens and sends selected Orders to the enrollment page.
	 *
	 * @return void
	 */
	public function test_order_bulk_actions_open_the_enrollment_page(): void {
		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress defines the Orders bulk-action hooks with hyphens.
		$this->assertSame( array( 'wp_buyer_blacklist_enroll' => 'Enroll in Blacklist' ), apply_filters( 'bulk_actions-woocommerce_page_wc-orders', array() ) );

		foreach ( array( 'handle_bulk_actions-edit-shop_order', 'handle_bulk_actions-woocommerce_page_wc-orders' ) as $hook ) {
			$this->assertSame( self::ORDER_ENROLLMENT_URL, apply_filters( $hook, 'http://example.org/wp-admin/edit.php', 'wp_buyer_blacklist_enroll', array( 7, 8 ) ) );
			$this->assertSame( 'http://example.org/wp-admin/edit.php', apply_filters( $hook, 'http://example.org/wp-admin/edit.php', 'trash', array( 7 ) ) );
		}
		// phpcs:enable WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Links an Order row to the enrollment page.
	 *
	 * @return void
	 */
	public function test_order_row_action_links_to_the_enrollment_page(): void {
		$order = wc_create_order();

		$this->assertSame(
			array(
				'wp_buyer_blacklist_enroll' => array(
					'url'    => 'http://example.org/wp-admin/admin.php?page=wp-buyer-blacklist-enroll&source_type=order&source_ids%5B0%5D=' . $order->get_id(),
					'name'   => 'Enroll in Blacklist',
					'action' => 'wp-buyer-blacklist-enroll',
				),
			),
			apply_filters( 'woocommerce_admin_order_actions', array(), $order )
		);
	}

	/**
	 * Redirects the Order-detail action to the enrollment page.
	 *
	 * @return void
	 */
	public function test_order_detail_action_redirects_to_the_enrollment_page(): void {
		$order = wc_create_order();

		$location = $this->capture_redirect(
			static function () use ( $order ): void {
				do_action( 'woocommerce_order_action_wp_buyer_blacklist_enroll', $order );
			}
		);

		$this->assertSame( 'http://example.org/wp-admin/admin.php?page=wp-buyer-blacklist-enroll&source_type=order&source_ids%5B0%5D=' . $order->get_id(), $location );
	}

	/**
	 * Refuses the Order-detail action without operator access.
	 *
	 * @return void
	 */
	public function test_order_detail_action_refuses_without_operator_access(): void {
		$order = wc_create_order();
		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', '0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$this->assertSame( array(), apply_filters( 'woocommerce_order_actions', array(), $order ) );
		try {
			do_action( 'woocommerce_order_action_wp_buyer_blacklist_enroll', $order );
			$this->fail( 'A manager without operator access opened Order enrollment.' );
		} catch ( WPDieException $denied ) {
			$this->assertSame( 403, $denied->getCode() );
		}
	}

	/**
	 * Registers the hidden enrollment page under its admin_page_ hook.
	 *
	 * @return void
	 */
	public function test_registers_the_hidden_enrollment_page(): void {
		$this->build_admin_menu();

		$this->assertSame( 'admin_page_wp-buyer-blacklist-enroll', get_plugin_page_hookname( 'wp-buyer-blacklist-enroll', '' ) );
		$this->assertNotFalse( has_action( 'admin_page_wp-buyer-blacklist-enroll' ) );
	}

	/**
	 * Renders the enrollment page exactly as recorded.
	 *
	 * @return void
	 */
	public function test_enrollment_page_matches_its_snapshot(): void {
		$markup = $this->render_admin_page(
			'admin_page_wp-buyer-blacklist-enroll',
			array(
				'page'        => 'wp-buyer-blacklist-enroll',
				'source_type' => 'order',
				'source_ids'  => array( '101', '102' ),
			)
		);

		$this->assert_matches_html_snapshot( 'enrollment-page', $markup, array( wp_create_nonce( 'wp_buyer_blacklist_enroll_source' ) => '{enroll-source-nonce}' ) );
	}

	/**
	 * Shows the previous submission result above the enrollment form.
	 *
	 * @return void
	 */
	public function test_enrollment_page_reports_the_previous_result(): void {
		$markup = $this->render_admin_page(
			'admin_page_wp-buyer-blacklist-enroll',
			array(
				'page'        => 'wp-buyer-blacklist-enroll',
				'source_type' => 'order',
				'source_ids'  => array( '101' ),
				'created'     => '2',
				'duplicates'  => '1',
				'unavailable' => '3',
			)
		);

		$this->assertStringContainsString( '<div class="notice notice-success"><p>2 created, 1 duplicates skipped, 3 unavailable sources skipped.</p></div>', $markup );
	}

	/**
	 * Asks for sources when the enrollment page has none.
	 *
	 * @return void
	 */
	public function test_enrollment_page_asks_for_sources(): void {
		$this->assertSame(
			'<div class="wrap"><p>Select at least one Customer or Order to enroll.</p></div>',
			$this->render_admin_page( 'admin_page_wp-buyer-blacklist-enroll', array( 'page' => 'wp-buyer-blacklist-enroll' ) )
		);
	}

	/**
	 * Creates entries from selected Orders and reports created, duplicate and unavailable sources.
	 *
	 * @return void
	 */
	public function test_creates_entries_from_orders(): void {
		$with_email = wc_create_order();
		$with_email->set_billing_email( 'buyer@example.com' );
		$with_email->save();
		$without_email = wc_create_order();
		$ids           = array( $with_email->get_id(), $without_email->get_id() );
		$entries       = new EntryRepository( $GLOBALS['wpdb'], new AuditLog( $GLOBALS['wpdb'] ), new BlockedAttemptLog( $GLOBALS['wpdb'] ) );

		$first = $this->enroll_orders( $ids );

		$this->assertSame( 'http://example.org/wp-admin/admin.php?page=wp-buyer-blacklist-enroll&source_type=order&source_ids%5B0%5D=' . $ids[0] . '&source_ids%5B1%5D=' . $ids[1] . '&created=1&duplicates=0&unavailable=1', $first );
		$this->assertSame( array( 'buyer@example.com' ), wp_list_pluck( $entries->all(), 'value' ) );
		$this->assertSame( 'Chargeback', $entries->all()[0]['reason'] );

		$this->assertStringEndsWith( '&created=0&duplicates=1&unavailable=1', $this->enroll_orders( $ids ) );
		$this->assertCount( 1, $entries->all() );
	}

	/**
	 * Refuses source enrollment without operator access.
	 *
	 * @return void
	 */
	public function test_source_enrollment_refuses_without_operator_access(): void {
		$order = wc_create_order();
		$order->set_billing_email( 'buyer@example.com' );
		$order->save();
		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', '0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		try {
			$this->enroll_orders( array( $order->get_id() ) );
			$this->fail( 'A manager without operator access enrolled Orders.' );
		} catch ( WPDieException $denied ) {
			$this->assertSame( 'You cannot enroll Blacklist entries.', $denied->getMessage() );
		}
		$this->assertSame( array(), ( new EntryRepository( $GLOBALS['wpdb'], new AuditLog( $GLOBALS['wpdb'] ), new BlockedAttemptLog( $GLOBALS['wpdb'] ) ) )->all() );
	}

	/**
	 * Renders the Users-list enrollment modal exactly as recorded.
	 *
	 * @return void
	 */
	public function test_customer_enrollment_modal_matches_its_snapshot(): void {
		$markup = $this->render_at(
			'/wp-admin/users.php',
			static function (): void {
				// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress defines this Users-screen footer hook with a hyphen and period.
				do_action( 'admin_footer-users.php' );
			}
		);

		$this->assert_matches_html_snapshot( 'customer-enrollment-modal', $markup, array( wp_create_nonce( 'wp_buyer_blacklist_enroll_customer' ) => '{enroll-customer-nonce}' ) );
	}

	/**
	 * Loads the dialog script on the Users screen instead of printing it with the dialogs.
	 *
	 * @return void
	 */
	public function test_users_screen_loads_the_dialog_script(): void {
		$this->enqueue_admin_scripts( 'users.php' );

		$this->assertTrue( wp_script_is( 'wp-buyer-blacklist-customer-dialogs', 'enqueued' ) );
		$this->assertSame( WP_BUYER_BLACKLIST_URL . 'assets/js/customer-dialogs.js', wp_scripts()->registered['wp-buyer-blacklist-customer-dialogs']->src );
		$this->assertSame( array( 'wp-api-fetch' ), wp_scripts()->registered['wp-buyer-blacklist-customer-dialogs']->deps );
		$this->assertStringNotContainsString(
			'<script',
			$this->render_at(
				'/wp-admin/users.php',
				static function (): void {
					// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- WordPress defines this Users-screen footer hook with a hyphen and period.
					do_action( 'admin_footer-users.php' );
				}
			)
		);
	}

	/**
	 * Loads the dialog script only on the Users screen and only for Blacklist operators.
	 *
	 * @return void
	 */
	public function test_dialog_script_needs_the_users_screen_and_operator_access(): void {
		$this->enqueue_admin_scripts( 'edit.php' );
		$this->assertFalse( wp_script_is( 'wp-buyer-blacklist-customer-dialogs', 'enqueued' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->enqueue_admin_scripts( 'users.php' );
		$this->assertFalse( wp_script_is( 'wp-buyer-blacklist-customer-dialogs', 'enqueued' ) );
	}

	/**
	 * Submits the enrollment page for Orders with the email Match type.
	 *
	 * @param int[] $ids Order identifiers.
	 * @return string Redirect location.
	 */
	private function enroll_orders( array $ids ): string {
		return $this->submit_admin_post(
			'wp_buyer_blacklist_enroll_source',
			array(
				'_wpnonce'    => wp_create_nonce( 'wp_buyer_blacklist_enroll_source' ),
				'source_type' => 'order',
				'source_ids'  => array_map( 'strval', $ids ),
				'match_type'  => 'email',
				'reason'      => 'Chargeback',
			)
		);
	}

	/**
	 * Renders admin notices for a screen after a Users-list enrollment.
	 *
	 * @param string $screen Current admin file, such as users.php.
	 * @param string $result Enrollment result in the query string.
	 * @return string Notice markup.
	 */
	private function admin_notices( string $screen, string $result ): string {
		global $pagenow;
		$previous = $pagenow;
		$pagenow  = $screen; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The notice only renders on the Users screen.
		$_GET     = array( 'wp_buyer_blacklist_enrollment' => $result );
		set_current_screen( basename( $screen, '.php' ) );

		ob_start();
		try {
			do_action( 'admin_notices' );
		} finally {
			$markup  = (string) ob_get_clean();
			$pagenow = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the value saved above.
			$_GET    = array();
		}

		return $markup;
	}

	/**
	 * Fires admin_enqueue_scripts the way wp-admin does, from an empty script queue.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return void
	 */
	private function enqueue_admin_scripts( string $hook_suffix ): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Each call starts from an empty script queue.
		$GLOBALS['wp_scripts'] = null;
		set_current_screen( $hook_suffix );
		do_action( 'admin_enqueue_scripts', $hook_suffix );
	}
}

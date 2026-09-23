<?php
/**
 * Tests Checkout Block and Store API enforcement.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\EmailEntry;
use WP_Buyer_Blacklist\EmailEntryRepository;

/**
 * Covers the WooCommerce pre-payment checkout hook.
 */
class CheckoutBlockStoreApiIntegrationTest extends WP_UnitTestCase {

	use AdminPostRequests;

	/**
	 * Email entry storage.
	 *
	 * @var EmailEntryRepository
	 */
	private EmailEntryRepository $entries;

	/**
	 * Entry identifier.
	 *
	 * @var int
	 */
	private int $entry_id;

	/**
	 * Registers an active email entry.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		WC_Install::create_tables();
		// The Store API checkout route keeps its draft order between requests, so rebuild the routes for each test.
		$GLOBALS['wp_rest_server'] = null;
		wp_set_current_user( self::factory()->user->create( array( 'display_name' => 'Blacklist Operator' ) ) );
		$this->entries = new EmailEntryRepository( $GLOBALS['wpdb'] );
		$this->entries->install();
		$this->assertTrue( $this->entries->create( EmailEntry::from_email( 'buyer@example.com' ), 'Test entry' ) );
		$entries        = $this->entries->all();
		$this->entry_id = (int) $entries[0]['id'];
	}

	/**
	 * Fires admin_init so the plugin registers its settings.
	 *
	 * @return void
	 */
	private function run_admin_init(): void {
		// Core sends a Referrer-Policy header here, which fails once PHPUnit has printed output.
		remove_action( 'admin_init', 'wp_admin_headers' );
		// WooCommerce adds its privacy policy text on admin_init, which WordPress rejects outside wp-admin.
		$this->setExpectedIncorrectUsage( 'wp_add_privacy_policy_content' );
		do_action( 'admin_init' );
	}

	/**
	 * Stops Checkout Block and Store API checkout before payment.
	 *
	 * @dataProvider buyer_block_messages
	 *
	 * @param string $message          Buyer block message to save.
	 * @param string $expected_message Expected Buyer block message.
	 * @return void
	 */
	public function test_blocks_matching_email_with_only_the_configured_buyer_message( string $message, string $expected_message ): void {
		$this->run_admin_init();
		update_option( 'wp_buyer_blacklist_buyer_message', sanitize_option( 'wp_buyer_blacklist_buyer_message', $message ) );
		$product = new WC_Product_Simple();
		$product->set_regular_price( '0' );
		$product->set_virtual( true );
		$product->save();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id() );
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_param(
			'billing_address',
			array(
				'first_name' => 'Blocked',
				'last_name'  => 'Buyer',
				'address_1'  => '1 Test Street',
				'city'       => 'Testville',
				'state'      => 'CA',
				'postcode'   => '90210',
				'country'    => 'US',
				'email'      => 'buyer@example.com',
				'phone'      => '5555555555',
			)
		);
		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		$data = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( $expected_message, $data['message'] );
		$this->assertStringNotContainsString( 'buyer@example.com', $data['message'] );
		$this->assertNotEmpty(
			wc_get_orders(
				array(
					'status' => 'checkout-draft',
					'limit'  => 1,
				)
			)
		);
	}

	/**
	 * Provides configured and default Buyer block messages.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function buyer_block_messages(): array {
		$default = 'We’re unable to process your order. Please contact us for assistance.';

		return array(
			'custom message'     => array( 'Configured buyer message', 'Configured buyer message' ),
			'blank message'      => array( '', $default ),
			'whitespace message' => array( " \n\t ", $default ),
		);
	}

	/**
	 * Restores the default Buyer message when a blank value is saved.
	 *
	 * @return void
	 */
	public function test_restores_the_default_buyer_message_when_a_blank_value_is_saved(): void {
		$this->run_admin_init();

		foreach ( array( '', " \n\t " ) as $message ) {
			update_option( 'wp_buyer_blacklist_buyer_message', sanitize_option( 'wp_buyer_blacklist_buyer_message', $message ) );
			$this->assertSame( 'We’re unable to process your order. Please contact us for assistance.', get_option( 'wp_buyer_blacklist_buyer_message' ) );
		}

		update_option( 'wp_buyer_blacklist_buyer_message', sanitize_option( 'wp_buyer_blacklist_buyer_message', 'Configured buyer message' ) );
		$this->assertSame( 'Configured buyer message', get_option( 'wp_buyer_blacklist_buyer_message' ) );
	}

	/**
	 * Records a Blocked attempt for a Store API block.
	 *
	 * @return void
	 */
	public function test_records_each_store_api_block_without_prohibited_data(): void {
		$product = new WC_Product_Simple();
		$product->set_regular_price( '0' );
		$product->set_virtual( true );
		$product->save();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id() );
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_param(
			'billing_address',
			array(
				'first_name' => 'Blocked',
				'last_name'  => 'Buyer',
				'address_1'  => '1 Test Street',
				'city'       => 'Testville',
				'state'      => 'CA',
				'postcode'   => '90210',
				'country'    => 'US',
				'email'      => 'buyer@example.com',
				'phone'      => '5555555555',
			)
		);
		rest_get_server()->dispatch( $request );
		remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		$attempts = $this->entries->blocked_attempts( $this->entry_id );

		$this->assertCount( 1, $attempts );
		$this->assertSame( 'buyer@example.com', $attempts[0]['identity_value'] );
		$this->assertSame( 'blocked', $attempts[0]['outcome'] );
		$this->assertSame( array( 'id', 'identity_value', 'outcome', 'created_at' ), array_keys( $attempts[0] ) );
	}

	/**
	 * Records the operator and action for ordinary entry changes.
	 *
	 * @return void
	 */
	public function test_records_audit_events_for_entry_changes(): void {
		$this->assertTrue( $this->entries->update_reason( $this->entry_id, 'Updated reason' ) );
		$this->assertTrue( $this->entries->set_active( $this->entry_id, false ) );

		$events = $this->entries->audit_events( $this->entry_id );

		$this->assertSame( array( 'deactivated', 'reason_changed', 'created' ), wp_list_pluck( $events, 'action' ) );
		$this->assertSame( 'Blacklist Operator', $events[0]['actor_name'] );
		$this->assertNotEmpty( $events[0]['actor_id'] );
	}

	/**
	 * Deletes personal Audit data and retains a minimal deletion event.
	 *
	 * @return void
	 */
	public function test_deletion_retains_only_a_minimal_audit_event(): void {
		$this->assertTrue( $this->entries->delete( $this->entry_id ) );
		$this->assertSame( array(), $this->entries->all() );

		$audit_table = $GLOBALS['wpdb']->prefix . 'buyer_blacklist_audit_events';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$event = $GLOBALS['wpdb']->get_row( "SELECT action, entry_id, actor_id, actor_name FROM {$audit_table} WHERE action = 'deleted'", ARRAY_A );

		$this->assertSame( 'deleted', $event['action'] );
		$this->assertNull( $event['entry_id'] );
		$this->assertNull( $event['actor_id'] );
		$this->assertNull( $event['actor_name'] );
	}

	/**
	 * Restores every supported setting to its registered default.
	 *
	 * @return void
	 */
	public function test_resets_settings_to_defaults(): void {
		// false would be skipped as unchanged while the option doesn't exist yet.
		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', '0' );
		update_option( 'wp_buyer_blacklist_delete_data_on_uninstall', true );
		update_option( 'wp_buyer_blacklist_buyer_message', 'Configured buyer message' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$location = $this->submit_admin_post( 'wp_buyer_blacklist_reset_settings', array( '_wpnonce' => wp_create_nonce( 'wp_buyer_blacklist_reset_settings' ) ) );

		$this->assertStringContainsString( 'wp_buyer_blacklist_settings=reset', $location );

		$this->assertTrue( get_option( 'wp_buyer_blacklist_allow_woocommerce_managers', true ) );
		$this->assertFalse( get_option( 'wp_buyer_blacklist_delete_data_on_uninstall', false ) );
		$this->assertSame( 'We’re unable to process your order. Please contact us for assistance.', get_option( 'wp_buyer_blacklist_buyer_message', 'We’re unable to process your order. Please contact us for assistance.' ) );
	}
}

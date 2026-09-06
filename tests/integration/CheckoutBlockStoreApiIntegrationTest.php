<?php
/**
 * Tests Checkout Block and Store API enforcement.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\EmailEntry;
use WP_Buyer_Blacklist\EmailEntryRepository;
use WP_Buyer_Blacklist\Plugin;

/**
 * Covers the WooCommerce pre-payment checkout hook.
 */
class CheckoutBlockStoreApiIntegrationTest extends WP_UnitTestCase {

	/**
	 * Email entry storage.
	 *
	 * @var EmailEntryRepository
	 */
	private EmailEntryRepository $entries;

	/**
	 * Checkout validator.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Registers an active email entry and the checkout validator.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->entries = new EmailEntryRepository( $GLOBALS['wpdb'] );
		$this->entries->install();
		$this->assertTrue( $this->entries->create( EmailEntry::from_email( 'buyer@example.com' ), 'Test entry' ) );

		$this->plugin = new Plugin( $this->entries );
		$this->plugin->register();
	}

	/**
	 * Removes the checkout validator.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_action( 'woocommerce_checkout_validate_order_before_payment', array( $this->plugin, 'validate_store_api_checkout' ) );

		parent::tear_down();
	}

	/**
	 * Stops Checkout Block and Store API checkout before payment.
	 *
	 * @return void
	 */
	public function test_blocks_matching_email_with_only_the_configured_buyer_message(): void {
		update_option( 'wp_buyer_blacklist_buyer_message', 'Configured buyer message' );
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
		$this->assertSame( 'Configured buyer message', $data['message'] );
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
}

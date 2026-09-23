<?php
/**
 * Tests checkout enforcement through the WooCommerce validation hooks.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\AddressEntry;
use WP_Buyer_Blacklist\CustomerIdEntry;
use WP_Buyer_Blacklist\EmailEntry;
use WP_Buyer_Blacklist\EmailEntryRepository;

/**
 * Covers classic checkout and Store API validation for every Match type.
 */
class CheckoutEnforcementIntegrationTest extends WP_UnitTestCase {

	/**
	 * Default Buyer block message.
	 */
	private const DEFAULT_MESSAGE = 'We’re unable to process your order. Please contact us for assistance.';

	/**
	 * Entry storage used to seed entries.
	 *
	 * @var EmailEntryRepository
	 */
	private EmailEntryRepository $entries;

	/**
	 * Registered Buyer placing the order.
	 *
	 * @var int
	 */
	private int $buyer_id;

	/**
	 * Signs in a registered Buyer.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->entries  = new EmailEntryRepository( $GLOBALS['wpdb'] );
		$this->buyer_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $this->buyer_id );
	}

	/**
	 * Blocks classic checkout for email and address entries.
	 *
	 * @dataProvider blocking_match_types
	 *
	 * @param string $match_type Match type of the seeded entry.
	 * @return void
	 */
	public function test_classic_checkout_blocks_a_matching_entry( string $match_type ): void {
		$this->seed_entry( $match_type );

		$errors = $this->validate_classic_checkout();

		$this->assertSame( array( self::DEFAULT_MESSAGE ), $errors->get_error_messages( 'wp_buyer_blacklist_blocked' ) );
	}

	/**
	 * Blocks Store API checkout for email and address entries.
	 *
	 * @dataProvider blocking_match_types
	 *
	 * @param string $match_type Match type of the seeded entry.
	 * @return void
	 */
	public function test_store_api_checkout_blocks_a_matching_entry( string $match_type ): void {
		$this->seed_entry( $match_type );

		$errors = $this->validate_store_api_checkout();

		$this->assertSame( array( self::DEFAULT_MESSAGE ), $errors->get_error_messages( 'wp_buyer_blacklist_blocked' ) );
	}

	/**
	 * Provides the Match types checkout enforces today.
	 *
	 * @return array<string, array{string}>
	 */
	public function blocking_match_types(): array {
		return array(
			'email'            => array( 'email' ),
			'billing address'  => array( 'billing_address' ),
			'shipping address' => array( 'shipping_address' ),
		);
	}

	/**
	 * Lets classic checkout through for a customer-ID entry.
	 *
	 * This pins current behaviour. #86 tracks that the PRD wants customer-ID entries enforced.
	 *
	 * @return void
	 */
	public function test_classic_checkout_ignores_a_customer_id_entry(): void {
		$this->seed_entry( 'customer_id' );

		$this->assertFalse( $this->validate_classic_checkout()->has_errors() );
	}

	/**
	 * Lets Store API checkout through for a customer-ID entry.
	 *
	 * This pins current behaviour. #86 tracks that the PRD wants customer-ID entries enforced.
	 *
	 * @return void
	 */
	public function test_store_api_checkout_ignores_a_customer_id_entry(): void {
		$this->seed_entry( 'customer_id' );

		$this->assertFalse( $this->validate_store_api_checkout()->has_errors() );
	}

	/**
	 * Lets checkout through when no entry matches.
	 *
	 * @return void
	 */
	public function test_checkout_passes_without_a_matching_entry(): void {
		$this->assertTrue( $this->entries->create( EmailEntry::from_email( 'someone-else@example.com' ), 'Fraud' ) );

		$this->assertFalse( $this->validate_classic_checkout()->has_errors() );
		$this->assertFalse( $this->validate_store_api_checkout()->has_errors() );
	}

	/**
	 * Lets checkout through when the matching entry is inactive.
	 *
	 * @return void
	 */
	public function test_checkout_passes_for_an_inactive_entry(): void {
		$this->seed_entry( 'email' );
		$this->assertTrue( $this->entries->set_active( (int) $this->entries->all()[0]['id'], false ) );

		$this->assertFalse( $this->validate_classic_checkout()->has_errors() );
		$this->assertFalse( $this->validate_store_api_checkout()->has_errors() );
	}

	/**
	 * Shows the configured Buyer block message on both checkout paths.
	 *
	 * @return void
	 */
	public function test_checkout_uses_the_configured_buyer_message(): void {
		update_option( 'wp_buyer_blacklist_buyer_message', 'Configured buyer message' );
		$this->seed_entry( 'email' );

		$this->assertSame( array( 'Configured buyer message' ), $this->validate_classic_checkout()->get_error_messages( 'wp_buyer_blacklist_blocked' ) );
		$this->assertSame( array( 'Configured buyer message' ), $this->validate_store_api_checkout()->get_error_messages( 'wp_buyer_blacklist_blocked' ) );
	}

	/**
	 * Falls back to the default message when the stored message is empty.
	 *
	 * @return void
	 */
	public function test_checkout_falls_back_to_the_default_buyer_message(): void {
		update_option( 'wp_buyer_blacklist_buyer_message', '' );
		$this->seed_entry( 'email' );

		$this->assertSame( array( self::DEFAULT_MESSAGE ), $this->validate_classic_checkout()->get_error_messages( 'wp_buyer_blacklist_blocked' ) );
		$this->assertSame( array( self::DEFAULT_MESSAGE ), $this->validate_store_api_checkout()->get_error_messages( 'wp_buyer_blacklist_blocked' ) );
	}

	/**
	 * Adds one entry of the given Match type for the checkout Buyer.
	 *
	 * @param string $match_type Match type.
	 * @return void
	 */
	private function seed_entry( string $match_type ): void {
		if ( 'email' === $match_type ) {
			$created = $this->entries->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' );
		} elseif ( 'customer_id' === $match_type ) {
			$created = $this->entries->create_customer_id( CustomerIdEntry::from_customer_id( $this->buyer_id ), 'Fraud' );
		} else {
			$created = $this->entries->create_address( AddressEntry::from_fields( $match_type, $this->address( $match_type ), WC()->countries->get_countries() ), 'Fraud' );
		}

		$this->assertTrue( $created );
	}

	/**
	 * Gets the checkout address for one Match type.
	 *
	 * @param string $match_type billing_address or shipping_address.
	 * @return array<string, string> Address fields.
	 */
	private function address( string $match_type ): array {
		return array(
			'address_line_1' => 'billing_address' === $match_type ? '1 Billing Street' : '2 Shipping Street',
			'address_line_2' => '',
			'city'           => 'billing_address' === $match_type ? 'Billville' : 'Shipville',
			'state'          => 'CA',
			'postcode'       => '90210',
			'country'        => 'US',
		);
	}

	/**
	 * Runs classic checkout validation for the Buyer.
	 *
	 * @return WP_Error Validation errors.
	 */
	private function validate_classic_checkout(): WP_Error {
		$data = array( 'billing_email' => 'buyer@example.com' );
		foreach ( array(
			'billing_'  => $this->address( 'billing_address' ),
			'shipping_' => $this->address( 'shipping_address' ),
		) as $prefix => $address ) {
			$data[ $prefix . 'address_1' ] = $address['address_line_1'];
			$data[ $prefix . 'address_2' ] = $address['address_line_2'];
			$data[ $prefix . 'city' ]      = $address['city'];
			$data[ $prefix . 'state' ]     = $address['state'];
			$data[ $prefix . 'postcode' ]  = $address['postcode'];
			$data[ $prefix . 'country' ]   = $address['country'];
		}
		$errors = new WP_Error();

		do_action( 'woocommerce_after_checkout_validation', $data, $errors );

		return $errors;
	}

	/**
	 * Runs Store API pre-payment validation for the Buyer.
	 *
	 * @return WP_Error Validation errors.
	 */
	private function validate_store_api_checkout(): WP_Error {
		$order    = new WC_Order();
		$billing  = $this->address( 'billing_address' );
		$shipping = $this->address( 'shipping_address' );
		$order->set_customer_id( $this->buyer_id );
		$order->set_billing_email( 'buyer@example.com' );
		$order->set_billing_address_1( $billing['address_line_1'] );
		$order->set_billing_city( $billing['city'] );
		$order->set_billing_state( $billing['state'] );
		$order->set_billing_postcode( $billing['postcode'] );
		$order->set_billing_country( $billing['country'] );
		$order->set_shipping_address_1( $shipping['address_line_1'] );
		$order->set_shipping_city( $shipping['city'] );
		$order->set_shipping_state( $shipping['state'] );
		$order->set_shipping_postcode( $shipping['postcode'] );
		$order->set_shipping_country( $shipping['country'] );
		$errors = new WP_Error();

		do_action( 'woocommerce_checkout_validate_order_before_payment', $order, $errors );

		return $errors;
	}
}

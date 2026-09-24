<?php
/**
 * Tests current Buyer context lookups.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Entries\AddressEntry;
use WP_Buyer_Blacklist\Entries\CustomerIdEntry;
use WP_Buyer_Blacklist\Entries\EmailEntry;
use WP_Buyer_Blacklist\EmailEntryRepository;

/**
 * Covers the entry-management Buyer context boundary.
 */
class BuyerContextIntegrationTest extends WP_UnitTestCase {

	/**
	 * Signs in a Blacklist operator.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Resolves a Customer-ID entry to the current registered Buyer.
	 *
	 * @return void
	 */
	public function test_resolves_a_customer_id_entry(): void {
		global $wpdb;
		$buyer_id = self::factory()->user->create(
			array(
				'role'       => 'customer',
				'user_email' => 'buyer@example.com',
				'first_name' => 'Ada',
				'last_name'  => 'Lovelace',
			)
		);
		update_user_meta( $buyer_id, 'billing_address_1', '1 Test Street' );
		update_user_meta( $buyer_id, 'billing_city', 'Testville' );
		update_user_meta( $buyer_id, 'billing_state', 'CA' );
		update_user_meta( $buyer_id, 'billing_postcode', '90210' );
		update_user_meta( $buyer_id, 'billing_country', 'US' );
		update_user_meta( $buyer_id, 'shipping_address_1', '2 Test Street' );
		update_user_meta( $buyer_id, 'shipping_city', 'Shipville' );
		update_user_meta( $buyer_id, 'shipping_state', 'NY' );
		update_user_meta( $buyer_id, 'shipping_postcode', '10001' );
		update_user_meta( $buyer_id, 'shipping_country', 'US' );
		$entries = new EmailEntryRepository( $wpdb );
		$this->assertTrue( $entries->create_customer_id( CustomerIdEntry::from_customer_id( $buyer_id ), 'Fraud' ) );

		$result = $this->buyer_context( (int) $entries->all()[0]['id'] );

		$this->assertSame( $buyer_id, $result['buyer']['id'] );
		$this->assertSame( 'Ada Lovelace', $result['buyer']['name'] );
		$this->assertSame( 'buyer@example.com', $result['buyer']['email'] );
		$this->assertSame( '1 Test Street', $result['buyer']['billing_address']['address_line_1'] );
		$this->assertSame( '2 Test Street', $result['buyer']['shipping_address']['address_line_1'] );
	}

	/**
	 * Resolves an email entry only when it identifies a registered Buyer.
	 *
	 * @return void
	 */
	public function test_resolves_a_unique_email_and_rejects_a_guest_email(): void {
		global $wpdb;
		self::factory()->user->create(
			array(
				'role'       => 'customer',
				'user_email' => 'buyer@example.com',
			)
		);
		$entries = new EmailEntryRepository( $wpdb );
		$this->assertTrue( $entries->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' ) );
		$this->assertTrue( $entries->create( EmailEntry::from_email( 'guest@example.com' ), 'Fraud' ) );

		$this->assertSame( 'buyer@example.com', $this->buyer_context( (int) $entries->all()[1]['id'] )['buyer']['email'] );
		$this->assertNull( $this->buyer_context( (int) $entries->all()[0]['id'] )['buyer'] );
	}

	/**
	 * Never resolves an address entry to an arbitrary Buyer.
	 *
	 * @return void
	 */
	public function test_address_entry_has_no_uniquely_identified_buyer(): void {
		global $wpdb;
		$entries = new EmailEntryRepository( $wpdb );
		$address = AddressEntry::from_fields(
			'billing_address',
			array(
				'address_line_1' => '1 Test Street',
				'address_line_2' => '',
				'city'           => 'Testville',
				'state'          => 'CA',
				'postcode'       => '90210',
				'country'        => 'US',
			),
			WC()->countries->get_countries()
		);
		$this->assertTrue( $entries->create_address( $address, 'Fraud' ) );

		$result = $this->buyer_context( (int) $entries->all()[0]['id'] );

		$this->assertNull( $result['buyer'] );
		$this->assertSame( 'No uniquely identified registered Buyer.', $result['message'] );
	}

	/**
	 * Requests context through the modal endpoint.
	 *
	 * @param int $entry_id Entry identifier.
	 * @return array<string, mixed> Buyer context.
	 */
	private function buyer_context( int $entry_id ): array {
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp-buyer-blacklist/v1/email-entries/' . $entry_id . '/buyer-context' ) );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}
}

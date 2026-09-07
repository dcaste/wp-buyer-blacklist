<?php
/**
 * Tests address Blacklist enrollment.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\AddressEntry;
use WP_Buyer_Blacklist\EmailEntryRepository;

/**
 * Covers the address-entry REST endpoint.
 */
class AddressEnrollmentIntegrationTest extends WP_UnitTestCase {

	/**
	 * Address entry storage.
	 *
	 * @var EmailEntryRepository
	 */
	private EmailEntryRepository $entries;

	/**
	 * Sets up address entry storage.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->entries = new EmailEntryRepository( $GLOBALS['wpdb'] );
		$this->entries->install();
		do_action( 'rest_api_init' );
	}

	/**
	 * Creates a complete address entry through the operator endpoint.
	 *
	 * @return void
	 */
	public function test_creates_a_billing_address_entry(): void {
		$request = new WP_REST_Request( 'POST', '/wp-buyer-blacklist/v1/address-entries' );
		foreach (
			array(
				'match_type'     => 'billing_address',
				'address_line_1' => '1 Test Street',
				'address_line_2' => 'Apt 2',
				'city'           => 'Testville',
				'state'          => 'CA',
				'postcode'       => '90210',
				'country'        => 'US',
				'reason'         => 'Test entry',
			) as $field => $value
		) {
			$request->set_param( $field, $value );
		}

		$response = rest_get_server()->dispatch( $request );
		$entry    = AddressEntry::from_fields(
			'billing_address',
			array(
				'address_line_1' => '1 Test Street',
				'address_line_2' => 'Apt 2',
				'city'           => 'Testville',
				'state'          => 'CA',
				'postcode'       => '90210',
				'country'        => 'US',
			),
			WC()->countries->get_countries()
		);

		$this->assertSame( 201, $response->get_status() );
		$this->assertNotEmpty( $this->entries->matching_active_address_entry_ids( $entry ) );
	}
}

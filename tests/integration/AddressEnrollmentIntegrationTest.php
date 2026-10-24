<?php
/**
 * Tests address Blacklist enrollment.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Enforcement\BlockedAttemptLog;
use WP_Buyer_Blacklist\Entries\AddressEntry;
use WP_Buyer_Blacklist\Entries\AuditLog;
use WP_Buyer_Blacklist\Entries\EntryRepository;
use WP_Buyer_Blacklist\Storage\Schema;

/**
 * Covers the address-entry REST endpoint.
 */
class AddressEnrollmentIntegrationTest extends WP_UnitTestCase {

	/**
	 * Address entry storage.
	 *
	 * @var EntryRepository
	 */
	private EntryRepository $entries;

	/**
	 * Sets up address entry storage.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->entries = new EntryRepository( $GLOBALS['wpdb'], new AuditLog( $GLOBALS['wpdb'] ), new BlockedAttemptLog( $GLOBALS['wpdb'] ) );
		( new Schema( $GLOBALS['wpdb'] ) )->install();
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

		$request->set_param( 'address_line_1', '  1   TEST Street ' );
		$request->set_param( 'address_line_2', ' APT  2 ' );
		$request->set_param( 'city', ' TESTVILLE ' );
		$request->set_param( 'state', ' ca ' );
		$request->set_param( 'country', 'us' );
		$show_database_errors = $GLOBALS['wpdb']->suppress_errors();
		$duplicate_response   = rest_get_server()->dispatch( $request );
		$GLOBALS['wpdb']->suppress_errors( $show_database_errors );
		$this->assertSame( 409, $duplicate_response->get_status() );
	}

	/**
	 * Rejects a country code WooCommerce does not recognize.
	 *
	 * @return void
	 */
	public function test_rejects_an_unrecognized_country_code(): void {
		$request = new WP_REST_Request( 'POST', '/wp-buyer-blacklist/v1/address-entries' );
		foreach (
			array(
				'match_type'     => 'shipping_address',
				'address_line_1' => '1 Test Street',
				'city'           => 'Testville',
				'state'          => 'CA',
				'postcode'       => '90210',
				'country'        => 'ZZ',
				'reason'         => 'Test entry',
			) as $field => $value
		) {
			$request->set_param( $field, $value );
		}

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}
}

<?php
/**
 * Tests CSV import REST actions.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Entries\AddressEntry;
use WP_Buyer_Blacklist\Entries\CustomerIdEntry;
use WP_Buyer_Blacklist\Entries\EmailEntry;
use WP_Buyer_Blacklist\EmailEntryRepository;

/**
 * Covers confirmation of validated imports.
 */
class CsvImportIntegrationTest extends WP_UnitTestCase {

	/**
	 * Imports validated rows as active entries and records imported audit events.
	 *
	 * @return void
	 */
	public function test_imports_a_validated_csv(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		global $wpdb;
		$repository = new EmailEntryRepository( $wpdb );
		$validation = new WP_REST_Request( 'POST', '/wp-buyer-blacklist/v1/csv-import/validate' );
		$validation->set_param( 'csv', "match_type,email,reason\nemail,imported@example.com,CSV test\n" );
		$validation = rest_do_request( $validation );
		$this->assertSame( 200, $validation->get_status() );

		$this->assertSame( 1, $validation->get_data()['valid'] );
		$confirmation = new WP_REST_Request( 'POST', '/wp-buyer-blacklist/v1/csv-import/confirm' );
		$confirmation->set_param( 'token', $validation->get_data()['token'] );
		$result = rest_do_request( $confirmation );
		$this->assertSame( 200, $result->get_status() );

		$this->assertSame( 1, $result->get_data()['created'] );
		$this->assertSame( 'imported@example.com', $repository->all()[0]['value'] );
		$this->assertSame( 'imported', $repository->audit_events( (int) $repository->all()[0]['id'] )[0]['action'] );
	}

	/**
	 * Round-trips every Match type and preserves active and inactive entries.
	 *
	 * @return void
	 */
	public function test_round_trips_all_entry_types_and_statuses(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		global $wpdb;
		$repository = new EmailEntryRepository( $wpdb );
		$address    = array(
			'address_line_1' => '1 Test Street',
			'address_line_2' => 'Apt 2',
			'city'           => 'Testville',
			'state'          => 'CA',
			'postcode'       => '90210',
			'country'        => 'US',
		);

		$this->assertTrue( $repository->create( EmailEntry::from_email( 'buyer@example.com' ), 'Email reason' ) );
		$this->assertTrue( $repository->create_customer_id( CustomerIdEntry::from_customer_id( 42 ), 'Customer ID reason' ) );
		$this->assertTrue( $repository->create_address( AddressEntry::from_fields( 'billing_address', $address, WC()->countries->get_countries() ), 'Billing reason' ) );
		$this->assertTrue( $repository->create_address( AddressEntry::from_fields( 'shipping_address', $address, WC()->countries->get_countries() ), 'Shipping reason' ) );

		$entries = $repository->all();
		foreach ( $entries as $entry ) {
			if ( in_array( $entry['match_type'], array( 'customer_id', 'shipping_address' ), true ) ) {
				$this->assertTrue( $repository->set_active( (int) $entry['id'], false ) );
			}
		}
		$csv = $repository->export_csv();
		$this->assertSame( array( 'match_type', 'email', 'customer_id', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country', 'reason', 'status' ), str_getcsv( explode( "\n", $csv )[0] ) );

		foreach ( $entries as $entry ) {
			$this->assertTrue( $repository->delete( (int) $entry['id'] ) );
		}
		$validation = new WP_REST_Request( 'POST', '/wp-buyer-blacklist/v1/csv-import/validate' );
		$validation->set_param( 'csv', $csv );
		$validation = rest_do_request( $validation );
		$this->assertSame( 200, $validation->get_status() );
		$this->assertSame( 4, $validation->get_data()['valid'] );
		$confirmation = new WP_REST_Request( 'POST', '/wp-buyer-blacklist/v1/csv-import/confirm' );
		$confirmation->set_param( 'token', $validation->get_data()['token'] );
		$result = rest_do_request( $confirmation );
		$this->assertSame( 200, $result->get_status() );

		$this->assertSame( 4, $result->get_data()['created'] );
		$statuses = array_column( $repository->all(), 'is_active', 'match_type' );
		ksort( $statuses );
		$this->assertSame(
			array(
				'billing_address'  => '1',
				'customer_id'      => '0',
				'email'            => '1',
				'shipping_address' => '0',
			),
			$statuses
		);
	}
}

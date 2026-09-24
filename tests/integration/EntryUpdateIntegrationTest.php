<?php
/**
 * Tests immutable Blacklist entry identities.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Entries\EmailEntry;
use WP_Buyer_Blacklist\EmailEntryRepository;

/**
 * Covers the entry update REST boundary.
 */
class EntryUpdateIntegrationTest extends WP_UnitTestCase {

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
	 * Reports real entry and Blocked attempt totals for the admin summary.
	 *
	 * @return void
	 */
	public function test_summary_counts_entries_by_status_and_blocked_attempts(): void {
		global $wpdb;
		$entries = new EmailEntryRepository( $wpdb );
		$this->assertTrue( $entries->create( EmailEntry::from_email( 'active@example.com' ), 'Fraud' ) );
		$this->assertTrue( $entries->create( EmailEntry::from_email( 'inactive@example.com' ), 'Fraud' ) );
		$all = $entries->all();
		$this->assertTrue( $entries->set_active( (int) $all[0]['id'], false ) );
		$this->assertTrue( $entries->record_blocked_attempt( (int) $all[1]['id'], 'active@example.com' ) );

		$this->assertSame(
			array(
				'total_entries'    => 2,
				'active_entries'   => 1,
				'inactive_entries' => 1,
				'blocked_attempts' => 1,
			),
			$entries->summary()
		);
	}

	/**
	 * Rejects an attempt to change an entry identity.
	 *
	 * @return void
	 */
	public function test_rejects_identity_fields_when_updating_an_entry(): void {
		global $wpdb;
		$entries = new EmailEntryRepository( $wpdb );
		$this->assertTrue( $entries->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' ) );
		$entry_id = (int) $entries->all()[0]['id'];
		$request  = new WP_REST_Request( 'PATCH', '/wp-buyer-blacklist/v1/email-entries/' . $entry_id );
		foreach ( array(
			'email'      => 'replacement@example.com',
			'match_type' => 'customer_id',
		) as $field => $value ) {
			$request->set_param( $field, $value );
			$response = rest_do_request( $request );

			$this->assertSame( 400, $response->get_status() );
			$this->assertSame( 'wp_buyer_blacklist_identity_immutable', $response->get_data()['code'] );
			$request->set_param( $field, null );
		}
		$this->assertSame( 'buyer@example.com', $entries->all()[0]['value'] );
	}

	/**
	 * Allows reason and status changes without changing the identity.
	 *
	 * @return void
	 */
	public function test_allows_reason_and_status_updates(): void {
		global $wpdb;
		$entries = new EmailEntryRepository( $wpdb );
		$this->assertTrue( $entries->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' ) );
		$entry_id = (int) $entries->all()[0]['id'];

		$reason_request = new WP_REST_Request( 'PATCH', '/wp-buyer-blacklist/v1/email-entries/' . $entry_id );
		$reason_request->set_param( 'reason', 'Confirmed fraud' );
		$status_request = new WP_REST_Request( 'PATCH', '/wp-buyer-blacklist/v1/email-entries/' . $entry_id );
		$status_request->set_param( 'is_active', false );

		$this->assertSame( 200, rest_do_request( $reason_request )->get_status() );
		$this->assertSame( 200, rest_do_request( $status_request )->get_status() );
		$this->assertSame( 'Confirmed fraud', $entries->all()[0]['reason'] );
		$this->assertSame( '0', $entries->all()[0]['is_active'] );
		$this->assertSame( array( 'deactivated', 'reason_changed', 'created' ), wp_list_pluck( $entries->audit_events( $entry_id ), 'action' ) );
	}
}

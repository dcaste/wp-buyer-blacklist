<?php
/**
 * Tests immutable Blacklist entry identities.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\EmailEntry;
use WP_Buyer_Blacklist\EmailEntryRepository;
use WP_Buyer_Blacklist\Plugin;

/**
 * Covers the entry update REST boundary.
 */
class EntryUpdateIntegrationTest extends WP_UnitTestCase {

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
		$request->set_param( 'id', $entry_id );
		foreach ( array(
			'email'      => 'replacement@example.com',
			'match_type' => 'customer_id',
		) as $field => $value ) {
			$request->set_param( $field, $value );
			$result = ( new Plugin( $entries ) )->update_email_entry( $request );

			$this->assertWPError( $result );
			$this->assertSame( 400, $result->get_error_data()['status'] );
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
		$plugin   = new Plugin( $entries );

		$reason_request = new WP_REST_Request( 'PATCH', '/wp-buyer-blacklist/v1/email-entries/' . $entry_id );
		$reason_request->set_param( 'id', $entry_id );
		$reason_request->set_param( 'reason', 'Confirmed fraud' );
		$status_request = new WP_REST_Request( 'PATCH', '/wp-buyer-blacklist/v1/email-entries/' . $entry_id );
		$status_request->set_param( 'id', $entry_id );
		$status_request->set_param( 'is_active', false );

		$this->assertInstanceOf( WP_REST_Response::class, $plugin->update_email_entry( $reason_request ) );
		$this->assertInstanceOf( WP_REST_Response::class, $plugin->update_email_entry( $status_request ) );
		$this->assertSame( 'Confirmed fraud', $entries->all()[0]['reason'] );
		$this->assertSame( '0', $entries->all()[0]['is_active'] );
		$this->assertSame( array( 'deactivated', 'reason_changed', 'created' ), wp_list_pluck( $entries->audit_events( $entry_id ), 'action' ) );
	}
}

<?php
/**
 * Tests the entry REST routes.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Enforcement\BlockedAttemptLog;
use WP_Buyer_Blacklist\Entries\AuditLog;
use WP_Buyer_Blacklist\Entries\EmailEntry;
use WP_Buyer_Blacklist\Entries\EntryRepository;

/**
 * Covers entry listing, creation, history, deletion and import history over REST.
 */
class EntriesRestIntegrationTest extends WP_UnitTestCase {

	/**
	 * Entry storage used to seed and read entries.
	 *
	 * @var EntryRepository
	 */
	private EntryRepository $entries;

	/**
	 * Signs in an Administrator.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->entries = new EntryRepository( $GLOBALS['wpdb'], new AuditLog( $GLOBALS['wpdb'] ), new BlockedAttemptLog( $GLOBALS['wpdb'] ) );
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role'         => 'administrator',
					'display_name' => 'Blacklist Operator',
				)
			)
		);
	}

	/**
	 * Reports entry and Blocked attempt totals.
	 *
	 * @return void
	 */
	public function test_entry_summary_counts_entries_and_blocked_attempts(): void {
		$this->assertTrue( $this->entries->create( EmailEntry::from_email( 'active@example.com' ), 'Fraud' ) );
		$this->assertTrue( $this->entries->create( EmailEntry::from_email( 'inactive@example.com' ), 'Fraud' ) );
		$all = $this->entries->all();
		$this->assertTrue( $this->entries->set_active( (int) $all[0]['id'], false ) );
		$this->block_checkout_for( 'active@example.com' );

		$response = $this->request( 'GET', '/entry-summary' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'total_entries'    => 2,
				'active_entries'   => 1,
				'inactive_entries' => 1,
				'blocked_attempts' => 1,
			),
			$response->get_data()
		);
	}

	/**
	 * Lists every entry.
	 *
	 * @return void
	 */
	public function test_lists_entries(): void {
		$this->assertTrue( $this->entries->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' ) );

		$response = $this->request( 'GET', '/email-entries' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
		$this->assertSame( array( 'id', 'match_type', 'value', 'reason', 'is_active', 'created_at' ), array_keys( $response->get_data()[0] ) );
		$this->assertSame( 'email', $response->get_data()[0]['match_type'] );
		$this->assertSame( 'buyer@example.com', $response->get_data()[0]['value'] );
		$this->assertSame( 'Fraud', $response->get_data()[0]['reason'] );
		$this->assertSame( '1', $response->get_data()[0]['is_active'] );
	}

	/**
	 * Creates an email entry and rejects a duplicate or invalid email.
	 *
	 * @return void
	 */
	public function test_creates_an_email_entry(): void {
		$created = $this->request(
			'POST',
			'/email-entries',
			array(
				'email'  => 'Buyer@Example.com',
				'reason' => 'Fraud',
			)
		);

		$this->assertSame( 201, $created->get_status() );
		$this->assertSame( array( 'message' => 'Email entry created.' ), $created->get_data() );
		$this->assertSame( 'buyer@example.com', $this->entries->all()[0]['value'] );
		$this->assertSame( 'Fraud', $this->entries->all()[0]['reason'] );

		$duplicate = $this->request(
			'POST',
			'/email-entries',
			array(
				'email'  => 'buyer@example.com',
				'reason' => 'Fraud',
			)
		);
		$this->assertSame( 409, $duplicate->get_status() );
		$this->assertSame( 'wp_buyer_blacklist_duplicate', $duplicate->get_data()['code'] );

		$invalid = $this->request(
			'POST',
			'/email-entries',
			array(
				'email'  => 'not-an-email',
				'reason' => 'Fraud',
			)
		);
		$this->assertSame( 400, $invalid->get_status() );
		$this->assertSame( 'wp_buyer_blacklist_invalid_email', $invalid->get_data()['code'] );
		$this->assertCount( 1, $this->entries->all() );
	}

	/**
	 * Rejects requests that break a route's declared argument schema.
	 *
	 * @return void
	 */
	public function test_rejects_requests_that_break_the_argument_schema(): void {
		$missing_email = $this->request( 'POST', '/email-entries', array( 'reason' => 'Fraud' ) );
		$this->assertSame( 400, $missing_email->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $missing_email->get_data()['code'] );

		$bad_match_type = $this->request(
			'POST',
			'/address-entries',
			array(
				'match_type' => 'email',
				'reason'     => 'Fraud',
			)
		);
		$this->assertSame( 400, $bad_match_type->get_status() );
		$this->assertSame( 'rest_invalid_param', $bad_match_type->get_data()['code'] );

		$bad_status = $this->request( 'PATCH', '/email-entries/' . $this->seed_entry(), array( 'is_active' => 'maybe' ) );
		$this->assertSame( 400, $bad_status->get_status() );
		$this->assertSame( 'rest_invalid_param', $bad_status->get_data()['code'] );
		$this->assertCount( 1, $this->entries->all() );
	}

	/**
	 * Requires a reason when creating or updating an entry.
	 *
	 * @return void
	 */
	public function test_requires_a_reason(): void {
		$no_reason = $this->request( 'POST', '/email-entries', array( 'email' => 'buyer@example.com' ) );
		$this->assertSame( 400, $no_reason->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $no_reason->get_data()['code'] );

		$blank_email = $this->request(
			'POST',
			'/email-entries',
			array(
				'email'  => 'buyer@example.com',
				'reason' => '   ',
			)
		);
		$this->assertSame( 400, $blank_email->get_status() );
		$this->assertSame( 'wp_buyer_blacklist_reason_required', $blank_email->get_data()['code'] );

		$blank_address = $this->request(
			'POST',
			'/address-entries',
			array(
				'match_type'     => 'billing_address',
				'address_line_1' => '1 Test Street',
				'city'           => 'Testville',
				'state'          => 'CA',
				'postcode'       => '90210',
				'country'        => 'US',
				'reason'         => '',
			)
		);
		$this->assertSame( 400, $blank_address->get_status() );
		$this->assertSame( 'wp_buyer_blacklist_reason_required', $blank_address->get_data()['code'] );
		$this->assertSame( array(), $this->entries->all() );

		$entry_id     = $this->seed_entry();
		$blank_update = $this->request( 'PATCH', '/email-entries/' . $entry_id, array( 'reason' => '  ' ) );
		$this->assertSame( 400, $blank_update->get_status() );
		$this->assertSame( 'wp_buyer_blacklist_reason_required', $blank_update->get_data()['code'] );
		$this->assertSame( 'Fraud', $this->entries->all()[0]['reason'] );
	}

	/**
	 * Lets an entry that already has an empty reason change status.
	 *
	 * @return void
	 */
	public function test_entry_with_an_empty_reason_can_still_change_status(): void {
		$this->assertTrue( $this->entries->create( EmailEntry::from_email( 'legacy@example.com' ), '' ) );
		$entry_id = (int) $this->entries->all()[0]['id'];

		$response = $this->request( 'PATCH', '/email-entries/' . $entry_id, array( 'is_active' => false ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '0', $this->entries->all()[0]['is_active'] );
		$this->assertSame( '', $this->entries->all()[0]['reason'] );
	}

	/**
	 * Lists an entry's Audit events, newest first.
	 *
	 * @return void
	 */
	public function test_lists_audit_events(): void {
		$entry_id = $this->seed_entry();
		$this->assertTrue( $this->entries->update_reason( $entry_id, 'Updated reason' ) );

		$response = $this->request( 'GET', '/email-entries/' . $entry_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'reason_changed', 'created' ), wp_list_pluck( $response->get_data(), 'action' ) );
		$this->assertSame( array( 'action', 'actor_id', 'actor_name', 'created_at' ), array_keys( $response->get_data()[0] ) );
		$this->assertSame( (string) get_current_user_id(), $response->get_data()[0]['actor_id'] );
		$this->assertSame( 'Blacklist Operator', $response->get_data()[0]['actor_name'] );
	}

	/**
	 * Lists an entry's Blocked attempts.
	 *
	 * @return void
	 */
	public function test_lists_blocked_attempts(): void {
		$entry_id = $this->seed_entry();
		$this->block_checkout_for( 'buyer@example.com' );

		$response = $this->request( 'GET', '/email-entries/' . $entry_id . '/blocked-attempts' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
		$this->assertSame( array( 'id', 'identity_value', 'outcome', 'created_at' ), array_keys( $response->get_data()[0] ) );
		$this->assertSame( 'buyer@example.com', $response->get_data()[0]['identity_value'] );
		$this->assertSame( 'blocked', $response->get_data()[0]['outcome'] );
	}

	/**
	 * Deletes an entry only after confirmation.
	 *
	 * @return void
	 */
	public function test_deletes_an_entry_after_confirmation(): void {
		$entry_id = $this->seed_entry();

		$unconfirmed = $this->request( 'DELETE', '/email-entries/' . $entry_id );
		$this->assertSame( 400, $unconfirmed->get_status() );
		$this->assertSame( 'wp_buyer_blacklist_delete_not_confirmed', $unconfirmed->get_data()['code'] );
		$this->assertCount( 1, $this->entries->all() );

		$confirmed = $this->request( 'DELETE', '/email-entries/' . $entry_id, array( 'confirm' => true ) );
		$this->assertSame( 200, $confirmed->get_status() );
		$this->assertSame( array( 'message' => 'Blacklist entry deleted.' ), $confirmed->get_data() );
		$this->assertSame( array(), $this->entries->all() );

		$missing = $this->request( 'DELETE', '/email-entries/' . $entry_id, array( 'confirm' => true ) );
		$this->assertSame( 404, $missing->get_status() );
		$this->assertSame( 'wp_buyer_blacklist_delete_failed', $missing->get_data()['code'] );
	}

	/**
	 * Refuses deletion to a WooCommerce operator.
	 *
	 * @return void
	 */
	public function test_woocommerce_operator_cannot_delete_an_entry(): void {
		$entry_id = $this->seed_entry();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$response = $this->request( 'DELETE', '/email-entries/' . $entry_id, array( 'confirm' => true ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertCount( 1, $this->entries->all() );
	}

	/**
	 * Returns an identity dossier to an Administrator only.
	 *
	 * @return void
	 */
	public function test_returns_an_identity_dossier_to_an_administrator(): void {
		$entry_id = $this->seed_entry();
		$this->block_checkout_for( 'buyer@example.com' );

		$response = $this->request( 'GET', '/email-entries/' . $entry_id . '/dossier' );
		$dossier  = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'entry', 'blocked_attempts', 'audit_events' ), array_keys( $dossier ) );
		$this->assertSame( array( 'id', 'match_type', 'value', 'reason', 'is_active', 'created_at' ), array_keys( $dossier['entry'] ) );
		$this->assertSame( (string) $entry_id, $dossier['entry']['id'] );
		$this->assertSame( 'email', $dossier['entry']['match_type'] );
		$this->assertSame( 'buyer@example.com', $dossier['entry']['value'] );
		$this->assertSame( 'Fraud', $dossier['entry']['reason'] );
		$this->assertTrue( $dossier['entry']['is_active'] );
		$this->assertSame( array( 'buyer@example.com' ), wp_list_pluck( $dossier['blocked_attempts'], 'identity_value' ) );
		$this->assertSame( array( 'created' ), wp_list_pluck( $dossier['audit_events'], 'action' ) );

		$missing = $this->request( 'GET', '/email-entries/' . ( $entry_id + 1 ) . '/dossier' );
		$this->assertSame( 404, $missing->get_status() );
		$this->assertSame( 'wp_buyer_blacklist_not_found', $missing->get_data()['code'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->assertSame( 403, $this->request( 'GET', '/email-entries/' . $entry_id . '/dossier' )->get_status() );
	}

	/**
	 * Lists recent CSV imports.
	 *
	 * @return void
	 */
	public function test_lists_import_history(): void {
		$validation = $this->request(
			'POST',
			'/csv-import/validate',
			array(
				'csv'      => "match_type,email,reason\nemail,imported@example.com,CSV test\n",
				'filename' => 'buyers.csv',
			)
		);
		$this->assertSame( 200, $this->request( 'POST', '/csv-import/confirm', array( 'token' => $validation->get_data()['token'] ) )->get_status() );

		$response = $this->request( 'GET', '/import-history' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data() );
		$import = $response->get_data()[0];
		$this->assertSame( array( 'id', 'file_name', 'entries_found', 'entries_created', 'duplicates', 'rejected', 'outcome', 'created_at' ), array_keys( $import ) );
		$this->assertSame(
			array(
				'file_name'       => 'buyers.csv',
				'entries_found'   => '1',
				'entries_created' => '1',
				'duplicates'      => '0',
				'rejected'        => '0',
				'outcome'         => 'completed',
			),
			array_intersect_key( $import, array_flip( array( 'file_name', 'entries_found', 'entries_created', 'duplicates', 'rejected', 'outcome' ) ) )
		);
	}

	/**
	 * Records a Blocked attempt by failing classic checkout for an email.
	 *
	 * @param string $email Buyer email address.
	 * @return void
	 */
	private function block_checkout_for( string $email ): void {
		$errors = new WP_Error();
		do_action( 'woocommerce_after_checkout_validation', array( 'billing_email' => $email ), $errors );
		$this->assertTrue( $errors->has_errors() );
	}

	/**
	 * Adds one email entry.
	 *
	 * @return int Entry identifier.
	 */
	private function seed_entry(): int {
		$this->assertTrue( $this->entries->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' ) );

		return (int) $this->entries->all()[0]['id'];
	}

	/**
	 * Sends a request to the plugin's REST namespace as the current user.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path   Route path after the namespace.
	 * @param array<string, mixed> $params Request parameters.
	 * @return WP_REST_Response Response.
	 */
	private function request( string $method, string $path, array $params = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/wp-buyer-blacklist/v1' . $path );
		foreach ( $params as $name => $value ) {
			$request->set_param( $name, $value );
		}

		return rest_do_request( $request );
	}
}

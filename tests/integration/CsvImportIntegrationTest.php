<?php
/**
 * Tests CSV import REST actions.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\EmailEntryRepository;
use WP_Buyer_Blacklist\Plugin;

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
		$plugin     = new Plugin( $repository );
		$validation = new WP_REST_Request( 'POST', '/wp-buyer-blacklist/v1/csv-import/validate' );
		$validation->set_param( 'csv', "match_type,email,reason\nemail,imported@example.com,CSV test\n" );
		$validation = $plugin->validate_csv_import( $validation );

		$this->assertSame( 1, $validation->get_data()['valid'] );
		$confirmation = new WP_REST_Request( 'POST', '/wp-buyer-blacklist/v1/csv-import/confirm' );
		$confirmation->set_param( 'token', $validation->get_data()['token'] );
		$result = $plugin->confirm_csv_import( $confirmation );

		$this->assertSame( 1, $result->get_data()['created'] );
		$this->assertSame( 'imported@example.com', $repository->all()[0]['value'] );
		$this->assertSame( 'imported', $repository->audit_events( (int) $repository->all()[0]['id'] )[0]['action'] );
	}
}

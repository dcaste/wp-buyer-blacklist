<?php
/**
 * Tests Blacklist privacy data operations.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Enforcement\BlockedAttemptLog;
use WP_Buyer_Blacklist\Entries\AuditLog;
use WP_Buyer_Blacklist\Entries\EmailEntry;
use WP_Buyer_Blacklist\Entries\EntryRepository;
use WP_Buyer_Blacklist\ImportExport\CsvExporter;
use WP_Buyer_Blacklist\Privacy\DossierBuilder;

/**
 * Covers the repository privacy boundary.
 */
class PrivacyDataIntegrationTest extends WP_UnitTestCase {

	/**
	 * Exports entries and erases an identity with its Blocked attempts.
	 *
	 * @return void
	 */
	public function test_exports_entries_and_erases_an_identity(): void {
		global $wpdb;
		$repository = new EntryRepository( $wpdb, new AuditLog( $wpdb ), new BlockedAttemptLog( $wpdb ) );
		$this->assertTrue( $repository->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' ) );
		$entry_id = (int) $repository->all()[0]['id'];
		( new BlockedAttemptLog( $wpdb ) )->record_blocked_attempt( $entry_id, 'buyer@example.com' );

		$this->assertStringContainsString( 'email,buyer@example.com,', ( new CsvExporter( $wpdb ) )->export_csv() );
		$this->assertSame( 'buyer@example.com', ( new DossierBuilder( $wpdb, new AuditLog( $wpdb ), new BlockedAttemptLog( $wpdb ) ) )->identity_dossier( $entry_id )['entry']['value'] );
		$this->assertTrue( $repository->erase( $entry_id ) );
		$this->assertSame( array(), $repository->all() );
		$this->assertSame( array(), ( new BlockedAttemptLog( $wpdb ) )->blocked_attempts( $entry_id ) );
		$this->assertSame( 'erased', ( new DossierBuilder( $wpdb, new AuditLog( $wpdb ), new BlockedAttemptLog( $wpdb ) ) )->deletion_events()[0]['action'] );
	}
}

<?php
/**
 * Tests identity dossier downloads.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Entries\EmailEntry;
use WP_Buyer_Blacklist\EmailEntryRepository;
use WP_Buyer_Blacklist\Privacy\DossierDownload;
use WP_Buyer_Blacklist\Privacy\PrivacyDataAccess;

/**
 * Covers Administrator-only dossier downloads.
 */
class IdentityDossierDownloadIntegrationTest extends WP_UnitTestCase {

	/**
	 * Lets an Administrator retrieve the current identity dossier.
	 *
	 * @return void
	 */
	public function test_administrator_can_retrieve_an_identity_dossier_for_download(): void {
		global $wpdb;
		$repository = new EmailEntryRepository( $wpdb );
		$this->assertTrue( $repository->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' ) );
		$entry_id = (int) $repository->all()[0]['id'];
		$repository->record_blocked_attempt( $entry_id, 'buyer@example.com' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$dossier = ( new DossierDownload( $repository, new PrivacyDataAccess() ) )->identity_dossier_for_download( $entry_id, wp_create_nonce( 'wp_buyer_blacklist_dossier_export' ) );

		$this->assertIsArray( $dossier );
		$this->assertSame( 'buyer@example.com', $dossier['entry']['value'] );
		$this->assertSame( 'created', $dossier['audit_events'][0]['action'] );
		$this->assertSame( 'buyer@example.com', $dossier['blocked_attempts'][0]['identity_value'] );
	}

	/**
	 * Refuses a WooCommerce operator identity dossiers.
	 *
	 * @return void
	 */
	public function test_woocommerce_operator_cannot_retrieve_an_identity_dossier_for_download(): void {
		global $wpdb;
		$repository = new EmailEntryRepository( $wpdb );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$result = ( new DossierDownload( $repository, new PrivacyDataAccess() ) )->identity_dossier_for_download( 1, wp_create_nonce( 'wp_buyer_blacklist_dossier_export' ) );

		$this->assertWPError( $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Refuses an expired dossier download link.
	 *
	 * @return void
	 */
	public function test_administrator_cannot_retrieve_an_identity_dossier_with_an_invalid_nonce(): void {
		global $wpdb;
		$repository = new EmailEntryRepository( $wpdb );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$result = ( new DossierDownload( $repository, new PrivacyDataAccess() ) )->identity_dossier_for_download( 1, 'expired-nonce' );

		$this->assertWPError( $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}
}

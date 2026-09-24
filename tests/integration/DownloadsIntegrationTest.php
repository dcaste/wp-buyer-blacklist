<?php
/**
 * Tests the CSV export and identity dossier downloads.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Enforcement\BlockedAttemptLog;
use WP_Buyer_Blacklist\Entries\AuditLog;
use WP_Buyer_Blacklist\Entries\EmailEntry;
use WP_Buyer_Blacklist\Entries\EntryRepository;
use WP_Buyer_Blacklist\ImportExport\CsvExportDownload;
use WP_Buyer_Blacklist\ImportExport\CsvExporter;
use WP_Buyer_Blacklist\Privacy\PrivacyDataAccess;

/**
 * Covers the admin-post download handlers and their side-effect-free seams.
 */
class DownloadsIntegrationTest extends WP_UnitTestCase {

	/**
	 * Gives an Administrator the full Blacklist as CSV.
	 *
	 * @return void
	 */
	public function test_administrator_gets_the_csv_export(): void {
		global $wpdb;
		$repository = new EntryRepository( $wpdb, new AuditLog( $wpdb ), new BlockedAttemptLog( $wpdb ) );
		$this->assertTrue( $repository->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$csv = ( new CsvExportDownload( new CsvExporter( $wpdb ), new PrivacyDataAccess() ) )->csv_export_for_download();

		$this->assertSame( "match_type,email,customer_id,address_line_1,address_line_2,city,state,postcode,country,reason,status\nemail,buyer@example.com,,,,,,,,Fraud,active\n", $csv );
	}

	/**
	 * Refuses the CSV export to a WooCommerce operator.
	 *
	 * @return void
	 */
	public function test_woocommerce_operator_cannot_get_the_csv_export(): void {
		global $wpdb;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$result = ( new CsvExportDownload( new CsvExporter( $wpdb ), new PrivacyDataAccess() ) )->csv_export_for_download();

		$this->assertWPError( $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Stops the CSV download handler for a WooCommerce operator.
	 *
	 * @return void
	 */
	public function test_csv_download_handler_refuses_a_woocommerce_operator(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$this->assertSame( 403, $this->admin_post_die_code( 'wp_buyer_blacklist_export', array( '_wpnonce' => wp_create_nonce( 'wp_buyer_blacklist_export' ) ) ) );
	}

	/**
	 * Stops the CSV download handler when the link has expired.
	 *
	 * @return void
	 */
	public function test_csv_download_handler_refuses_an_invalid_nonce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 403, $this->admin_post_die_code( 'wp_buyer_blacklist_export', array( '_wpnonce' => 'expired-nonce' ) ) );
	}

	/**
	 * Stops the dossier download handler for a WooCommerce operator.
	 *
	 * @return void
	 */
	public function test_dossier_download_handler_refuses_a_woocommerce_operator(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$this->assertSame( 403, $this->admin_post_die_code( 'wp_buyer_blacklist_dossier_export', array( '_wpnonce' => wp_create_nonce( 'wp_buyer_blacklist_dossier_export' ) ) ) );
	}

	/**
	 * Stops the dossier download handler when the link has expired.
	 *
	 * @return void
	 */
	public function test_dossier_download_handler_refuses_an_invalid_nonce(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame(
			403,
			$this->admin_post_die_code(
				'wp_buyer_blacklist_dossier_export',
				array(
					'entry_id' => '1',
					'_wpnonce' => 'expired-nonce',
				)
			)
		);
	}

	/**
	 * Runs a download handler that is expected to stop with wp_die().
	 *
	 * @param string               $action admin-post action name.
	 * @param array<string, mixed> $query  Query parameters.
	 * @return int The wp_die() response code.
	 */
	private function admin_post_die_code( string $action, array $query ): int {
		$_GET     = $query;
		$_REQUEST = $query;

		try {
			do_action( 'admin_post_' . $action );
		} catch ( WPDieException $died ) {
			return $died->getCode();
		} finally {
			$_GET     = array();
			$_REQUEST = array();
		}

		$this->fail( "admin_post_{$action} did not stop." );
	}
}

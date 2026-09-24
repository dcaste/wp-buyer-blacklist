<?php
/**
 * Full Blacklist CSV download.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\ImportExport;

use WP_Buyer_Blacklist\EmailEntryRepository;
use WP_Error;

/**
 * Sends every Blacklist entry as an importable CSV file to an administrator.
 */
final class CsvExportDownload {

	/**
	 * Entry storage.
	 *
	 * @var EmailEntryRepository
	 */
	private EmailEntryRepository $entries;

	/**
	 * Creates the download handler.
	 *
	 * @param EmailEntryRepository $entries Entry storage.
	 */
	public function __construct( EmailEntryRepository $entries ) {
		$this->entries = $entries;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_wp_buyer_blacklist_export', array( $this, 'download_csv_export' ) );
	}

	/**
	 * Sends a full Blacklist CSV export to an administrator.
	 *
	 * @return void
	 */
	public function download_csv_export(): void {
		if ( ! $this->can_manage_privacy_data() ) {
			wp_die( esc_html__( 'You are not allowed to export Blacklist data.', 'wp-buyer-blacklist' ), 403 );
		}
		check_admin_referer( 'wp_buyer_blacklist_export' );
		$csv = $this->csv_export_for_download();
		if ( is_wp_error( $csv ) ) {
			wp_die( esc_html( $csv->get_error_message() ), (int) $csv->get_error_data()['status'] );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="buyer-blacklist.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV values are encoded by fputcsv().
		exit;
	}

	/**
	 * Gets the full Blacklist CSV for an administrator download.
	 *
	 * @return string|WP_Error CSV export or download error.
	 */
	public function csv_export_for_download(): string|WP_Error {
		if ( ! $this->can_manage_privacy_data() ) {
			return new WP_Error( 'wp_buyer_blacklist_forbidden', __( 'You are not allowed to export Blacklist data.', 'wp-buyer-blacklist' ), array( 'status' => 403 ) );
		}

		return $this->entries->export_csv();
	}

	/**
	 * Checks whether the current user may export Blacklist data.
	 *
	 * @return bool
	 */
	private function can_manage_privacy_data(): bool {
		return current_user_can( 'manage_options' );
	}
}

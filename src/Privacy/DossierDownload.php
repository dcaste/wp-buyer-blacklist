<?php
/**
 * Identity dossier download.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Privacy;

use WP_Error;

/**
 * Sends one identity dossier as a JSON file to an administrator.
 */
final class DossierDownload {

	/**
	 * Identity dossiers.
	 *
	 * @var DossierBuilder
	 */
	private DossierBuilder $dossiers;

	/**
	 * Privacy data access check.
	 *
	 * @var PrivacyDataAccess
	 */
	private PrivacyDataAccess $privacy_access;

	/**
	 * Creates the dossier download.
	 *
	 * @param DossierBuilder    $dossiers       Identity dossiers.
	 * @param PrivacyDataAccess $privacy_access Privacy data access check.
	 */
	public function __construct( DossierBuilder $dossiers, PrivacyDataAccess $privacy_access ) {
		$this->dossiers       = $dossiers;
		$this->privacy_access = $privacy_access;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_wp_buyer_blacklist_dossier_export', array( $this, 'download_identity_dossier' ) );
	}

	/**
	 * Sends one identity dossier as JSON to an administrator.
	 *
	 * @return void
	 */
	public function download_identity_dossier(): void {
		if ( ! $this->privacy_access->can_manage_privacy_data() ) {
			wp_die( esc_html__( 'You are not allowed to export Blacklist data.', 'wp-buyer-blacklist' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- identity_dossier_for_download() verifies this request nonce.
		$entry_id = isset( $_GET['entry_id'] ) ? absint( wp_unslash( $_GET['entry_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- identity_dossier_for_download() verifies this request nonce.
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$dossier = $this->identity_dossier_for_download( $entry_id, $nonce );
		if ( is_wp_error( $dossier ) ) {
			wp_die( esc_html( $dossier->get_error_message() ), (int) $dossier->get_error_data()['status'] );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="buyer-blacklist-dossier-' . $entry_id . '.json"' );
		echo wp_json_encode( $dossier, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() encodes every dossier value.
		exit;
	}

	/**
	 * Gets an identity dossier for an administrator download.
	 *
	 * @param int    $entry_id Blacklist entry ID.
	 * @param string $nonce    Admin action nonce.
	 * @return array<string, mixed>|WP_Error Identity dossier or download error.
	 */
	public function identity_dossier_for_download( int $entry_id, string $nonce ): array|WP_Error {
		if ( ! $this->privacy_access->can_manage_privacy_data() ) {
			return new WP_Error( 'wp_buyer_blacklist_forbidden', __( 'You are not allowed to export Blacklist data.', 'wp-buyer-blacklist' ), array( 'status' => 403 ) );
		}
		if ( ! wp_verify_nonce( $nonce, 'wp_buyer_blacklist_dossier_export' ) ) {
			return new WP_Error( 'wp_buyer_blacklist_invalid_nonce', __( 'The download link has expired. Refresh the page and try again.', 'wp-buyer-blacklist' ), array( 'status' => 403 ) );
		}

		$dossier = $this->dossiers->identity_dossier( $entry_id );
		if ( null === $dossier ) {
			return new WP_Error( 'wp_buyer_blacklist_not_found', __( 'The Blacklist entry could not be found.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		return $dossier;
	}
}

<?php
/**
 * CSV import REST routes.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\ImportExport;

use InvalidArgumentException;
use WP_Buyer_Blacklist\Access\OperatorAccess;
use WP_Buyer_Blacklist\EmailEntryRepository;
use WP_Buyer_Blacklist\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Validates and confirms CSV imports and lists import history.
 */
final class ImportExportController {

	/**
	 * Entry storage.
	 *
	 * @var EmailEntryRepository
	 */
	private EmailEntryRepository $entries;

	/**
	 * Blacklist operator access policy.
	 *
	 * @var OperatorAccess
	 */
	private OperatorAccess $operator_access;

	/**
	 * Creates the controller.
	 *
	 * @param EmailEntryRepository $entries         Entry storage.
	 * @param OperatorAccess       $operator_access Blacklist operator access policy.
	 */
	public function __construct( EmailEntryRepository $entries, OperatorAccess $operator_access ) {
		$this->entries         = $entries;
		$this->operator_access = $operator_access;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the CSV import routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/csv-import/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'validate_csv_import' ),
				'permission_callback' => array( $this->operator_access, 'can_manage_entries' ),
			)
		);
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/csv-import/confirm',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'confirm_csv_import' ),
				'permission_callback' => array( $this->operator_access, 'can_manage_entries' ),
			)
		);
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/import-history',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_import_history' ),
				'permission_callback' => array( $this->operator_access, 'can_manage_entries' ),
			)
		);
	}

	/**
	 * Validates a CSV file before the operator confirms its import.
	 *
	 * @param WP_REST_Request $request Request containing CSV text.
	 * @return WP_REST_Response|WP_Error
	 */
	public function validate_csv_import( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$csv = $request->get_param( 'csv' );
		if ( ! is_string( $csv ) ) {
			return new WP_Error( 'wp_buyer_blacklist_invalid_csv', __( 'Select a CSV file to import.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}

		try {
			$result = CsvImport::validate( $csv, WC()->countries->get_countries() );
		} catch ( InvalidArgumentException $exception ) {
			return new WP_Error( 'wp_buyer_blacklist_invalid_csv', $exception->getMessage(), array( 'status' => 400 ) );
		}

		foreach ( $result['accepted'] as $index => $entry ) {
			if ( $this->entries->exists( $entry['match_type'], $entry['canonical_hash'] ) ) {
				$result['duplicates'][] = array(
					'row'    => $entry['row'],
					'reason' => __( 'Duplicate entry.', 'wp-buyer-blacklist' ),
				);
				unset( $result['accepted'][ $index ] );
				continue;
			}
			$reason                                 = sanitize_textarea_field( $entry['reason'] );
			$result['accepted'][ $index ]['reason'] = '' === trim( $reason ) ? __( 'Imported blacklist entry', 'wp-buyer-blacklist' ) : $reason;
		}
		$result['accepted']  = array_values( $result['accepted'] );
		$result['file_name'] = sanitize_file_name( (string) $request->get_param( 'filename' ) );
		$token               = wp_generate_uuid4();
		set_transient( $this->csv_import_transient_key( $token ), $result, HOUR_IN_SECONDS );

		return new WP_REST_Response( $this->csv_import_result( $result, $token ) );
	}

	/**
	 * Imports the rows accepted during CSV validation.
	 *
	 * @param WP_REST_Request $request Confirmation token request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function confirm_csv_import( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$token  = $request->get_param( 'token' );
		$result = is_string( $token ) ? get_transient( $this->csv_import_transient_key( $token ) ) : false;
		if ( ! is_array( $result ) || ! isset( $result['accepted'], $result['duplicates'], $result['rejected'] ) ) {
			return new WP_Error( 'wp_buyer_blacklist_invalid_import', __( 'Validate the CSV file before importing it.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}

		$created = 0;
		foreach ( array_chunk( $result['accepted'], 100 ) as $batch ) {
			foreach ( $batch as $entry ) {
				$status = $this->entries->create_imported( $entry );
				if ( 'failed' === $status ) {
					return new WP_Error( 'wp_buyer_blacklist_import_failed', __( 'The import stopped because of a technical failure.', 'wp-buyer-blacklist' ), array( 'status' => 500 ) );
				}
				if ( 'duplicate' === $status ) {
					$result['duplicates'][] = array(
						'row'    => $entry['row'],
						'reason' => __( 'Duplicate entry.', 'wp-buyer-blacklist' ),
					);
					continue;
				}
				++$created;
			}
		}
		delete_transient( $this->csv_import_transient_key( $token ) );
		$this->entries->record_import( (string) ( $result['file_name'] ?? __( 'CSV import', 'wp-buyer-blacklist' ) ), count( $result['accepted'] ) + count( $result['duplicates'] ) + count( $result['rejected'] ), $created, count( $result['duplicates'] ), count( $result['rejected'] ) );

		return new WP_REST_Response( $this->csv_import_result( $result, '', $created ) );
	}

	/**
	 * Gets the transient key for the current operator's validated import.
	 *
	 * @param string $token Import confirmation token.
	 * @return string
	 */
	private function csv_import_transient_key( string $token ): string {
		return 'wp_buyer_blacklist_import_' . get_current_user_id() . '_' . $token;
	}

	/**
	 * Formats a validation review or completed import result.
	 *
	 * @param array{accepted: list<array<string, mixed>>, duplicates: list<array{row: int, reason: string}>, rejected: list<array{row: int, reason: string}>} $result Import result.
	 * @param string                                                                                                                                          $token  Confirmation token.
	 * @param int|null                                                                                                                                        $created Created entry count.
	 * @return array<string, mixed>
	 */
	private function csv_import_result( array $result, string $token, ?int $created = null ): array {
		return array(
			'token'      => $token,
			'valid'      => count( $result['accepted'] ),
			'created'    => $created,
			'duplicates' => $result['duplicates'],
			'rejected'   => $result['rejected'],
		);
	}

	/**
	 * Gets protected import history.
	 *
	 * @return list<array<string, string>>
	 */
	public function get_import_history(): array {
		return $this->entries->recent_imports();
	}
}

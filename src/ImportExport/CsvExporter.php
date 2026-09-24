<?php
/**
 * Blacklist CSV export.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\ImportExport;

use WP_Buyer_Blacklist\Storage\Schema;

/**
 * Writes every Blacklist entry as an importable CSV document.
 */
final class CsvExporter {

	/**
	 * WordPress database connection.
	 *
	 * @var \wpdb
	 */
	private \wpdb $database;

	/**
	 * Entries table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Creates the exporter.
	 *
	 * @param \wpdb $database WordPress database connection.
	 */
	public function __construct( \wpdb $database ) {
		$this->database   = $database;
		$this->table_name = $database->prefix . Schema::ENTRIES_TABLE;
	}

	/**
	 * Exports every Blacklist entry as UTF-8 CSV.
	 *
	 * @return string CSV document.
	 */
	public function export_csv(): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- A temporary in-memory stream does not use the filesystem.
		$stream = fopen( 'php://temp', 'r+' );
		if ( false === $stream ) {
			return '';
		}
		fputcsv( $stream, array( 'match_type', 'email', 'customer_id', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country', 'reason', 'status' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$entries = $this->database->get_results( "SELECT match_type, value, reason, is_active FROM {$this->table_name} ORDER BY id ASC", ARRAY_A );
		foreach ( is_array( $entries ) ? $entries : array() as $entry ) {
			fputcsv( $stream, $this->export_row( $entry ) );
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the temporary in-memory stream.
		fclose( $stream );

		return false === $csv ? '' : $csv;
	}

	/**
	 * Converts a stored Blacklist entry to an importable CSV row.
	 *
	 * @param array<string, string> $entry Stored Blacklist entry.
	 * @return array<int, string> CSV row.
	 */
	private function export_row( array $entry ): array {
		$row = array(
			'match_type'     => $entry['match_type'],
			'email'          => '',
			'customer_id'    => '',
			'address_line_1' => '',
			'address_line_2' => '',
			'city'           => '',
			'state'          => '',
			'postcode'       => '',
			'country'        => '',
			'reason'         => $entry['reason'],
			'status'         => '1' === $entry['is_active'] ? 'active' : 'inactive',
		);
		if ( 'email' === $entry['match_type'] || 'customer_id' === $entry['match_type'] ) {
			$row[ $entry['match_type'] ] = $entry['value'];
			return array_values( $row );
		}

		$address = json_decode( $entry['value'], true );
		if ( is_array( $address ) ) {
			foreach ( array( 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country' ) as $field ) {
				if ( isset( $address[ $field ] ) && is_string( $address[ $field ] ) ) {
					$row[ $field ] = $address[ $field ];
				}
			}
		}

		return array_values( $row );
	}
}

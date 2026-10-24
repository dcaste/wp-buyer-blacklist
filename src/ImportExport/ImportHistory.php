<?php
/**
 * CSV import history persistence.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\ImportExport;

use WP_Buyer_Blacklist\Storage\Schema;

/**
 * Records and lists the outcomes of confirmed CSV imports.
 */
final class ImportHistory {

	/**
	 * WordPress database connection.
	 *
	 * @var \wpdb
	 */
	private \wpdb $database;

	/**
	 * Import history table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Creates the import history.
	 *
	 * @param \wpdb $database WordPress database connection.
	 */
	public function __construct( \wpdb $database ) {
		$this->database   = $database;
		$this->table_name = $database->prefix . Schema::IMPORTS_TABLE;
	}

	/**
	 * Records the non-sensitive outcome of a confirmed CSV import.
	 *
	 * @param string $file_name     Uploaded file name.
	 * @param int    $entries_found Rows found during validation.
	 * @param int    $entries_created Entries created.
	 * @param int    $duplicates     Duplicate rows skipped.
	 * @param int    $rejected       Rejected rows.
	 * @return bool Whether history was recorded.
	 */
	public function record_import( string $file_name, int $entries_found, int $entries_created, int $duplicates, int $rejected ): bool {
		return 1 === $this->database->insert(
			$this->table_name,
			array(
				'file_name'       => $file_name,
				'entries_found'   => $entries_found,
				'entries_created' => $entries_created,
				'duplicates'      => $duplicates,
				'rejected'        => $rejected,
				'outcome'         => $duplicates || $rejected ? 'warning' : 'completed',
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Gets recent import outcomes.
	 *
	 * @return list<array<string, string>> Recent imports.
	 */
	public function recent_imports(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$results = $this->database->get_results( "SELECT id, file_name, entries_found, entries_created, duplicates, rejected, outcome, created_at FROM {$this->table_name} ORDER BY id DESC", ARRAY_A );
		return is_array( $results ) ? $results : array();
	}
}

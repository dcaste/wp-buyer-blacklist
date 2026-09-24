<?php
/**
 * Blocked attempt persistence.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Enforcement;

use WP_Buyer_Blacklist\Storage\Schema;

/**
 * Records and lists Blocked attempts.
 */
final class BlockedAttemptLog implements BlockedAttemptRecorder {

	/**
	 * WordPress database connection.
	 *
	 * @var \wpdb
	 */
	private \wpdb $database;

	/**
	 * Blocked attempts table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Creates the Blocked attempt log.
	 *
	 * @param \wpdb $database WordPress database connection.
	 */
	public function __construct( \wpdb $database ) {
		$this->database   = $database;
		$this->table_name = $database->prefix . Schema::BLOCKED_ATTEMPTS_TABLE;
	}

	/**
	 * Records a Blocked attempt.
	 *
	 * @param int    $entry_id       Matching Blacklist entry identifier.
	 * @param string $identity_value Evaluated Buyer identity value.
	 * @return bool Whether the attempt was recorded.
	 */
	public function record_blocked_attempt( int $entry_id, string $identity_value ): bool {
		$recorded = 1 === $this->database->insert(
			$this->table_name,
			array(
				'entry_id'       => $entry_id,
				'identity_value' => $identity_value,
				'outcome'        => 'blocked',
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
		return $recorded;
	}

	/**
	 * Gets Blocked attempts for an entry in reverse creation order.
	 *
	 * @param int $entry_id Entry identifier.
	 * @return list<array<string, string>>
	 */
	public function blocked_attempts( int $entry_id ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$results = $this->database->get_results( $this->database->prepare( "SELECT id, identity_value, outcome, created_at FROM {$this->table_name} WHERE entry_id = %d ORDER BY created_at DESC, id DESC", $entry_id ), ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Counts every Blocked attempt.
	 *
	 * @return int
	 */
	public function count(): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		return (int) $this->database->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
	}

	/**
	 * Deletes every Blocked attempt of an entry.
	 *
	 * @param int $entry_id Entry identifier.
	 * @return int|false Deleted rows, or false on failure.
	 */
	public function delete_for_entry( int $entry_id ): int|false {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		return $this->database->query( $this->database->prepare( "DELETE FROM {$this->table_name} WHERE entry_id = %d", $entry_id ) );
	}
}

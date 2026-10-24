<?php
/**
 * Audit event persistence.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Entries;

use WP_Buyer_Blacklist\Storage\Schema;

/**
 * Records and lists administrative Audit events for Blacklist entries.
 */
final class AuditLog {

	/**
	 * WordPress database connection.
	 *
	 * @var \wpdb
	 */
	private \wpdb $database;

	/**
	 * Audit events table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Creates the Audit log.
	 *
	 * @param \wpdb $database WordPress database connection.
	 */
	public function __construct( \wpdb $database ) {
		$this->database   = $database;
		$this->table_name = $database->prefix . Schema::AUDIT_EVENTS_TABLE;
	}

	/**
	 * Records an ordinary administrative Audit event.
	 *
	 * @param int|null $entry_id Entry identifier, or null for a deletion event.
	 * @param string   $action   Action name.
	 * @return bool Whether the event was recorded.
	 */
	public function record( ?int $entry_id, string $action ): bool {
		$user = wp_get_current_user();
		$data = array(
			'entry_id'   => $entry_id,
			'action'     => $action,
			'actor_id'   => null,
			'actor_name' => null,
			'created_at' => current_time( 'mysql', true ),
		);

		if ( null !== $entry_id && $user instanceof \WP_User && 0 !== $user->ID ) {
			$data['actor_id']   = $user->ID;
			$data['actor_name'] = $user->display_name;
		}

		return 1 === $this->database->insert( $this->table_name, $data, array( '%d', '%s', '%d', '%s', '%s' ) );
	}

	/**
	 * Gets an entry's Audit events in reverse creation order.
	 *
	 * @param int $entry_id Entry identifier.
	 * @return list<array<string, mixed>>
	 */
	public function audit_events( int $entry_id ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$results = $this->database->get_results( $this->database->prepare( "SELECT action, actor_id, actor_name, created_at FROM {$this->table_name} WHERE entry_id = %d ORDER BY created_at DESC, id DESC", $entry_id ), ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Gets retained minimal deletion Audit events.
	 *
	 * @return list<array<string, mixed>> Deletion events.
	 */
	public function deletion_events(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$events = $this->database->get_results( "SELECT action, created_at FROM {$this->table_name} WHERE entry_id IS NULL ORDER BY id DESC", ARRAY_A );

		return is_array( $events ) ? $events : array();
	}

	/**
	 * Deletes every Audit event of an entry.
	 *
	 * @param int $entry_id Entry identifier.
	 * @return int|false Deleted rows, or false on failure.
	 */
	public function delete_for_entry( int $entry_id ): int|false {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		return $this->database->query( $this->database->prepare( "DELETE FROM {$this->table_name} WHERE entry_id = %d", $entry_id ) );
	}
}

<?php
/**
 * Email Blacklist entry persistence.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

/**
 * Stores and finds email Blacklist entries.
 */
final class EmailEntryRepository implements ActiveEmailEntryFinder {

	/**
	 * Current database schema version.
	 */
	private const DATABASE_VERSION = '2';

	/**
	 * WordPress database connection.
	 *
	 * @var \wpdb
	 */
	private \wpdb $database;

	/**
	 * Plugin table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Creates the repository.
	 *
	 * @param \wpdb $database WordPress database connection.
	 */
	public function __construct( \wpdb $database ) {
		$this->database   = $database;
		$this->table_name = $database->prefix . 'buyer_blacklist_entries';
	}

	/**
	 * Creates the entries table.
	 *
	 * @return void
	 */
	public function install(): void {
		if ( self::DATABASE_VERSION === get_option( 'wp_buyer_blacklist_database_version' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->database->get_charset_collate();
		$sql             = "CREATE TABLE {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			match_type varchar(32) NOT NULL,
			value text NOT NULL,
			canonical_value text NOT NULL,
			canonical_hash char(64) NOT NULL,
			reason text NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY match_value (match_type,canonical_hash)
		) {$charset_collate};";

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		dbDelta( $sql );
		$audit_table    = $this->audit_table_name();
		$attempts_table = $this->database->prefix . 'buyer_blacklist_blocked_attempts';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use only the WordPress table prefix.
		dbDelta( "CREATE TABLE {$audit_table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, entry_id bigint(20) unsigned NULL, action varchar(32) NOT NULL, actor_id bigint(20) unsigned NULL, actor_name varchar(255) NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY entry_id (entry_id)) {$charset_collate};" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		dbDelta( "CREATE TABLE {$attempts_table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, entry_id bigint(20) unsigned NOT NULL, identity_value text NOT NULL, outcome varchar(32) NOT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY entry_id (entry_id)) {$charset_collate};" );
		update_option( 'wp_buyer_blacklist_database_version', self::DATABASE_VERSION );
	}

	/**
	 * Creates an active email Blacklist entry.
	 *
	 * @param EmailEntry $entry  Email identity to store.
	 * @param string     $reason Operator-provided reason.
	 * @return bool Whether an entry was created.
	 */
	public function create( EmailEntry $entry, string $reason ): bool {
		$result = $this->database->insert(
			$this->table_name,
			array(
				'match_type'      => 'email',
				'value'           => $entry->canonical_value(),
				'canonical_value' => $entry->canonical_value(),
				'canonical_hash'  => $entry->canonical_hash(),
				'reason'          => $reason,
				'is_active'       => 1,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return 1 === $result && $this->record_audit_event( (int) $this->database->insert_id, 'created' );
	}

	/**
	 * Updates an entry reason.
	 *
	 * @param int    $entry_id Entry identifier.
	 * @param string $reason   New reason.
	 * @return bool Whether the entry was updated.
	 */
	public function update_reason( int $entry_id, string $reason ): bool {
		$result = $this->database->update(
			$this->table_name,
			array( 'reason' => $reason ),
			array(
				'id'         => $entry_id,
				'match_type' => 'email',
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		return 1 === $result && $this->record_audit_event( $entry_id, 'reason_changed' );
	}

	/**
	 * Activates or deactivates an entry.
	 *
	 * @param int  $entry_id Entry identifier.
	 * @param bool $active   Whether the entry is active.
	 * @return bool Whether the entry was updated.
	 */
	public function set_active( int $entry_id, bool $active ): bool {
		$result = $this->database->update(
			$this->table_name,
			array( 'is_active' => $active ? 1 : 0 ),
			array(
				'id'         => $entry_id,
				'match_type' => 'email',
			),
			array( '%d' ),
			array( '%d', '%s' )
		);

		return 1 === $result && $this->record_audit_event( $entry_id, $active ? 'activated' : 'deactivated' );
	}

	/**
	 * Deletes an entry and retains only its deletion event.
	 *
	 * @param int $entry_id Entry identifier.
	 * @return bool Whether the entry was deleted.
	 */
	public function delete( int $entry_id ): bool {
		if ( false === $this->database->query( 'START TRANSACTION' ) ) {
			return false;
		}

		$audit_table    = $this->audit_table_name();
		$attempts_table = $this->database->prefix . 'buyer_blacklist_blocked_attempts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$events_deleted = $this->database->query( $this->database->prepare( "DELETE FROM {$audit_table} WHERE entry_id = %d", $entry_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$attempts_deleted = $this->database->query( $this->database->prepare( "DELETE FROM {$attempts_table} WHERE entry_id = %d", $entry_id ) );
		$entry_deleted    = $this->database->delete(
			$this->table_name,
			array(
				'id'         => $entry_id,
				'match_type' => 'email',
			),
			array( '%d', '%s' )
		);
		$event_created    = 1 === $entry_deleted && $this->record_audit_event( null, 'deleted' );

		if ( false === $events_deleted || false === $attempts_deleted || ! $event_created ) {
			$this->database->query( 'ROLLBACK' );
			return false;
		}

		return false !== $this->database->query( 'COMMIT' );
	}

	/**
	 * Checks whether an active email entry matches a Buyer email.
	 *
	 * @param string $email Buyer email address.
	 * @return bool Whether an active entry matches.
	 */
	public function has_active_email( string $email ): bool {
		try {
			$entry = EmailEntry::from_email( $email );
		} catch ( \InvalidArgumentException $exception ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$query = $this->database->prepare(
			"SELECT id FROM {$this->table_name} WHERE match_type = %s AND canonical_hash = %s AND is_active = %d LIMIT 1",
			'email',
			$entry->canonical_hash(),
			1
		);

		return null !== $this->database->get_var( $query );
	}

	/**
	 * Gets email entries in reverse creation order.
	 *
	 * @return list<array<string, string>>
	 */
	public function all(): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$results = $this->database->get_results( "SELECT id, value, reason, is_active, created_at FROM {$this->table_name} WHERE match_type = 'email' ORDER BY created_at DESC, id DESC", ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Gets an entry's Audit events in reverse creation order.
	 *
	 * @param int $entry_id Entry identifier.
	 * @return list<array<string, mixed>>
	 */
	public function audit_events( int $entry_id ): array {
		$audit_table = $this->audit_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$results = $this->database->get_results( $this->database->prepare( "SELECT action, actor_id, actor_name, created_at FROM {$audit_table} WHERE entry_id = %d ORDER BY created_at DESC, id DESC", $entry_id ), ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Records an ordinary administrative Audit event.
	 *
	 * @param int|null $entry_id Entry identifier, or null for a deletion event.
	 * @param string   $action   Action name.
	 * @return bool Whether the event was recorded.
	 */
	private function record_audit_event( ?int $entry_id, string $action ): bool {
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

		return 1 === $this->database->insert( $this->audit_table_name(), $data, array( '%d', '%s', '%d', '%s', '%s' ) );
	}

	/**
	 * Gets the Audit events table name.
	 *
	 * @return string
	 */
	private function audit_table_name(): string {
		return $this->database->prefix . 'buyer_blacklist_audit_events';
	}
}

<?php
/**
 * Blacklist entry persistence.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Entries;

use WP_Buyer_Blacklist\Enforcement\ActiveAddressEntryFinder;
use WP_Buyer_Blacklist\Enforcement\ActiveCustomerIdEntryFinder;
use WP_Buyer_Blacklist\Enforcement\ActiveEmailEntryFinder;
use WP_Buyer_Blacklist\Enforcement\BlockedAttemptLog;
use WP_Buyer_Blacklist\Storage\Schema;

/**
 * Stores and finds Blacklist entries of every Match type.
 */
final class EntryRepository implements ActiveAddressEntryFinder, ActiveCustomerIdEntryFinder, ActiveEmailEntryFinder {

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
	 * Audit events for entry changes.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit_log;

	/**
	 * Blocked attempts removed with their entry.
	 *
	 * @var BlockedAttemptLog
	 */
	private BlockedAttemptLog $blocked_attempts;

	/**
	 * Creates the repository.
	 *
	 * @param \wpdb             $database         WordPress database connection.
	 * @param AuditLog          $audit_log        Audit events for entry changes.
	 * @param BlockedAttemptLog $blocked_attempts Blocked attempts removed with their entry.
	 */
	public function __construct( \wpdb $database, AuditLog $audit_log, BlockedAttemptLog $blocked_attempts ) {
		$this->database         = $database;
		$this->table_name       = $database->prefix . Schema::ENTRIES_TABLE;
		$this->audit_log        = $audit_log;
		$this->blocked_attempts = $blocked_attempts;
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

		return 1 === $result && $this->audit_log->record( (int) $this->database->insert_id, 'created' );
	}

	/**
	 * Creates an active Customer-ID Blacklist entry.
	 *
	 * @param CustomerIdEntry $entry  Customer identity to store.
	 * @param string          $reason Operator-provided reason.
	 * @return bool Whether an entry was created.
	 */
	public function create_customer_id( CustomerIdEntry $entry, string $reason ): bool {
		$result = $this->database->insert(
			$this->table_name,
			array(
				'match_type'      => 'customer_id',
				'value'           => $entry->canonical_value(),
				'canonical_value' => $entry->canonical_value(),
				'canonical_hash'  => $entry->canonical_hash(),
				'reason'          => $reason,
				'is_active'       => 1,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return 1 === $result && $this->audit_log->record( (int) $this->database->insert_id, 'created' );
	}

	/**
	 * Creates an active address Blacklist entry.
	 *
	 * @param AddressEntry $entry  Address identity to store.
	 * @param string       $reason Operator-provided reason.
	 * @return bool Whether an entry was created.
	 */
	public function create_address( AddressEntry $entry, string $reason ): bool {
		$value  = wp_json_encode( $entry->canonical_value() );
		$result = $this->database->insert(
			$this->table_name,
			array(
				'match_type'      => $entry->match_type(),
				'value'           => $value,
				'canonical_value' => $value,
				'canonical_hash'  => $entry->canonical_hash(),
				'reason'          => $reason,
				'is_active'       => 1,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return 1 === $result && $this->audit_log->record( (int) $this->database->insert_id, 'created' );
	}

	/**
	 * Checks whether a Blacklist entry already has this identity.
	 *
	 * @param string $match_type     Match type.
	 * @param string $canonical_hash Database-safe identity key.
	 * @return bool Whether the entry exists.
	 */
	public function exists( string $match_type, string $canonical_hash ): bool {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$query = $this->database->prepare( "SELECT id FROM {$this->table_name} WHERE match_type = %s AND canonical_hash = %s", $match_type, $canonical_hash );

		return null !== $this->database->get_var( $query );
	}

	/**
	 * Creates an active entry validated by the CSV importer.
	 *
	 * @param array{match_type: string, value: string|array<string, string>, canonical_hash: string, reason: string, is_active: bool, row: int} $entry Validated import entry.
	 * @return string created, duplicate, or failed.
	 */
	public function create_imported( array $entry ): string {
		$value  = is_array( $entry['value'] ) ? wp_json_encode( $entry['value'] ) : $entry['value'];
		$result = $this->database->insert(
			$this->table_name,
			array(
				'match_type'      => $entry['match_type'],
				'value'           => $value,
				'canonical_value' => $value,
				'canonical_hash'  => $entry['canonical_hash'],
				'reason'          => $entry['reason'],
				'is_active'       => $entry['is_active'] ? 1 : 0,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		if ( 1 === $result ) {
			return $this->audit_log->record( (int) $this->database->insert_id, 'imported' ) ? 'created' : 'failed';
		}

		return $this->exists( $entry['match_type'], $entry['canonical_hash'] ) ? 'duplicate' : 'failed';
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
			array( 'id' => $entry_id ),
			array( '%s' ),
			array( '%d' )
		);

		return 1 === $result && $this->audit_log->record( $entry_id, 'reason_changed' );
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
			array( 'id' => $entry_id ),
			array( '%d' ),
			array( '%d' )
		);

		return 1 === $result && $this->audit_log->record( $entry_id, $active ? 'activated' : 'deactivated' );
	}

	/**
	 * Deletes an entry and retains only its deletion event.
	 *
	 * @param int $entry_id Entry identifier.
	 * @return bool Whether the entry was deleted.
	 */
	public function delete( int $entry_id ): bool {
		return $this->remove( $entry_id, 'deleted' );
	}

	/**
	 * Erases an identity and its personal data.
	 *
	 * @param int $entry_id Entry identifier.
	 * @return bool Whether the identity was erased.
	 */
	public function erase( int $entry_id ): bool {
		return $this->remove( $entry_id, 'erased' );
	}

	/**
	 * Removes an entry and its associated personal data.
	 *
	 * @param int    $entry_id Entry identifier.
	 * @param string $action   Minimal retained Audit event action.
	 * @return bool Whether the identity was removed.
	 */
	private function remove( int $entry_id, string $action ): bool {
		if ( false === $this->database->query( 'START TRANSACTION' ) ) {
			return false;
		}

		$events_deleted   = $this->audit_log->delete_for_entry( $entry_id );
		$attempts_deleted = $this->blocked_attempts->delete_for_entry( $entry_id );
		$entry_deleted    = $this->database->delete( $this->table_name, array( 'id' => $entry_id ), array( '%d' ) );
		$event_created    = 1 === $entry_deleted && $this->audit_log->record( null, $action );

		if ( false === $events_deleted || false === $attempts_deleted || ! $event_created ) {
			$this->database->query( 'ROLLBACK' );
			return false;
		}

		return false !== $this->database->query( 'COMMIT' );
	}

	/**
	 * Finds every active Blacklist entry matching a WooCommerce Customer ID.
	 *
	 * @param int $customer_id WooCommerce Customer ID.
	 * @return list<int> Matching entry identifiers.
	 */
	public function matching_active_customer_id_entry_ids( int $customer_id ): array {
		if ( $customer_id <= 0 ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$query = $this->database->prepare(
			"SELECT id FROM {$this->table_name} WHERE match_type = %s AND canonical_hash = %s AND is_active = %d",
			'customer_id',
			hash( 'sha256', (string) $customer_id ),
			1
		);

		return array_map( 'intval', (array) $this->database->get_col( $query ) );
	}

	/**
	 * Finds every Blacklist entry with a canonical identity value.
	 *
	 * @param string $match_type     Entry Match type.
	 * @param string $canonical_hash Canonical identity hash.
	 * @return list<array{id: string, is_active: string}> Matching entries.
	 */
	public function matching_entries( string $match_type, string $canonical_hash ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$query   = $this->database->prepare(
			"SELECT id, is_active FROM {$this->table_name} WHERE match_type = %s AND canonical_hash = %s",
			$match_type,
			$canonical_hash
		);
		$entries = $this->database->get_results( $query, ARRAY_A );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Finds every active Blacklist entry matching a Buyer email.
	 *
	 * @param string $email Buyer email address.
	 * @return list<int> Matching entry identifiers.
	 */
	public function matching_active_email_entry_ids( string $email ): array {
		try {
			$entry = EmailEntry::from_email( $email );
		} catch ( \InvalidArgumentException $exception ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$query     = $this->database->prepare(
			"SELECT id FROM {$this->table_name} WHERE match_type = %s AND canonical_hash = %s AND is_active = %d",
			'email',
			$entry->canonical_hash(),
			1
		);
		$entry_ids = $this->database->get_col( $query );

		return array_map( 'intval', is_array( $entry_ids ) ? $entry_ids : array() );
	}

	/**
	 * Finds every active entry matching a complete address.
	 *
	 * @param AddressEntry $entry Address identity.
	 * @return list<int> Matching entry identifiers.
	 */
	public function matching_active_address_entry_ids( AddressEntry $entry ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$query     = $this->database->prepare(
			"SELECT id FROM {$this->table_name} WHERE match_type = %s AND canonical_hash = %s AND is_active = %d",
			$entry->match_type(),
			$entry->canonical_hash(),
			1
		);
		$entry_ids = $this->database->get_col( $query );

		return array_map( 'intval', is_array( $entry_ids ) ? $entry_ids : array() );
	}

	/**
	 * Gets Blacklist entries in reverse creation order.
	 *
	 * @return list<array<string, string>>
	 */
	public function all(): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$results = $this->database->get_results( "SELECT id, match_type, value, reason, is_active, created_at FROM {$this->table_name} ORDER BY created_at DESC, id DESC", ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Gets the entry and Blocked attempt totals for the admin summary.
	 *
	 * @return array{total_entries: int, active_entries: int, inactive_entries: int, blocked_attempts: int}
	 */
	public function summary(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use only the WordPress table prefix.
		$entries = $this->database->get_row( "SELECT COUNT(*) AS total_entries, SUM(is_active = 1) AS active_entries, SUM(is_active = 0) AS inactive_entries FROM {$this->table_name}", ARRAY_A );

		return array(
			'total_entries'    => (int) ( $entries['total_entries'] ?? 0 ),
			'active_entries'   => (int) ( $entries['active_entries'] ?? 0 ),
			'inactive_entries' => (int) ( $entries['inactive_entries'] ?? 0 ),
			'blocked_attempts' => $this->blocked_attempts->count(),
		);
	}
}

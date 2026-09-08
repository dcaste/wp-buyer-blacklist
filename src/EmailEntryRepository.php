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
final class EmailEntryRepository implements ActiveAddressEntryFinder, ActiveEmailEntryFinder, BlockedAttemptRecorder {

	/**
	 * Current database schema version.
	 */
	private const DATABASE_VERSION = '3';

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
		dbDelta(
			"CREATE TABLE {$audit_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) unsigned NULL,
			action varchar(32) NOT NULL,
			actor_id bigint(20) unsigned NULL,
			actor_name varchar(255) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY entry_id (entry_id)
		) {$charset_collate};"
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		dbDelta(
			"CREATE TABLE {$attempts_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) unsigned NOT NULL,
			identity_value text NOT NULL,
			outcome varchar(32) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY entry_id (entry_id)
		) {$charset_collate};"
		);
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

		return 1 === $result && $this->record_audit_event( (int) $this->database->insert_id, 'created' );
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

		return 1 === $result && $this->record_audit_event( (int) $this->database->insert_id, 'created' );
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
	 * @param array{match_type: string, value: string|array<string, string>, canonical_hash: string, reason: string, row: int} $entry Validated import entry.
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
				'is_active'       => 1,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		if ( 1 === $result ) {
			return $this->record_audit_event( (int) $this->database->insert_id, 'imported' ) ? 'created' : 'failed';
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
			array( 'id' => $entry_id ),
			array( '%d' ),
			array( '%d' )
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

		$audit_table    = $this->audit_table_name();
		$attempts_table = $this->database->prefix . 'buyer_blacklist_blocked_attempts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$events_deleted = $this->database->query( $this->database->prepare( "DELETE FROM {$audit_table} WHERE entry_id = %d", $entry_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$attempts_deleted = $this->database->query( $this->database->prepare( "DELETE FROM {$attempts_table} WHERE entry_id = %d", $entry_id ) );
		$entry_deleted    = $this->database->delete( $this->table_name, array( 'id' => $entry_id ), array( '%d' ) );
		$event_created    = 1 === $entry_deleted && $this->record_audit_event( null, $action );

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
	 * Records an email Blocked attempt.
	 *
	 * @param int    $entry_id       Matching Blacklist entry identifier.
	 * @param string $identity_value Evaluated Buyer email address.
	 * @return bool Whether the attempt was recorded.
	 */
	public function record_blocked_attempt( int $entry_id, string $identity_value ): bool {
		return 1 === $this->database->insert(
			$this->database->prefix . 'buyer_blacklist_blocked_attempts',
			array(
				'entry_id'       => $entry_id,
				'identity_value' => $identity_value,
				'outcome'        => 'blocked',
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Gets Blocked attempts for an entry in reverse creation order.
	 *
	 * @param int $entry_id Entry identifier.
	 * @return list<array<string, string>>
	 */
	public function blocked_attempts( int $entry_id ): array {
		$attempts_table = $this->database->prefix . 'buyer_blacklist_blocked_attempts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$results = $this->database->get_results( $this->database->prepare( "SELECT id, identity_value, outcome, created_at FROM {$attempts_table} WHERE entry_id = %d ORDER BY created_at DESC, id DESC", $entry_id ), ARRAY_A );

		return is_array( $results ) ? $results : array();
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
		fputcsv( $stream, array( 'match_type', 'value', 'reason', 'status', 'created_at' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$entries = $this->database->get_results( "SELECT match_type, value, reason, is_active, created_at FROM {$this->table_name} ORDER BY id ASC", ARRAY_A );
		foreach ( is_array( $entries ) ? $entries : array() as $entry ) {
			fputcsv( $stream, array( $entry['match_type'], $entry['value'], $entry['reason'], '1' === $entry['is_active'] ? 'active' : 'inactive', $entry['created_at'] ) );
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the temporary in-memory stream.
		fclose( $stream );

		return false === $csv ? '' : $csv;
	}

	/**
	 * Gets one identity dossier.
	 *
	 * @param int $entry_id Entry identifier.
	 * @return array<string, mixed>|null Identity dossier, if it exists.
	 */
	public function identity_dossier( int $entry_id ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$entry = $this->database->get_row( $this->database->prepare( "SELECT id, match_type, value, reason, is_active, created_at FROM {$this->table_name} WHERE id = %d", $entry_id ), ARRAY_A );
		if ( ! is_array( $entry ) ) {
			return null;
		}
		$value              = json_decode( $entry['value'], true );
		$entry['value']     = is_array( $value ) ? $value : $entry['value'];
		$entry['is_active'] = '1' === $entry['is_active'];

		return array(
			'entry'            => $entry,
			'blocked_attempts' => $this->blocked_attempts( $entry_id ),
			'audit_events'     => $this->audit_events( $entry_id ),
		);
	}

	/**
	 * Gets dossiers for email identities matched by the WordPress privacy tools.
	 *
	 * @param string $email Buyer email address.
	 * @return list<array<string, mixed>> Identity dossiers.
	 */
	public function identity_dossiers_for_email( string $email ): array {
		try {
			$entry = EmailEntry::from_email( $email );
		} catch ( \InvalidArgumentException $exception ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$ids = $this->database->get_col( $this->database->prepare( "SELECT id FROM {$this->table_name} WHERE match_type = %s AND canonical_hash = %s", 'email', $entry->canonical_hash() ) );

		return array_values( array_filter( array_map( fn( $id ) => $this->identity_dossier( (int) $id ), is_array( $ids ) ? $ids : array() ) ) );
	}

	/**
	 * Gets retained minimal deletion Audit events.
	 *
	 * @return list<array<string, mixed>> Deletion events.
	 */
	public function deletion_events(): array {
		$audit_table = $this->audit_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		$events = $this->database->get_results( "SELECT action, created_at FROM {$audit_table} WHERE entry_id IS NULL ORDER BY id DESC", ARRAY_A );

		return is_array( $events ) ? $events : array();
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
	 * Removes all plugin data during an explicitly configured uninstall.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use only the WordPress table prefix.
		$this->database->query( "DROP TABLE IF EXISTS {$this->table_name}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use only the WordPress table prefix.
		$this->database->query( "DROP TABLE IF EXISTS {$this->audit_table_name()}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use only the WordPress table prefix.
		$this->database->query( "DROP TABLE IF EXISTS {$this->database->prefix}buyer_blacklist_blocked_attempts" );
		delete_option( 'wp_buyer_blacklist_database_version' );
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

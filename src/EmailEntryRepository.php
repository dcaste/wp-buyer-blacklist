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
	private const DATABASE_VERSION = '1';

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
		$audit_table    = $this->database->prefix . 'buyer_blacklist_audit_events';
		$attempts_table = $this->database->prefix . 'buyer_blacklist_blocked_attempts';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use only the WordPress table prefix.
		dbDelta( "CREATE TABLE {$audit_table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, entry_id bigint(20) unsigned NULL, action varchar(32) NOT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY entry_id (entry_id)) {$charset_collate};" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use only the WordPress table prefix.
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

		return 1 === $result;
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
}

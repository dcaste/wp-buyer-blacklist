<?php
/**
 * Plugin database tables.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Storage;

/**
 * Names, creates and removes the plugin tables.
 */
final class Schema {

	/**
	 * Current database schema version.
	 */
	private const DATABASE_VERSION = '4';

	/**
	 * Blacklist entries table, without the WordPress prefix.
	 */
	public const ENTRIES_TABLE = 'buyer_blacklist_entries';

	/**
	 * Audit events table, without the WordPress prefix.
	 */
	public const AUDIT_EVENTS_TABLE = 'buyer_blacklist_audit_events';

	/**
	 * Blocked attempts table, without the WordPress prefix.
	 */
	public const BLOCKED_ATTEMPTS_TABLE = 'buyer_blacklist_blocked_attempts';

	/**
	 * Import history table, without the WordPress prefix.
	 */
	public const IMPORTS_TABLE = 'buyer_blacklist_imports';

	/**
	 * WordPress database connection.
	 *
	 * @var \wpdb
	 */
	private \wpdb $database;

	/**
	 * Creates the schema.
	 *
	 * @param \wpdb $database WordPress database connection.
	 */
	public function __construct( \wpdb $database ) {
		$this->database = $database;
	}

	/**
	 * Creates or upgrades the plugin tables.
	 *
	 * @return void
	 */
	public function install(): void {
		if ( self::DATABASE_VERSION === get_option( 'wp_buyer_blacklist_database_version' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->database->get_charset_collate();
		$entries_table   = $this->database->prefix . self::ENTRIES_TABLE;
		$sql             = "CREATE TABLE {$entries_table} (
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
		$audit_table    = $this->database->prefix . self::AUDIT_EVENTS_TABLE;
		$attempts_table = $this->database->prefix . self::BLOCKED_ATTEMPTS_TABLE;
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
		$imports_table = $this->database->prefix . self::IMPORTS_TABLE;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table name uses only the WordPress table prefix.
		dbDelta(
			"CREATE TABLE {$imports_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			file_name varchar(255) NOT NULL,
			entries_found int unsigned NOT NULL,
			entries_created int unsigned NOT NULL,
			duplicates int unsigned NOT NULL,
			rejected int unsigned NOT NULL,
			outcome varchar(16) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};"
		);
		update_option( 'wp_buyer_blacklist_database_version', self::DATABASE_VERSION );
	}

	/**
	 * Removes all plugin data during an explicitly configured uninstall.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$entries_table  = $this->database->prefix . self::ENTRIES_TABLE;
		$audit_table    = $this->database->prefix . self::AUDIT_EVENTS_TABLE;
		$attempts_table = $this->database->prefix . self::BLOCKED_ATTEMPTS_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use only the WordPress table prefix.
		$this->database->query( "DROP TABLE IF EXISTS {$entries_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use only the WordPress table prefix.
		$this->database->query( "DROP TABLE IF EXISTS {$audit_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use only the WordPress table prefix.
		$this->database->query( "DROP TABLE IF EXISTS {$attempts_table}" );
		delete_option( 'wp_buyer_blacklist_database_version' );
	}
}

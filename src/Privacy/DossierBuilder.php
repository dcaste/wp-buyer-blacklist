<?php
/**
 * Identity dossier assembly.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Privacy;

use WP_Buyer_Blacklist\Enforcement\BlockedAttemptLog;
use WP_Buyer_Blacklist\Entries\AuditLog;
use WP_Buyer_Blacklist\Entries\EmailEntry;
use WP_Buyer_Blacklist\Storage\Schema;

/**
 * Gathers an entry with its Blocked attempts and Audit events.
 */
final class DossierBuilder {

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
	 * Audit events of each entry.
	 *
	 * @var AuditLog
	 */
	private AuditLog $audit_log;

	/**
	 * Blocked attempts of each entry.
	 *
	 * @var BlockedAttemptLog
	 */
	private BlockedAttemptLog $blocked_attempts;

	/**
	 * Creates the dossier builder.
	 *
	 * @param \wpdb             $database         WordPress database connection.
	 * @param AuditLog          $audit_log        Audit events of each entry.
	 * @param BlockedAttemptLog $blocked_attempts Blocked attempts of each entry.
	 */
	public function __construct( \wpdb $database, AuditLog $audit_log, BlockedAttemptLog $blocked_attempts ) {
		$this->database         = $database;
		$this->table_name       = $database->prefix . Schema::ENTRIES_TABLE;
		$this->audit_log        = $audit_log;
		$this->blocked_attempts = $blocked_attempts;
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
			'blocked_attempts' => $this->blocked_attempts->blocked_attempts( $entry_id ),
			'audit_events'     => $this->audit_log->audit_events( $entry_id ),
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
		return $this->audit_log->deletion_events();
	}
}

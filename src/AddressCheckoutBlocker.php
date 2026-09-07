<?php
/**
 * Decides whether an address blocks checkout.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

/**
 * Checks Buyer addresses against active Blacklist entries.
 */
final class AddressCheckoutBlocker {

	/**
	 * Address entry storage.
	 *
	 * @var ActiveAddressEntryFinder&BlockedAttemptRecorder
	 */
	private ActiveAddressEntryFinder&BlockedAttemptRecorder $entries;

	/**
	 * Creates the address checkout blocker.
	 *
	 * @param ActiveAddressEntryFinder&BlockedAttemptRecorder $entries Address entry storage.
	 */
	public function __construct( ActiveAddressEntryFinder&BlockedAttemptRecorder $entries ) {
		$this->entries = $entries;
	}

	/**
	 * Checks and records matching complete addresses.
	 *
	 * @param string                $match_type Address Match type.
	 * @param array<string, string> $fields     Buyer address fields.
	 * @param array<string, string> $countries  WooCommerce countries.
	 * @return bool Whether checkout must be blocked.
	 */
	public function blocks( string $match_type, array $fields, array $countries ): bool {
		try {
			$entry = AddressEntry::from_fields( $match_type, $fields, $countries );
		} catch ( \InvalidArgumentException $exception ) {
			return false;
		}

		$entry_ids = $this->entries->matching_active_address_entry_ids( $entry );
		foreach ( $entry_ids as $entry_id ) {
			$this->entries->record_blocked_attempt( $entry_id, wp_json_encode( $entry->canonical_value() ) );
		}

		return array() !== $entry_ids;
	}
}

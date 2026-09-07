<?php
/**
 * Finds active address Blacklist entries.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

/**
 * Checks whether a Buyer address has an active Blacklist entry.
 */
interface ActiveAddressEntryFinder {

	/**
	 * Finds every active entry matching an address.
	 *
	 * @param AddressEntry $entry Address identity.
	 * @return list<int> Matching entry identifiers.
	 */
	public function matching_active_address_entry_ids( AddressEntry $entry ): array;
}

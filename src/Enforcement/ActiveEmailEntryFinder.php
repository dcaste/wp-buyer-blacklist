<?php
/**
 * Finds active email Blacklist entries.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Enforcement;

/**
 * Checks whether a Buyer email has an active Blacklist entry.
 */
interface ActiveEmailEntryFinder {

	/**
	 * Finds every active Blacklist entry matching a Buyer email.
	 *
	 * @param string $email Buyer email address.
	 * @return list<int> Matching entry identifiers.
	 */
	public function matching_active_email_entry_ids( string $email ): array;
}

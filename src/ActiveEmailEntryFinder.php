<?php
/**
 * Finds active email Blacklist entries.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

/**
 * Checks whether a Buyer email has an active Blacklist entry.
 */
interface ActiveEmailEntryFinder {

	/**
	 * Checks whether an active email entry matches a Buyer email.
	 *
	 * @param string $email Buyer email address.
	 * @return bool Whether an active entry matches.
	 */
	public function has_active_email( string $email ): bool;
}

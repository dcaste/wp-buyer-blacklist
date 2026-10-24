<?php
/**
 * Finds active customer-ID Blacklist entries.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Enforcement;

/**
 * Checks whether a WooCommerce customer ID has an active Blacklist entry.
 */
interface ActiveCustomerIdEntryFinder {

	/**
	 * Finds every active Blacklist entry matching a WooCommerce customer ID.
	 *
	 * @param int $customer_id WooCommerce customer ID.
	 * @return list<int> Matching entry identifiers.
	 */
	public function matching_active_customer_id_entry_ids( int $customer_id ): array;
}

<?php
/**
 * Decides whether a customer ID blocks checkout.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Enforcement;

/**
 * Checks authenticated WooCommerce customer IDs against active Blacklist entries.
 */
final class CustomerIdCheckoutBlocker {

	/**
	 * Active customer-ID entry lookup.
	 *
	 * @var ActiveCustomerIdEntryFinder
	 */
	private ActiveCustomerIdEntryFinder $entries;

	/**
	 * Blocked attempt persistence.
	 *
	 * @var BlockedAttemptRecorder
	 */
	private BlockedAttemptRecorder $attempts;

	/**
	 * Creates the customer-ID checkout blocker.
	 *
	 * @param ActiveCustomerIdEntryFinder $entries  Active customer-ID entry lookup.
	 * @param BlockedAttemptRecorder      $attempts Blocked attempt persistence.
	 */
	public function __construct( ActiveCustomerIdEntryFinder $entries, BlockedAttemptRecorder $attempts ) {
		$this->entries  = $entries;
		$this->attempts = $attempts;
	}

	/**
	 * Checks and records a matching customer ID.
	 *
	 * @param int $customer_id WooCommerce customer ID, or 0 for a guest.
	 * @return bool Whether checkout must be blocked.
	 */
	public function blocks( int $customer_id ): bool {
		if ( $customer_id <= 0 ) {
			return false;
		}

		$entry_ids = $this->entries->matching_active_customer_id_entry_ids( $customer_id );
		foreach ( $entry_ids as $entry_id ) {
			$this->attempts->record_blocked_attempt( $entry_id, (string) $customer_id );
		}

		return array() !== $entry_ids;
	}
}

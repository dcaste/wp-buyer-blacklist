<?php
/**
 * Decides whether an email blocks checkout.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

/**
 * Checks Buyer emails against active Blacklist entries.
 */
final class EmailCheckoutBlocker {

	/**
	 * Active email entry lookup.
	 *
	 * @var ActiveEmailEntryFinder
	 */
	private ActiveEmailEntryFinder $entries;

	/**
	 * Creates the checkout blocker.
	 *
	 * @param ActiveEmailEntryFinder $entries Active email entry lookup.
	 */
	public function __construct( ActiveEmailEntryFinder $entries ) {
		$this->entries = $entries;
	}

	/**
	 * Checks whether an email belongs to an active Blacklist entry.
	 *
	 * @param string $email Buyer email address.
	 * @return bool Whether checkout must be blocked.
	 */
	public function blocks( string $email ): bool {
		return $this->entries->has_active_email( $email );
	}
}

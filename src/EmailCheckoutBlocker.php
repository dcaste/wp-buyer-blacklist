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
	 * Blocked attempt persistence.
	 *
	 * @var BlockedAttemptRecorder
	 */
	private BlockedAttemptRecorder $attempts;

	/**
	 * Creates the checkout blocker.
	 *
	 * @param ActiveEmailEntryFinder $entries  Active email entry lookup.
	 * @param BlockedAttemptRecorder $attempts Blocked attempt persistence.
	 */
	public function __construct( ActiveEmailEntryFinder $entries, BlockedAttemptRecorder $attempts ) {
		$this->entries  = $entries;
		$this->attempts = $attempts;
	}

	/**
	 * Checks whether an email belongs to an active Blacklist entry.
	 *
	 * @param string $email Buyer email address.
	 * @return bool Whether checkout must be blocked.
	 */
	public function blocks( string $email ): bool {
		$entry_ids = $this->entries->matching_active_email_entry_ids( $email );

		foreach ( $entry_ids as $entry_id ) {
			$this->attempts->record_blocked_attempt( $entry_id, $email );
		}

		return array() !== $entry_ids;
	}
}

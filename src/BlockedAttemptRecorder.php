<?php
/**
 * Blocked attempt persistence boundary.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

/**
 * Records attempts stopped by a Blacklist entry.
 */
interface BlockedAttemptRecorder {

	/**
	 * Records a Blocked attempt for an entry.
	 *
	 * @param int    $entry_id       Matching Blacklist entry identifier.
	 * @param string $identity_value Evaluated Buyer identity value.
	 * @return bool Whether the attempt was recorded.
	 */
	public function record_blocked_attempt( int $entry_id, string $identity_value ): bool;
}

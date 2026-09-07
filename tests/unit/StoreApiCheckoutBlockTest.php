<?php
/**
 * Tests Store API checkout blocking.
 *
 * @package WP_Buyer_Blacklist
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Buyer_Blacklist\ActiveEmailEntryFinder;
use WP_Buyer_Blacklist\EmailCheckoutBlocker;

/**
 * Covers the active email block decision.
 */
#[CoversClass( EmailCheckoutBlocker::class )]
class StoreApiCheckoutBlockTest extends TestCase {

	/**
	 * Blocks an email found in an active Blacklist entry.
	 *
	 * @return void
	 */
	public function test_blocks_an_email_found_in_an_active_blacklist_entry(): void {
		$entries = new class() implements ActiveEmailEntryFinder {
			/**
			 * Checks the active email used by this test.
			 *
			 * @param string $email Buyer email address.
			 * @return bool Whether the entry is active.
			 */
			public function has_active_email( string $email ): bool {
				return 'buyer@example.com' === $email;
			}
		};

		$blocker = new EmailCheckoutBlocker( $entries );

		$this->assertTrue( $blocker->blocks( 'buyer@example.com' ) );
	}
}

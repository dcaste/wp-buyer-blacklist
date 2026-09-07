<?php
/**
 * Tests Store API checkout blocking.
 *
 * @package WP_Buyer_Blacklist
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Buyer_Blacklist\ActiveEmailEntryFinder;
use WP_Buyer_Blacklist\BlockedAttemptRecorder;
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
		$entries  = new class() implements ActiveEmailEntryFinder {
			/**
			 * Finds matching entry identifiers for the test Buyer.
			 *
			 * @param string $email Buyer email address.
			 * @return list<int> Matching entry identifiers.
			 */
			public function matching_active_email_entry_ids( string $email ): array {
				return 'buyer@example.com' === $email ? array( 4, 8 ) : array();
			}
		};
		$attempts = new class() implements BlockedAttemptRecorder {
			/**
			 * Recorded attempts.
			 *
			 * @var list<array{entry_id: int, identity_value: string}>
			 */
			public array $recorded = array();

			/**
			 * Records a Blocked attempt for the test.
			 *
			 * @param int    $entry_id       Matching Blacklist entry identifier.
			 * @param string $identity_value Evaluated Buyer identity value.
			 * @return bool Whether the attempt was recorded.
			 */
			public function record_blocked_attempt( int $entry_id, string $identity_value ): bool {
				$this->recorded[] = compact( 'entry_id', 'identity_value' );
				return true;
			}
		};

		$blocker = new EmailCheckoutBlocker( $entries, $attempts );

		$this->assertTrue( $blocker->blocks( 'buyer@example.com' ) );
		$this->assertTrue( $blocker->blocks( 'buyer@example.com' ) );
		$this->assertSame(
			array(
				array(
					'entry_id'       => 4,
					'identity_value' => 'buyer@example.com',
				),
				array(
					'entry_id'       => 8,
					'identity_value' => 'buyer@example.com',
				),
				array(
					'entry_id'       => 4,
					'identity_value' => 'buyer@example.com',
				),
				array(
					'entry_id'       => 8,
					'identity_value' => 'buyer@example.com',
				),
			),
			$attempts->recorded
		);
	}
}

<?php
/**
 * Tests customer-ID checkout blocking.
 *
 * @package WP_Buyer_Blacklist
 */

use PHPUnit\Framework\TestCase;
use WP_Buyer_Blacklist\Enforcement\ActiveCustomerIdEntryFinder;
use WP_Buyer_Blacklist\Enforcement\BlockedAttemptRecorder;
use WP_Buyer_Blacklist\Enforcement\CustomerIdCheckoutBlocker;

/**
 * Covers the active customer-ID block decision.
 */
class CustomerIdCheckoutBlockerTest extends TestCase {

	/**
	 * Blocks a customer ID found in an active entry and records the attempt.
	 *
	 * @return void
	 */
	public function test_blocks_a_matching_customer_id(): void {
		$entries = $this->entries( array( 7 => array( 3 ) ) );

		$this->assertTrue( ( new CustomerIdCheckoutBlocker( $entries ) )->blocks( 7 ) );
		$this->assertSame(
			array(
				array(
					'entry_id'       => 3,
					'identity_value' => '7',
				),
			),
			$entries->recorded
		);
	}

	/**
	 * Lets through a customer ID with no active entry.
	 *
	 * @return void
	 */
	public function test_allows_an_unlisted_customer_id(): void {
		$entries = $this->entries( array( 7 => array( 3 ) ) );

		$this->assertFalse( ( new CustomerIdCheckoutBlocker( $entries ) )->blocks( 8 ) );
		$this->assertSame( array(), $entries->recorded );
	}

	/**
	 * Does not look up guests.
	 *
	 * @return void
	 */
	public function test_allows_a_guest(): void {
		$entries = $this->entries( array( 0 => array( 3 ) ) );

		$this->assertFalse( ( new CustomerIdCheckoutBlocker( $entries ) )->blocks( 0 ) );
		$this->assertSame( array(), $entries->recorded );
	}

	/**
	 * Creates entry storage that records Blocked attempts.
	 *
	 * @param array<int, list<int>> $matches Entry identifiers keyed by customer ID.
	 * @return ActiveCustomerIdEntryFinder&BlockedAttemptRecorder
	 */
	private function entries( array $matches ): ActiveCustomerIdEntryFinder&BlockedAttemptRecorder {
		return new class( $matches ) implements ActiveCustomerIdEntryFinder, BlockedAttemptRecorder {
			/**
			 * Recorded attempts.
			 *
			 * @var list<array{entry_id: int, identity_value: string}>
			 */
			public array $recorded = array();

			/**
			 * Creates the test storage.
			 *
			 * @param array<int, list<int>> $matches Entry identifiers keyed by customer ID.
			 */
			public function __construct( private array $matches ) {}

			/**
			 * Finds matching entry identifiers.
			 *
			 * @param int $customer_id WooCommerce customer ID.
			 * @return list<int>
			 */
			public function matching_active_customer_id_entry_ids( int $customer_id ): array {
				return $this->matches[ $customer_id ] ?? array();
			}

			/**
			 * Records a Blocked attempt.
			 *
			 * @param int    $entry_id       Matching entry identifier.
			 * @param string $identity_value Evaluated Buyer identity value.
			 * @return bool Whether the attempt was recorded.
			 */
			public function record_blocked_attempt( int $entry_id, string $identity_value ): bool {
				$this->recorded[] = compact( 'entry_id', 'identity_value' );
				return true;
			}
		};
	}
}

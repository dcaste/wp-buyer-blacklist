<?php
/**
 * Tests address checkout blocking.
 *
 * @package WP_Buyer_Blacklist
 */

use PHPUnit\Framework\TestCase;
use WP_Buyer_Blacklist\Enforcement\ActiveAddressEntryFinder;
use WP_Buyer_Blacklist\Enforcement\AddressCheckoutBlocker;
use WP_Buyer_Blacklist\Enforcement\BlockedAttemptRecorder;

/**
 * Covers the active address block decision.
 */
class AddressCheckoutBlockerTest extends TestCase {

	/**
	 * Blocks a matching complete address and records the attempt.
	 *
	 * @return void
	 */
	public function test_blocks_a_matching_complete_address(): void {
		$entries = new class() implements ActiveAddressEntryFinder, BlockedAttemptRecorder {
			/**
			 * Recorded attempts.
			 *
			 * @var list<array{entry_id: int, identity_value: string}>
			 */
			public array $recorded = array();

			/**
			 * Finds matching address entry identifiers.
			 *
			 * @param \WP_Buyer_Blacklist\AddressEntry $entry Address to find.
			 * @return list<int>
			 */
			public function matching_active_address_entry_ids( \WP_Buyer_Blacklist\AddressEntry $entry ): array {
				return array( 4 );
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

		$blocker = new AddressCheckoutBlocker( $entries );

		$this->assertTrue(
			$blocker->blocks(
				'billing_address',
				array(
					'address_line_1' => '1 Test Street',
					'city'           => 'Testville',
					'state'          => 'CA',
					'postcode'       => '90210',
					'country'        => 'US',
				),
				array( 'US' => 'United States' )
			)
		);
		$this->assertSame(
			array(
				array(
					'entry_id'       => 4,
					'identity_value' => wp_json_encode(
						array(
							'address_line_1' => '1 test street',
							'address_line_2' => '',
							'city'           => 'testville',
							'state'          => 'ca',
							'postcode'       => '90210',
							'country'        => 'US',
						)
					),
				),
			),
			$entries->recorded
		);
	}

	/**
	 * Ignores incomplete addresses without looking them up or recording them.
	 *
	 * @return void
	 */
	public function test_ignores_an_incomplete_address(): void {
		$entries = new class() implements ActiveAddressEntryFinder, BlockedAttemptRecorder {
			/**
			 * Address lookup count.
			 *
			 * @var int
			 */
			public int $lookups = 0;

			/**
			 * Blocked-attempt record count.
			 *
			 * @var int
			 */
			public int $recorded = 0;

			/**
			 * Finds matching address entry identifiers.
			 *
			 * @param \WP_Buyer_Blacklist\AddressEntry $entry Address to find.
			 * @return list<int>
			 */
			public function matching_active_address_entry_ids( \WP_Buyer_Blacklist\AddressEntry $entry ): array {
				++$this->lookups;
				return array();
			}

			/**
			 * Records a Blocked attempt.
			 *
			 * @param int    $entry_id       Matching entry identifier.
			 * @param string $identity_value Evaluated Buyer identity value.
			 * @return bool Whether the attempt was recorded.
			 */
			public function record_blocked_attempt( int $entry_id, string $identity_value ): bool {
				++$this->recorded;
				return true;
			}
		};

		$blocker = new AddressCheckoutBlocker( $entries );

		$this->assertFalse(
			$blocker->blocks(
				'shipping_address',
				array(
					'address_line_1' => '1 Test Street',
					'city'           => 'Testville',
					'state'          => 'CA',
					'country'        => 'US',
				),
				array( 'US' => 'United States' )
			)
		);
		$this->assertSame( 0, $entries->lookups );
		$this->assertSame( 0, $entries->recorded );
	}
}

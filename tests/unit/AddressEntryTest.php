<?php
/**
 * Tests address Blacklist entry identities.
 *
 * @package WP_Buyer_Blacklist
 */

use PHPUnit\Framework\TestCase;
use WP_Buyer_Blacklist\AddressEntry;

/**
 * Covers address validation and canonicalization.
 */
class AddressEntryTest extends TestCase {

	/**
	 * Canonicalizes complete address fields without inferring equivalence.
	 *
	 * @return void
	 */
	public function test_canonicalizes_complete_address_fields(): void {
		$entry = AddressEntry::from_fields(
			'billing_address',
			array(
				'address_line_1' => '  1   Test Street ',
				'address_line_2' => ' Apt  2 ',
				'city'           => ' Testville ',
				'state'          => ' ca ',
				'postcode'       => ' 90210 ',
				'country'        => 'us',
			),
			array( 'US' => 'United States' )
		);

		$this->assertSame(
			array(
				'address_line_1' => '1 test street',
				'address_line_2' => 'apt 2',
				'city'           => 'testville',
				'state'          => 'ca',
				'postcode'       => '90210',
				'country'        => 'US',
			),
			$entry->canonical_value()
		);
	}

	/**
	 * Rejects incomplete addresses and unrecognized countries.
	 *
	 * @return void
	 */
	public function test_rejects_incomplete_addresses_and_unrecognized_countries(): void {
		$this->expectException( InvalidArgumentException::class );

		AddressEntry::from_fields(
			'shipping_address',
			array(
				'address_line_1' => '1 Test Street',
				'city'           => 'Testville',
				'state'          => 'CA',
				'postcode'       => '',
				'country'        => 'ZZ',
			),
			array( 'US' => 'United States' )
		);
	}
}

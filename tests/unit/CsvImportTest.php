<?php
/**
 * Tests CSV Blacklist import validation.
 *
 * @package WP_Buyer_Blacklist
 */

use PHPUnit\Framework\TestCase;
use WP_Buyer_Blacklist\CsvImport;

/**
 * Covers the supported CSV import format.
 */
class CsvImportTest extends TestCase {

	/**
	 * Validates a BOM-prefixed CSV, ignores empty rows, and identifies duplicate rows.
	 *
	 * @return void
	 */
	public function test_validates_supported_rows_before_import(): void {
		$result = CsvImport::validate(
			"\xEF\xBB\xBFreason,email,match_type,customer_id,address_line_1,address_line_2,city,state,postcode,country\r\nFraud, Buyer@Example.com ,email,,,,,,,\r\n\r\n,,customer_id,42,,,,,,\r\n,,billing_address,,1 Test Street,Apt 2,Testville,CA,90210,US\r\nAgain,buyer@example.com,email,,,,,,,\r\n",
			array( 'US' => 'United States' )
		);

		$this->assertCount( 3, $result['accepted'] );
		$this->assertSame( 'buyer@example.com', $result['accepted'][0]['value'] );
		$this->assertSame( 'Imported blacklist entry', $result['accepted'][1]['reason'] );
		$this->assertSame( 'billing_address', $result['accepted'][2]['match_type'] );
		$this->assertSame(
			array(
				array(
					'row'    => 6,
					'reason' => 'Duplicate entry.',
				),
			),
			$result['duplicates']
		);
		$this->assertSame( array(), $result['rejected'] );
	}

	/**
	 * Rejects invalid rows without preventing valid rows from being reviewed.
	 *
	 * @return void
	 */
	public function test_reports_invalid_rows(): void {
		$result = CsvImport::validate(
			"match_type,email,reason\nemail,not an email,Test\ncustomer_id,,Test\n",
			array()
		);

		$this->assertSame( array(), $result['accepted'] );
		$this->assertSame(
			array(
				array(
					'row'    => 2,
					'reason' => 'Enter a valid email address.',
				),
				array(
					'row'    => 3,
					'reason' => 'Enter a positive Customer ID.',
				),
			),
			$result['rejected']
		);
	}

	/**
	 * Preserves an explicit status and defaults a missing status to active.
	 *
	 * @return void
	 */
	public function test_validates_import_status(): void {
		$result = CsvImport::validate(
			"match_type,email,status\nemail,active@example.com,active\nemail,inactive@example.com,inactive\nemail,default@example.com,\nemail,invalid@example.com,disabled\n",
			array()
		);

		$this->assertSame( true, $result['accepted'][0]['is_active'] );
		$this->assertSame( false, $result['accepted'][1]['is_active'] );
		$this->assertSame( true, $result['accepted'][2]['is_active'] );
		$this->assertSame(
			array(
				array(
					'row'    => 5,
					'reason' => 'Choose a valid status.',
				),
			),
			$result['rejected']
		);
	}
}

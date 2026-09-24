<?php
/**
 * Tests the enrollment helpers that need no WooCommerce data.
 *
 * @package WP_Buyer_Blacklist
 */

use PHPUnit\Framework\TestCase;
use WP_Buyer_Blacklist\EmailEntryRepository;
use WP_Buyer_Blacklist\Enrollment\Enroller;

/**
 * Covers address formatting and email validation for enrollment.
 */
class EnrollerTest extends TestCase {

	/**
	 * Enroller under test.
	 *
	 * @var Enroller
	 */
	private Enroller $enroller;

	/**
	 * Creates the enroller.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->enroller = new Enroller( new EmailEntryRepository( $GLOBALS['wpdb'] ) );
	}

	/**
	 * Joins the filled address fields with commas.
	 *
	 * @return void
	 */
	public function test_formats_an_address_without_empty_fields(): void {
		$this->assertSame(
			'1 Test Street, Testville, CA, 90210, US',
			$this->enroller->format_address(
				array(
					'address_line_1' => '1 Test Street',
					'address_line_2' => '',
					'city'           => 'Testville',
					'state'          => 'CA',
					'postcode'       => '90210',
					'country'        => 'US',
				)
			)
		);
	}

	/**
	 * Formats an address with no filled fields as an empty string.
	 *
	 * @return void
	 */
	public function test_formats_an_empty_address_as_an_empty_string(): void {
		$this->assertSame( '', $this->enroller->format_address( array( 'address_line_1' => '' ) ) );
	}

	/**
	 * Accepts an email that can create an entry.
	 *
	 * @return void
	 */
	public function test_accepts_a_valid_email(): void {
		$this->assertTrue( $this->enroller->is_valid_email( ' Buyer@Example.com ' ) );
	}

	/**
	 * Rejects emails that cannot create an entry.
	 *
	 * @dataProvider invalid_emails
	 *
	 * @param string $email Candidate email.
	 * @return void
	 */
	public function test_rejects_an_invalid_email( string $email ): void {
		$this->assertFalse( $this->enroller->is_valid_email( $email ) );
	}

	/**
	 * Provides emails that cannot create an entry.
	 *
	 * @return array<string, array{string}>
	 */
	public function invalid_emails(): array {
		return array(
			'empty'        => array( '' ),
			'missing at'   => array( 'buyer.example.com' ),
			'missing user' => array( '@example.com' ),
		);
	}
}

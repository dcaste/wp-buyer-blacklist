<?php
/**
 * Tests email Blacklist entries.
 *
 * @package WP_Buyer_Blacklist
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Buyer_Blacklist\EmailEntry;

/**
 * Covers email entry creation.
 */
#[CoversClass( EmailEntry::class )]
class EmailEntryTest extends TestCase {

	/**
	 * Creates a canonical email identity.
	 *
	 * @return void
	 */
	public function test_creates_a_canonical_email_identity(): void {
		$entry = EmailEntry::from_email( ' Buyer@Example.COM ' );

		$this->assertSame( 'buyer@example.com', $entry->canonical_value() );
		$this->assertSame( hash( 'sha256', 'buyer@example.com' ), $entry->canonical_hash() );
	}

	/**
	 * Rejects invalid emails before they become Blacklist entries.
	 *
	 * @return void
	 */
	public function test_rejects_an_invalid_email(): void {
		$this->expectException( InvalidArgumentException::class );

		EmailEntry::from_email( 'not-an-email' );
	}
}

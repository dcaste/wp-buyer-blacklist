<?php
/**
 * Tests email canonicalization.
 *
 * @package WP_Buyer_Blacklist
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_Buyer_Blacklist\EmailCanonicalizer;

/**
 * Covers the email Canonical value.
 */
#[CoversClass( EmailCanonicalizer::class )]
class EmailCanonicalizerTest extends TestCase {

	/**
	 * Ignores case and surrounding whitespace.
	 *
	 * @return void
	 */
	public function test_canonicalizes_case_and_surrounding_whitespace(): void {
		$this->assertSame( 'buyer@example.com', EmailCanonicalizer::canonicalize( ' Buyer@Example.COM ' ) );
	}
}

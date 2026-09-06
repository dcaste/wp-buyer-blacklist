<?php
/**
 * Verifies that the PHP unit-test runner is available.
 *
 * @package WP_Buyer_Blacklist
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Verifies the PHP unit-test runner.
 */
#[CoversNothing]
class ToolchainTest extends TestCase {

	/**
	 * Confirms PHPUnit can execute a test.
	 *
	 * @return void
	 */
	public function test_phpunit_is_running(): void {
		$this->assertTrue( true );
	}
}

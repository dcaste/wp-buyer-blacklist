<?php
/**
 * Verifies that the PHP unit-test runner is available.
 *
 * @package WP_Buyer_Blacklist
 */

use PHPUnit\Framework\TestCase;

class ToolchainTest extends TestCase {

	public function test_phpunit_is_running(): void {
		$this->assertTrue( true );
	}
}

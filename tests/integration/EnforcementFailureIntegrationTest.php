<?php
/**
 * Tests checkout enforcement when the entry lookup fails.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Enforcement\BlockedAttemptLog;
use WP_Buyer_Blacklist\Entries\AuditLog;
use WP_Buyer_Blacklist\Entries\EmailEntry;
use WP_Buyer_Blacklist\Entries\EntryRepository;
use WP_Buyer_Blacklist\Storage\Schema;

/**
 * Covers the fail-open rule from ADR 0004: a failing lookup lets checkout proceed.
 */
class EnforcementFailureIntegrationTest extends WP_UnitTestCase {

	/**
	 * Number of entry lookups this test made fail.
	 *
	 * @var int
	 */
	private int $failed_lookups = 0;

	/**
	 * Whether wpdb printed errors before the test.
	 *
	 * @var bool
	 */
	private bool $suppressed_errors;

	/**
	 * Stores an active entry for the Buyer, then breaks every active-entry lookup.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$entries = new EntryRepository( $GLOBALS['wpdb'], new AuditLog( $GLOBALS['wpdb'] ), new BlockedAttemptLog( $GLOBALS['wpdb'] ) );
		$entries->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );

		$this->suppressed_errors = $GLOBALS['wpdb']->suppress_errors( true );
		add_filter( 'query', array( $this, 'break_entry_lookup' ) );
	}

	/**
	 * Restores normal queries.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'query', array( $this, 'break_entry_lookup' ) );
		$GLOBALS['wpdb']->suppress_errors( $this->suppressed_errors );
		parent::tear_down();
	}

	/**
	 * Points active-entry lookups at a table that does not exist, so the database returns an error.
	 *
	 * @param string $query SQL about to run.
	 * @return string SQL to run instead.
	 */
	public function break_entry_lookup( string $query ): string {
		$table = $GLOBALS['wpdb']->prefix . Schema::ENTRIES_TABLE;
		if ( str_starts_with( ltrim( $query ), 'SELECT id FROM ' . $table ) && str_contains( $query, 'is_active' ) ) {
			++$this->failed_lookups;

			return str_replace( $table, $table . '_missing', $query );
		}

		return $query;
	}

	/**
	 * Confirms that classic checkout proceeds when the lookup fails.
	 *
	 * @return void
	 */
	public function test_classic_checkout_proceeds_when_the_lookup_fails(): void {
		$errors = new WP_Error();

		do_action( 'woocommerce_after_checkout_validation', array( 'billing_email' => 'buyer@example.com' ), $errors );

		$this->assertGreaterThan( 0, $this->failed_lookups );
		$this->assertFalse( $errors->has_errors() );
	}

	/**
	 * Confirms that Store API checkout proceeds when the lookup fails.
	 *
	 * @return void
	 */
	public function test_store_api_checkout_proceeds_when_the_lookup_fails(): void {
		$order = new WC_Order();
		$order->set_billing_email( 'buyer@example.com' );
		$errors = new WP_Error();

		do_action( 'woocommerce_checkout_validate_order_before_payment', $order, $errors );

		$this->assertGreaterThan( 0, $this->failed_lookups );
		$this->assertFalse( $errors->has_errors() );
	}
}

<?php
/**
 * Tests the WordPress personal-data exporter and eraser.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Enforcement\BlockedAttemptLog;
use WP_Buyer_Blacklist\Entries\AuditLog;
use WP_Buyer_Blacklist\Entries\EmailEntry;
use WP_Buyer_Blacklist\Entries\EntryRepository;

/**
 * Covers the privacy tools registered through core filters.
 */
class PrivacyToolsIntegrationTest extends WP_UnitTestCase {

	/**
	 * Registers the Buyer Blacklist exporter and exports an email identity.
	 *
	 * @return void
	 */
	public function test_exports_personal_data_for_an_email(): void {
		$entries = new EntryRepository( $GLOBALS['wpdb'], new AuditLog( $GLOBALS['wpdb'] ), new BlockedAttemptLog( $GLOBALS['wpdb'] ) );
		$this->assertTrue( $entries->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' ) );
		$entry_id = (int) $entries->all()[0]['id'];
		do_action( 'woocommerce_after_checkout_validation', array( 'billing_email' => 'buyer@example.com' ), new WP_Error() );

		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$this->assertArrayHasKey( 'wp-buyer-blacklist', $exporters );
		$this->assertSame( 'Buyer Blacklist', $exporters['wp-buyer-blacklist']['exporter_friendly_name'] );

		$export = call_user_func( $exporters['wp-buyer-blacklist']['callback'], 'buyer@example.com', 1 );

		$this->assertTrue( $export['done'] );
		$this->assertCount( 1, $export['data'] );
		$this->assertSame( 'wp-buyer-blacklist', $export['data'][0]['group_id'] );
		$this->assertSame( 'Buyer Blacklist', $export['data'][0]['group_label'] );
		$this->assertSame( 'entry-' . $entry_id, $export['data'][0]['item_id'] );
		$this->assertSame( array( 'Email', 'Reason', 'Blocked attempts' ), wp_list_pluck( $export['data'][0]['data'], 'name' ) );
		$this->assertSame( 'buyer@example.com', $export['data'][0]['data'][0]['value'] );
		$this->assertSame( 'Fraud', $export['data'][0]['data'][1]['value'] );
		$this->assertStringStartsWith( 'blocked at ', $export['data'][0]['data'][2]['value'] );
	}

	/**
	 * Exports nothing for an email without entries.
	 *
	 * @return void
	 */
	public function test_exports_nothing_for_an_unknown_email(): void {
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );

		$this->assertSame(
			array(
				'data' => array(),
				'done' => true,
			),
			call_user_func( $exporters['wp-buyer-blacklist']['callback'], 'nobody@example.com', 1 )
		);
	}

	/**
	 * Registers the Buyer Blacklist eraser and erases an email identity.
	 *
	 * @return void
	 */
	public function test_erases_personal_data_for_an_email(): void {
		$entries = new EntryRepository( $GLOBALS['wpdb'], new AuditLog( $GLOBALS['wpdb'] ), new BlockedAttemptLog( $GLOBALS['wpdb'] ) );
		$this->assertTrue( $entries->create( EmailEntry::from_email( 'buyer@example.com' ), 'Fraud' ) );
		$this->assertTrue( $entries->create( EmailEntry::from_email( 'other@example.com' ), 'Fraud' ) );

		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		$this->assertArrayHasKey( 'wp-buyer-blacklist', $erasers );
		$this->assertSame( 'Buyer Blacklist', $erasers['wp-buyer-blacklist']['eraser_friendly_name'] );

		$erased = call_user_func( $erasers['wp-buyer-blacklist']['callback'], 'buyer@example.com', 1 );

		$this->assertSame(
			array(
				'items_removed'  => true,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			),
			$erased
		);
		$this->assertSame( array( 'other@example.com' ), wp_list_pluck( $entries->all(), 'value' ) );
		$this->assertFalse( call_user_func( $erasers['wp-buyer-blacklist']['callback'], 'buyer@example.com', 1 )['items_removed'] );
	}
}

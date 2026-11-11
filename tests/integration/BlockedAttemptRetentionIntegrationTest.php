<?php
/**
 * Tests the scheduled purge of expired Blocked attempts.
 *
 * @package WP_Buyer_Blacklist
 */

use WP_Buyer_Blacklist\Enforcement\BlockedAttemptRetention;

/**
 * Covers when the daily purge is scheduled and when it is removed.
 */
class BlockedAttemptRetentionIntegrationTest extends WP_UnitTestCase {

	use AdminInit;

	/**
	 * Starts each test without a scheduled purge.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_clear_scheduled_hook( BlockedAttemptRetention::HOOK );
	}

	/**
	 * Confirms that activation schedules the purge once a day.
	 *
	 * @return void
	 */
	public function test_activation_schedules_a_daily_purge(): void {
		wp_buyer_blacklist_activate();

		$this->assertNotFalse( wp_next_scheduled( BlockedAttemptRetention::HOOK ) );
		$this->assertSame( 'daily', wp_get_schedule( BlockedAttemptRetention::HOOK ) );
	}

	/**
	 * Confirms that wp-admin restores a missing purge, as after an in-place plugin update.
	 *
	 * @return void
	 */
	public function test_admin_init_schedules_a_missing_purge(): void {
		$this->run_admin_init();

		$this->assertSame( 'daily', wp_get_schedule( BlockedAttemptRetention::HOOK ) );
	}

	/**
	 * Confirms that an already scheduled purge is not scheduled again.
	 *
	 * @return void
	 */
	public function test_schedules_the_purge_only_once(): void {
		wp_buyer_blacklist_activate();
		$this->run_admin_init();

		$this->assertCount( 1, $this->scheduled_purges() );
	}

	/**
	 * Confirms that deactivating the plugin removes the purge.
	 *
	 * @return void
	 */
	public function test_deactivation_removes_the_purge(): void {
		wp_buyer_blacklist_activate();

		wp_buyer_blacklist_deactivate();

		$this->assertFalse( wp_next_scheduled( BlockedAttemptRetention::HOOK ) );
	}

	/**
	 * Confirms that a data-deleting uninstall removes the purge.
	 *
	 * @return void
	 */
	public function test_data_deleting_uninstall_removes_the_purge(): void {
		wp_buyer_blacklist_activate();
		update_option( 'wp_buyer_blacklist_delete_data_on_uninstall', true );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-buyer-blacklist/wp-buyer-blacklist.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertFalse( wp_next_scheduled( BlockedAttemptRetention::HOOK ) );
	}

	/**
	 * Lists every scheduled purge event.
	 *
	 * @return array<int, mixed> Scheduled events for the purge hook.
	 */
	private function scheduled_purges(): array {
		$events = array();
		foreach ( _get_cron_array() as $hooks ) {
			if ( isset( $hooks[ BlockedAttemptRetention::HOOK ] ) ) {
				$events = array_merge( $events, array_values( $hooks[ BlockedAttemptRetention::HOOK ] ) );
			}
		}

		return $events;
	}
}

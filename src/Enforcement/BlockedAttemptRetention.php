<?php
/**
 * Scheduled removal of expired Blocked attempts.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Enforcement;

/**
 * Deletes Blocked attempts older than the retention period an Administrator set.
 *
 * A retention period of 0 days keeps Blocked attempts while their entry exists.
 */
final class BlockedAttemptRetention {

	/**
	 * WP-Cron hook that runs the purge.
	 */
	public const HOOK = 'wp_buyer_blacklist_purge_blocked_attempts';

	/**
	 * Option holding the retention period in days.
	 */
	public const OPTION = 'wp_buyer_blacklist_blocked_attempt_retention_days';

	/**
	 * Blocked attempts to purge.
	 *
	 * @var BlockedAttemptLog
	 */
	private BlockedAttemptLog $blocked_attempts;

	/**
	 * Creates the retention job.
	 *
	 * @param BlockedAttemptLog $blocked_attempts Blocked attempts to purge.
	 */
	public function __construct( BlockedAttemptLog $blocked_attempts ) {
		$this->blocked_attempts = $blocked_attempts;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Activation schedules the purge. This restores it after an in-place update, without work on storefront requests.
		add_action( 'admin_init', array( self::class, 'schedule' ) );
		add_action( self::HOOK, array( $this, 'purge' ) );
	}

	/**
	 * Schedules the daily purge unless it is already scheduled.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}
	}

	/**
	 * Removes the scheduled purge.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Turns a submitted retention period into whole days.
	 *
	 * Negative or non-numeric input becomes 0, which keeps every Blocked attempt.
	 * absint() would turn -5 into 5 and start deleting data.
	 *
	 * @param mixed $days Submitted or stored retention period.
	 * @return int Retention period in days.
	 */
	public static function sanitize_days( mixed $days ): int {
		return is_numeric( $days ) ? max( 0, (int) $days ) : 0;
	}

	/**
	 * Deletes Blocked attempts older than the retention period.
	 *
	 * @return void
	 */
	public function purge(): void {
		$days = self::sanitize_days( get_option( self::OPTION, 0 ) );
		if ( 0 === $days ) {
			return;
		}

		$this->blocked_attempts->delete_older_than( gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) );
	}
}

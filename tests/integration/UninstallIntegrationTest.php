<?php
/**
 * Tests plugin data removal on uninstall.
 *
 * @package WP_Buyer_Blacklist
 */

/**
 * Covers the opt-in data removal that uninstall.php runs when WordPress uninstalls the plugin.
 */
class UninstallIntegrationTest extends WP_UnitTestCase {

	/**
	 * DROP TABLE statements sent while a test runs.
	 *
	 * @var list<string>
	 */
	private array $drops = array();

	/**
	 * Records DROP TABLE statements.
	 *
	 * The test library rewrites DROP TABLE to DROP TEMPORARY TABLE, so the real
	 * tables survive and the statements are the only thing to check. This filter
	 * runs first so it sees each statement before that rewrite.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter( 'query', array( $this, 'record_drop' ), 1 );
	}

	/**
	 * Stops recording.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'query', array( $this, 'record_drop' ), 1 );
		parent::tear_down();
	}

	/**
	 * Keeps DROP TABLE statements.
	 *
	 * @param string $query SQL statement.
	 * @return string The same statement.
	 */
	public function record_drop( string $query ): string {
		if ( str_starts_with( ltrim( $query ), 'DROP ' ) ) {
			$this->drops[] = $query;
		}

		return $query;
	}

	/**
	 * Drops every plugin table when the administrator opted in.
	 *
	 * @return void
	 */
	public function test_drops_every_plugin_table_after_opting_in(): void {
		global $wpdb;
		update_option( 'wp_buyer_blacklist_delete_data_on_uninstall', true );

		$this->uninstall();

		foreach ( array( 'entries', 'audit_events', 'blocked_attempts', 'imports' ) as $table ) {
			$this->assertContains( "DROP TABLE IF EXISTS {$wpdb->prefix}buyer_blacklist_{$table}", $this->drops, $table );
		}
		$this->assertFalse( get_option( 'wp_buyer_blacklist_database_version' ) );
		$this->assertFalse( get_option( 'wp_buyer_blacklist_delete_data_on_uninstall' ) );
	}

	/**
	 * Deletes every plugin option when the administrator opted in, including
	 * option names left behind by earlier versions.
	 *
	 * @return void
	 */
	public function test_deletes_every_plugin_option_after_opting_in(): void {
		$options = array(
			'wp_buyer_blacklist_buyer_message',
			'wp_buyer_blacklist_allow_woocommerce_managers',
			'wp_buyer_blacklist_block_message',
		);
		foreach ( $options as $option ) {
			update_option( $option, 'stored' );
		}
		update_option( 'wp_buyer_blacklist_delete_data_on_uninstall', true );

		$this->uninstall();

		foreach ( $options as $option ) {
			$this->assertFalse( get_option( $option ), $option );
		}
	}

	/**
	 * Deletes unconfirmed CSV import reviews when the administrator opted in.
	 *
	 * @return void
	 */
	public function test_deletes_pending_import_transients_after_opting_in(): void {
		set_transient( 'wp_buyer_blacklist_import_1_token', array( 'accepted' => array() ), HOUR_IN_SECONDS );
		update_option( 'wp_buyer_blacklist_delete_data_on_uninstall', true );

		$this->uninstall();
		wp_cache_flush();

		$this->assertFalse( get_transient( 'wp_buyer_blacklist_import_1_token' ) );
	}

	/**
	 * Keeps every option when the administrator did not opt in.
	 *
	 * @return void
	 */
	public function test_keeps_options_without_opting_in(): void {
		update_option( 'wp_buyer_blacklist_buyer_message', 'stored' );

		$this->uninstall();

		$this->assertSame( 'stored', get_option( 'wp_buyer_blacklist_buyer_message' ) );
	}

	/**
	 * Keeps every table when the administrator did not opt in.
	 *
	 * @return void
	 */
	public function test_keeps_data_without_opting_in(): void {
		$this->uninstall();

		$this->assertSame( array(), $this->drops );
	}

	/**
	 * Confirms that uninstall.php does nothing when WordPress did not start an uninstall.
	 *
	 * A separate PHP process loads the file with ABSPATH defined, as if WordPress
	 * were loaded, but without WP_UNINSTALL_PLUGIN. It runs apart because this
	 * process already defines that constant and constants can't be undefined.
	 * Without the guard, the file would call get_option() and fail.
	 *
	 * @return void
	 */
	public function test_does_nothing_outside_a_wordpress_uninstall(): void {
		$output = array();
		$status = null;
		$code   = sprintf( "define( 'ABSPATH', '/' ); include %s;", var_export( $this->uninstall_file(), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Quotes a path for the child process.

		exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Runs the file outside an uninstall to check its guard.

		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$this->assertSame( array(), $output );
	}

	/**
	 * Runs uninstall.php the way WordPress does when an Administrator deletes the plugin.
	 *
	 * @return void
	 */
	private function uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-buyer-blacklist/wp-buyer-blacklist.php' );
		}

		include $this->uninstall_file();
	}

	/**
	 * Path of the plugin's uninstall file.
	 *
	 * @return string Absolute path.
	 */
	private function uninstall_file(): string {
		return dirname( __DIR__, 2 ) . '/uninstall.php';
	}
}

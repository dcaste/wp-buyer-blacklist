<?php
/**
 * Tests plugin data removal on uninstall.
 *
 * @package WP_Buyer_Blacklist
 */

/**
 * Covers the opt-in data removal run by the uninstall hook.
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

		wp_buyer_blacklist_uninstall();

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

		wp_buyer_blacklist_uninstall();

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

		wp_buyer_blacklist_uninstall();
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

		wp_buyer_blacklist_uninstall();

		$this->assertSame( 'stored', get_option( 'wp_buyer_blacklist_buyer_message' ) );
	}

	/**
	 * Keeps every table when the administrator did not opt in.
	 *
	 * @return void
	 */
	public function test_keeps_data_without_opting_in(): void {
		wp_buyer_blacklist_uninstall();

		$this->assertSame( array(), $this->drops );
	}
}

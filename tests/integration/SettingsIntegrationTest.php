<?php
/**
 * Tests Buyer Blacklist settings.
 *
 * @package WP_Buyer_Blacklist
 */

/**
 * Covers settings registration, the reset handler and the Settings page.
 */
class SettingsIntegrationTest extends WP_UnitTestCase {

	use AdminInit;
	use AdminPostRequests;
	use AdminScreens;
	use HtmlSnapshots;

	/**
	 * Default Buyer block message.
	 */
	private const DEFAULT_MESSAGE = 'We’re unable to process your order. Please contact us for assistance.';

	/**
	 * Registers the three settings with their types and defaults.
	 *
	 * @return void
	 */
	public function test_registers_settings_on_admin_init(): void {
		$this->run_admin_init();
		$settings = get_registered_settings();

		foreach ( array(
			'wp_buyer_blacklist_allow_woocommerce_managers' => array( 'boolean', true ),
			'wp_buyer_blacklist_delete_data_on_uninstall' => array( 'boolean', false ),
			'wp_buyer_blacklist_buyer_message'            => array( 'string', self::DEFAULT_MESSAGE ),
		) as $option => $expected ) {
			$this->assertArrayHasKey( $option, $settings );
			$this->assertSame( 'wp_buyer_blacklist', $settings[ $option ]['group'] );
			$this->assertSame( $expected[0], $settings[ $option ]['type'] );
			$this->assertSame( $expected[1], $settings[ $option ]['default'] );
		}
		$this->assertSame( 'rest_sanitize_boolean', $settings['wp_buyer_blacklist_allow_woocommerce_managers']['sanitize_callback'] );
		$this->assertSame( 'rest_sanitize_boolean', $settings['wp_buyer_blacklist_delete_data_on_uninstall']['sanitize_callback'] );
	}

	/**
	 * Sanitizes the Buyer block message and restores the default for blank input.
	 *
	 * @return void
	 */
	public function test_sanitizes_the_buyer_block_message(): void {
		$this->run_admin_init();

		$this->assertSame( self::DEFAULT_MESSAGE, sanitize_option( 'wp_buyer_blacklist_buyer_message', '' ) );
		$this->assertSame( self::DEFAULT_MESSAGE, sanitize_option( 'wp_buyer_blacklist_buyer_message', " \n\t " ) );
		$this->assertSame( 'Call us', sanitize_option( 'wp_buyer_blacklist_buyer_message', '<b>Call us</b>' ) );
	}

	/**
	 * Refuses the settings reset to a WooCommerce operator.
	 *
	 * @return void
	 */
	public function test_reset_handler_refuses_a_woocommerce_operator(): void {
		update_option( 'wp_buyer_blacklist_buyer_message', 'Configured buyer message' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		try {
			$this->submit_admin_post( 'wp_buyer_blacklist_reset_settings', array( '_wpnonce' => wp_create_nonce( 'wp_buyer_blacklist_reset_settings' ) ) );
			$this->fail( 'A WooCommerce operator reset the settings.' );
		} catch ( WPDieException $denied ) {
			$this->assertSame( 403, $denied->getCode() );
		}
		$this->assertSame( 'Configured buyer message', get_option( 'wp_buyer_blacklist_buyer_message' ) );
	}

	/**
	 * Renders the Settings page exactly as recorded.
	 *
	 * @return void
	 */
	public function test_settings_page_matches_its_snapshot(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$markup = $this->render_admin_page(
			'woocommerce_page_wp-buyer-blacklist',
			array(
				'page' => 'wp-buyer-blacklist',
				'tab'  => 'settings',
			)
		);

		$this->assert_matches_html_snapshot(
			'settings-page',
			$markup,
			array(
				wp_create_nonce( 'wp_buyer_blacklist-options' )        => '{settings-nonce}',
				wp_create_nonce( 'wp_buyer_blacklist_reset_settings' ) => '{reset-nonce}',
			)
		);
	}
}

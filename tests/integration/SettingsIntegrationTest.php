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
	 * Starts every test with no queued settings messages.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->start_new_request();
	}

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

	/**
	 * Attaches the message preview script to the admin script instead of printing it in the page.
	 *
	 * @return void
	 */
	public function test_settings_page_adds_the_preview_script_to_the_admin_script(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The test starts from an empty script queue.
		$GLOBALS['wp_scripts'] = null;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'woocommerce_page_wp-buyer-blacklist' );
		do_action( 'admin_enqueue_scripts', 'woocommerce_page_wp-buyer-blacklist' );

		$markup = $this->render_admin_page(
			'woocommerce_page_wp-buyer-blacklist',
			array(
				'page' => 'wp-buyer-blacklist',
				'tab'  => 'settings',
			)
		);

		$this->assertStringNotContainsString( '<script', $markup );
		$this->assertContains(
			"document.getElementById('wp-buyer-blacklist-buyer-message').addEventListener('input', function () { document.getElementById('wp-buyer-blacklist-message-preview').textContent = this.value || 'We’re unable to process your order. Please contact us for assistance.'; });",
			wp_scripts()->get_data( 'wp-buyer-blacklist-admin', 'after' )
		);
	}

	/**
	 * Posts a valid options.php nonce when the Settings form is saved.
	 *
	 * @return void
	 */
	public function test_settings_form_posts_a_valid_options_nonce(): void {
		$fields = $this->form_fields( $this->render_settings_tab(), 'options.php' );

		$this->assertSame( 'wp_buyer_blacklist', $fields['option_page'] );
		$this->assertSame( 'update', $fields['action'] );
		$this->assertSame( 1, wp_verify_nonce( $fields['_wpnonce'], 'wp_buyer_blacklist-options' ) );
	}

	/**
	 * Resets the settings with the fields the Reset form posts.
	 *
	 * @return void
	 */
	public function test_reset_form_posts_what_the_reset_handler_accepts(): void {
		update_option( 'wp_buyer_blacklist_buyer_message', 'Configured buyer message' );
		$fields = $this->form_fields( $this->render_settings_tab(), admin_url( 'admin-post.php' ) );

		$this->assertSame( 'wp_buyer_blacklist_reset_settings', $fields['action'] );
		$this->assertStringContainsString( 'settings-updated=true', $this->submit_admin_post( $fields['action'], $fields ) );
		$this->assertFalse( get_option( 'wp_buyer_blacklist_buyer_message' ) );
	}

	/**
	 * Renders the Settings tab as an Administrator.
	 *
	 * @return string Rendered markup.
	 */
	private function render_settings_tab(): string {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		return $this->render_admin_page(
			'woocommerce_page_wp-buyer-blacklist',
			array(
				'page' => 'wp-buyer-blacklist',
				'tab'  => 'settings',
			)
		);
	}

	/**
	 * Gets the fields a form posts, keeping the last value of a repeated name as PHP does.
	 *
	 * @param string $markup Rendered page.
	 * @param string $action Form action attribute, as rendered.
	 * @return array<string, string> Posted fields.
	 */
	private function form_fields( string $markup, string $action ): array {
		$this->assertSame( 1, preg_match( '/<form[^>]*action="' . preg_quote( $action, '/' ) . '"[^>]*>(.*?)<\/form>/s', $markup, $form ), "No form posts to {$action}." );
		preg_match_all( '/<input[^>]*name=["\']([^"\']+)["\'][^>]*value=["\']([^"\']*)["\']/', $form[1], $inputs, PREG_SET_ORDER );

		$fields = array();
		foreach ( $inputs as $input ) {
			$fields[ $input[1] ] = html_entity_decode( $input[2] );
		}

		return $fields;
	}

	/**
	 * Confirms a reset on the Settings page it redirects to.
	 *
	 * @return void
	 */
	public function test_reset_confirms_on_the_settings_page(): void {
		$fields   = $this->form_fields( $this->render_settings_tab(), admin_url( 'admin-post.php' ) );
		$location = $this->submit_admin_post( $fields['action'], $fields );
		parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
		$this->start_new_request();

		$markup = $this->render_admin_page( 'woocommerce_page_wp-buyer-blacklist', $query );

		$this->assertSame( 1, substr_count( $markup, 'Settings restored to their defaults.' ) );
		$this->assertStringNotContainsString( 'Settings saved.', $markup );
	}

	/**
	 * Confirms a save, even when the page was last reloaded after a reset.
	 *
	 * @return void
	 */
	public function test_save_after_a_reset_confirms_the_save(): void {
		$fields   = $this->form_fields( $this->render_settings_tab(), admin_url( 'admin-post.php' ) );
		$location = $this->submit_admin_post( $fields['action'], $fields );
		parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $after_reset );
		$this->start_new_request();
		$this->render_admin_page( 'woocommerce_page_wp-buyer-blacklist', $after_reset );
		$this->start_new_request();

		// What options.php does after saving: queue its message and return to the page the form came from.
		add_settings_error( 'general', 'settings_updated', 'Settings saved.', 'success' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		$this->start_new_request();
		$markup = $this->render_admin_page( 'woocommerce_page_wp-buyer-blacklist', array_merge( $after_reset, array( 'settings-updated' => 'true' ) ) );

		$this->assertStringContainsString( 'Settings saved.', $markup );
		$this->assertStringNotContainsString( 'Settings restored to their defaults.', $markup );
	}

	/**
	 * Forgets settings messages queued in memory, as a redirect to a new request would.
	 *
	 * @return void
	 */
	private function start_new_request(): void {
		$GLOBALS['wp_settings_errors'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- A new request starts with no queued messages.
	}
}

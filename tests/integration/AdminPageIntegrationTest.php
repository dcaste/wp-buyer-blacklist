<?php
/**
 * Tests the Buyer Blacklist admin page shell and its assets.
 *
 * @package WP_Buyer_Blacklist
 */

/**
 * Covers the admin page rendered for each tab and the asset enqueueing.
 */
class AdminPageIntegrationTest extends WP_UnitTestCase {

	use AdminScreens;

	/**
	 * Starts every test with no queued scripts or styles.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Each test starts from an empty asset queue.
		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Renders the React mount point with its download links on the Manage entries tab.
	 *
	 * @return void
	 */
	public function test_manage_tab_renders_the_admin_app_mount_point(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$markup = $this->render_admin_page( 'woocommerce_page_wp-buyer-blacklist', array( 'page' => 'wp-buyer-blacklist' ) );

		$this->assertStringContainsString( '<h1>Buyer Blacklist</h1>', $markup );
		$this->assertStringContainsString( '<div id="wp-buyer-blacklist-admin" data-tab="manage"', $markup );
		$this->assertStringContainsString( 'data-export-url="http://example.org/wp-admin/admin-post.php?action=wp_buyer_blacklist_export&#038;_wpnonce=' . wp_create_nonce( 'wp_buyer_blacklist_export' ) . '"', $markup );
		$this->assertStringContainsString( 'data-dossier-export-url="http://example.org/wp-admin/admin-post.php?action=wp_buyer_blacklist_dossier_export&#038;_wpnonce=' . wp_create_nonce( 'wp_buyer_blacklist_dossier_export' ) . '"', $markup );
		$this->assertStringContainsString( 'data-example-csv-url="' . esc_url( WP_BUYER_BLACKLIST_URL . 'assets/buyer-blacklist-example.csv' ) . '"', $markup );
		$this->assertStringContainsString( 'data-users-url="http://example.org/wp-admin/users.php"', $markup );
		$this->assertStringContainsString( 'class="nav-tab nav-tab-active" href="http://example.org/wp-admin/admin.php?page=wp-buyer-blacklist"', $markup );
		$this->assertStringContainsString( 'tab=settings', $markup );
		$this->assertStringContainsString( 'data-can-manage-privacy-data="1"', $markup );
	}

	/**
	 * Selects the Import and export tab.
	 *
	 * @return void
	 */
	public function test_import_export_tab_renders_the_admin_app_mount_point(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$markup = $this->render_admin_page(
			'woocommerce_page_wp-buyer-blacklist',
			array(
				'page' => 'wp-buyer-blacklist',
				'tab'  => 'import-export',
			)
		);

		$this->assertStringContainsString( '<div id="wp-buyer-blacklist-admin" data-tab="import-export"', $markup );
	}

	/**
	 * Falls back to Manage entries for an unknown tab.
	 *
	 * @return void
	 */
	public function test_unknown_tab_falls_back_to_manage_entries(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$markup = $this->render_admin_page(
			'woocommerce_page_wp-buyer-blacklist',
			array(
				'page' => 'wp-buyer-blacklist',
				'tab'  => 'unknown',
			)
		);

		$this->assertStringContainsString( 'data-tab="manage"', $markup );
	}

	/**
	 * Hides the Settings tab from a WooCommerce operator.
	 *
	 * @return void
	 */
	public function test_woocommerce_operator_cannot_open_the_settings_tab(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$markup = $this->render_admin_page(
			'woocommerce_page_wp-buyer-blacklist',
			array(
				'page' => 'wp-buyer-blacklist',
				'tab'  => 'settings',
			)
		);

		$this->assertStringContainsString( 'data-tab="manage"', $markup );
		$this->assertStringNotContainsString( 'tab=settings', $markup );
		$this->assertStringNotContainsString( 'wpbb-settings', $markup );
		$this->assertStringNotContainsString( 'data-can-manage-privacy-data', $markup );
	}

	/**
	 * Renders nothing for a WooCommerce operator when manager access is off.
	 *
	 * @return void
	 */
	public function test_renders_nothing_without_operator_access(): void {
		update_option( 'wp_buyer_blacklist_allow_woocommerce_managers', '0' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$this->assertSame( '', $this->render_admin_page( 'woocommerce_page_wp-buyer-blacklist', array( 'page' => 'wp-buyer-blacklist' ) ) );
	}

	/**
	 * Enqueues the admin script and style on the Buyer Blacklist page and the Users list.
	 *
	 * @dataProvider screens_with_the_admin_app
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return void
	 */
	public function test_enqueues_the_admin_app( string $hook_suffix ): void {
		$this->enqueue_admin_scripts( $hook_suffix );

		$this->assertTrue( wp_script_is( 'wp-buyer-blacklist-admin', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wp-buyer-blacklist-admin', 'enqueued' ) );
		$this->assertSame( 'replace', wp_styles()->get_data( 'wp-buyer-blacklist-admin', 'rtl' ) );
		$this->assertSame( WP_BUYER_BLACKLIST_URL . 'build/index.js', wp_scripts()->registered['wp-buyer-blacklist-admin']->src );
		$this->assertSame( WP_BUYER_BLACKLIST_URL . 'build/style-index.tsx.css', wp_styles()->registered['wp-buyer-blacklist-admin']->src );
		$this->assertSame( 'wp-buyer-blacklist', wp_scripts()->registered['wp-buyer-blacklist-admin']->textdomain );
	}

	/**
	 * Provides the screens that load the admin app.
	 *
	 * @return array<string, array{string}>
	 */
	public function screens_with_the_admin_app(): array {
		return array(
			'Buyer Blacklist page' => array( 'woocommerce_page_wp-buyer-blacklist' ),
			'Users list'           => array( 'users.php' ),
		);
	}

	/**
	 * Enqueues only the style on the enrollment page.
	 *
	 * @return void
	 */
	public function test_enqueues_only_the_style_on_the_enrollment_page(): void {
		$this->enqueue_admin_scripts( 'admin_page_wp-buyer-blacklist-enroll' );

		$this->assertFalse( wp_script_is( 'wp-buyer-blacklist-admin', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wp-buyer-blacklist-admin', 'enqueued' ) );
	}

	/**
	 * Enqueues nothing on other admin screens.
	 *
	 * @return void
	 */
	public function test_enqueues_nothing_on_other_screens(): void {
		$this->enqueue_admin_scripts( 'edit.php' );

		$this->assertFalse( wp_script_is( 'wp-buyer-blacklist-admin', 'registered' ) );
		$this->assertFalse( wp_style_is( 'wp-buyer-blacklist-admin', 'registered' ) );
	}

	/**
	 * Fires admin_enqueue_scripts the way wp-admin does, with the screen already set.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return void
	 */
	private function enqueue_admin_scripts( string $hook_suffix ): void {
		set_current_screen( $hook_suffix );
		do_action( 'admin_enqueue_scripts', $hook_suffix );
	}
}

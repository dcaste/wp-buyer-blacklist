<?php
/**
 * Buyer Blacklist admin page.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Admin;

use WP_Buyer_Blacklist\Access\OperatorAccess;
use WP_Buyer_Blacklist\Privacy\PrivacyDataAccess;
use WP_Buyer_Blacklist\Settings\SettingsTab;

/**
 * Registers the admin page, renders its tabs and loads its assets.
 */
final class AdminPage {

	/**
	 * Blacklist operator access policy.
	 *
	 * @var OperatorAccess
	 */
	private OperatorAccess $operator_access;

	/**
	 * Privacy data access check.
	 *
	 * @var PrivacyDataAccess
	 */
	private PrivacyDataAccess $privacy_access;

	/**
	 * Settings tab.
	 *
	 * @var SettingsTab
	 */
	private SettingsTab $settings_tab;

	/**
	 * Creates the admin page.
	 *
	 * @param OperatorAccess    $operator_access Blacklist operator access policy.
	 * @param PrivacyDataAccess $privacy_access  Privacy data access check.
	 * @param SettingsTab       $settings_tab    Settings tab.
	 */
	public function __construct( OperatorAccess $operator_access, PrivacyDataAccess $privacy_access, SettingsTab $settings_tab ) {
		$this->operator_access = $operator_access;
		$this->privacy_access  = $privacy_access;
		$this->settings_tab    = $settings_tab;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Registers the WooCommerce submenu page.
	 *
	 * @return void
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Buyer Blacklist', 'wp-buyer-blacklist' ),
			__( 'Buyer Blacklist', 'wp-buyer-blacklist' ),
			$this->operator_access->menu_capability(),
			'wp-buyer-blacklist',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Renders the entry creation and list screen.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! $this->operator_access->can_manage_entries() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This selects the tab to render and does not change plugin data.
		$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'manage';
		if ( ! in_array( $tab, array( 'manage', 'import-export', 'settings' ), true ) || ( 'settings' === $tab && ! $this->privacy_access->can_manage_privacy_data() ) ) {
			$tab = 'manage';
		}

		// The template runs in a static closure so it sees only these values, not $this.
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- The required template reads every parameter.
		( static function ( string $tab, bool $can_manage_settings, callable $render_settings_tab ): void {
			require WP_BUYER_BLACKLIST_DIR . 'templates/admin/page.php';
		} )( $tab, $this->privacy_access->can_manage_privacy_data(), array( $this->settings_tab, 'render' ) );
	}

	/**
	 * Enqueues the React admin application.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'woocommerce_page_wp-buyer-blacklist', 'admin_page_wp-buyer-blacklist-enroll', 'users.php' ), true ) ) {
			return;
		}
		$asset = file_exists( WP_BUYER_BLACKLIST_DIR . 'build/index.asset.php' ) ? require WP_BUYER_BLACKLIST_DIR . 'build/index.asset.php' : array(
			'dependencies' => array(),
			'version'      => '0.1.0',
		);
		if ( file_exists( WP_BUYER_BLACKLIST_DIR . 'build/style-index.tsx.css' ) ) {
			wp_enqueue_style( 'wp-buyer-blacklist-admin', WP_BUYER_BLACKLIST_URL . 'build/style-index.tsx.css', array(), $asset['version'] );
			wp_style_add_data( 'wp-buyer-blacklist-admin', 'rtl', 'replace' );
		}
		if ( in_array( $hook, array( 'woocommerce_page_wp-buyer-blacklist', 'users.php' ), true ) ) {
			wp_enqueue_script( 'wp-buyer-blacklist-admin', WP_BUYER_BLACKLIST_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
		}
	}
}

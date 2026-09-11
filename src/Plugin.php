<?php
/**
 * Plugin bootstrap and classic checkout enforcement.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

use InvalidArgumentException;
use WP_Error;

/**
 * Registers Blacklist administration and classic checkout enforcement.
 */
final class Plugin {

	/**
	 * Email entry storage.
	 *
	 * @var EmailEntryRepository
	 */
	private EmailEntryRepository $entries;

	/**
	 * Email checkout blocking decision.
	 *
	 * @var EmailCheckoutBlocker
	 */
	private EmailCheckoutBlocker $blocker;

	/**
	 * Address checkout blocking decision.
	 *
	 * @var AddressCheckoutBlocker
	 */
	private AddressCheckoutBlocker $address_blocker;

	/**
	 * Customer and Order enrollment workflow.
	 *
	 * @var SourceEnrollment
	 */
	private SourceEnrollment $source_enrollment;

	/**
	 * Blacklist operator access policy.
	 *
	 * @var OperatorAccess
	 */
	private OperatorAccess $operator_access;

	/**
	 * Creates the plugin.
	 *
	 * @param EmailEntryRepository $entries Email entry storage.
	 */
	public function __construct( EmailEntryRepository $entries ) {
		$this->entries           = $entries;
		$this->blocker           = new EmailCheckoutBlocker( $entries, $entries );
		$this->address_blocker   = new AddressCheckoutBlocker( $entries );
		$this->operator_access   = new OperatorAccess();
		$this->source_enrollment = new SourceEnrollment( $entries, $this->operator_access );
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_post_wp_buyer_blacklist_export', array( $this, 'download_csv_export' ) );
		add_action( 'admin_post_wp_buyer_blacklist_dossier_export', array( $this, 'download_identity_dossier' ) );
		add_action( 'admin_post_wp_buyer_blacklist_reset_settings', array( $this, 'reset_settings' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_personal_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_personal_data_eraser' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_classic_checkout' ), 10, 2 );
		add_action( 'woocommerce_checkout_validate_order_before_payment', array( $this, 'validate_store_api_checkout' ), 10, 2 );
		$this->source_enrollment->register();
	}

	/**
	 * Registers the uninstall cleanup option.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'wp_buyer_blacklist',
			'wp_buyer_blacklist_allow_woocommerce_managers',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);
		register_setting(
			'wp_buyer_blacklist',
			'wp_buyer_blacklist_delete_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
		register_setting(
			'wp_buyer_blacklist',
			'wp_buyer_blacklist_buyer_message',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_buyer_block_message' ),
				'default'           => $this->default_buyer_block_message(),
			)
		);
	}

	/**
	 * Sanitizes the Buyer block message.
	 *
	 * @param mixed $message Buyer block message.
	 * @return string Sanitized Buyer block message.
	 */
	public function sanitize_buyer_block_message( mixed $message ): string {
		$message = sanitize_textarea_field( $message );

		return '' === trim( $message ) ? $this->default_buyer_block_message() : $message;
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
		if ( ! $this->can_manage_entries() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This selects the tab to render and does not change plugin data.
		$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'manage';
		if ( ! in_array( $tab, array( 'manage', 'import-export', 'settings' ), true ) || ( 'settings' === $tab && ! $this->can_manage_privacy_data() ) ) {
			$tab = 'manage';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Buyer Blacklist', 'wp-buyer-blacklist' ); ?></h1>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Buyer Blacklist sections', 'wp-buyer-blacklist' ); ?>">
				<a class="nav-tab <?php echo 'manage' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-buyer-blacklist' ) ); ?>"><?php esc_html_e( 'Manage entries', 'wp-buyer-blacklist' ); ?></a>
				<a class="nav-tab <?php echo 'import-export' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-buyer-blacklist&tab=import-export' ) ); ?>"><?php esc_html_e( 'Import and export', 'wp-buyer-blacklist' ); ?></a>
				<?php
				if ( $this->can_manage_privacy_data() ) :
					?>
					<a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-buyer-blacklist&tab=settings' ) ); ?>"><?php esc_html_e( 'Settings', 'wp-buyer-blacklist' ); ?></a><?php endif; ?>
			</nav>
			<?php if ( 'settings' === $tab ) : ?>
				<?php if ( isset( $_GET['wp_buyer_blacklist_settings'] ) && 'reset' === sanitize_key( wp_unslash( $_GET['wp_buyer_blacklist_settings'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This displays a result from the nonce-protected reset action. ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings restored to their defaults.', 'wp-buyer-blacklist' ); ?></p></div>
				<?php endif; ?>
				<form class="wpbb-settings" action="options.php" method="post">
					<?php settings_fields( 'wp_buyer_blacklist' ); ?>
					<section class="wpbb-settings-card wpbb-settings-message-card"><header><h2><?php esc_html_e( 'Buyer-facing block message', 'wp-buyer-blacklist' ); ?></h2><p class="description"><?php esc_html_e( 'This message is shown when a Buyer is blocked from placing an order.', 'wp-buyer-blacklist' ); ?></p></header><div class="wpbb-settings-message-content"><div><p><label for="wp-buyer-blacklist-buyer-message"><?php esc_html_e( 'Message', 'wp-buyer-blacklist' ); ?></label><textarea aria-describedby="wp-buyer-blacklist-buyer-message-description" class="large-text" id="wp-buyer-blacklist-buyer-message" name="wp_buyer_blacklist_buyer_message" rows="3"><?php echo esc_textarea( get_option( 'wp_buyer_blacklist_buyer_message', $this->default_buyer_block_message() ) ); ?></textarea></p><p class="description" id="wp-buyer-blacklist-buyer-message-description"><?php esc_html_e( 'Keep the message clear and professional. Explain what the Buyer can do next.', 'wp-buyer-blacklist' ); ?></p></div><div class="wpbb-settings-preview"><strong><?php esc_html_e( 'Preview', 'wp-buyer-blacklist' ); ?></strong><p class="description"><?php esc_html_e( 'This is how the message appears to a blocked Buyer during checkout.', 'wp-buyer-blacklist' ); ?></p><div class="wpbb-message-preview" aria-live="polite"><span class="wpbb-message-preview-icon" aria-hidden="true">!</span><p id="wp-buyer-blacklist-message-preview"><?php echo esc_html( get_option( 'wp_buyer_blacklist_buyer_message', $this->default_buyer_block_message() ) ); ?></p></div></div></div></section>
					<section class="wpbb-settings-card wpbb-settings-permissions-card"><header><h2><?php esc_html_e( 'Permissions', 'wp-buyer-blacklist' ); ?></h2><p class="description"><?php esc_html_e( 'Control who can manage the Blacklist in your store.', 'wp-buyer-blacklist' ); ?></p></header><input name="wp_buyer_blacklist_allow_woocommerce_managers" type="hidden" value="0" /><label class="wpbb-toggle"><input aria-describedby="wp-buyer-blacklist-allow-woocommerce-managers-description" id="wp-buyer-blacklist-allow-woocommerce-managers" name="wp_buyer_blacklist_allow_woocommerce_managers" type="checkbox" value="1" <?php checked( get_option( 'wp_buyer_blacklist_allow_woocommerce_managers', true ) ); ?> /> <?php esc_html_e( 'Allow WooCommerce managers to manage Blacklist entries', 'wp-buyer-blacklist' ); ?></label><p class="description" id="wp-buyer-blacklist-allow-woocommerce-managers-description"><?php esc_html_e( 'Gives users with WooCommerce management permission access to entries, CSV imports, and Blocked attempts.', 'wp-buyer-blacklist' ); ?></p></section>
					<section class="wpbb-settings-card wpbb-settings-cleanup-card"><header><h2><?php esc_html_e( 'Data and cleanup', 'wp-buyer-blacklist' ); ?></h2><p class="description"><?php esc_html_e( 'Manage what happens to Blacklist data when the plugin is removed.', 'wp-buyer-blacklist' ); ?></p></header><input name="wp_buyer_blacklist_delete_data_on_uninstall" type="hidden" value="0" /><label class="wpbb-toggle"><input aria-describedby="wp-buyer-blacklist-delete-data-description" id="wp-buyer-blacklist-delete-data" name="wp_buyer_blacklist_delete_data_on_uninstall" type="checkbox" value="1" <?php checked( get_option( 'wp_buyer_blacklist_delete_data_on_uninstall', false ) ); ?> /> <?php esc_html_e( 'Delete all Blacklist data when the plugin is uninstalled', 'wp-buyer-blacklist' ); ?></label><p class="description" id="wp-buyer-blacklist-delete-data-description"><?php esc_html_e( 'Permanently deletes all Blacklist entries, import history, and Blocked attempt data when the plugin is uninstalled. This cannot be undone.', 'wp-buyer-blacklist' ); ?></p><div class="wpbb-settings-warning"><span class="dashicons dashicons-warning" aria-hidden="true"></span><div><strong><?php esc_html_e( 'This will permanently delete your data.', 'wp-buyer-blacklist' ); ?></strong><p><?php esc_html_e( 'Export your entries first if you need a backup before uninstalling the plugin.', 'wp-buyer-blacklist' ); ?></p></div></div></section>
					<div class="wpbb-settings-actions"><?php wp_nonce_field( 'wp_buyer_blacklist_reset_settings' ); ?><?php submit_button( __( 'Save changes', 'wp-buyer-blacklist' ), 'primary', 'submit', false ); ?><button class="button" type="submit" name="action" value="wp_buyer_blacklist_reset_settings" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" formmethod="post" onclick="return window.confirm('<?php echo esc_js( __( 'Reset all Buyer Blacklist settings to their defaults?', 'wp-buyer-blacklist' ) ); ?>');"><?php esc_html_e( 'Reset to defaults', 'wp-buyer-blacklist' ); ?></button></div>
				</form>
				<script>document.getElementById('wp-buyer-blacklist-buyer-message').addEventListener('input', function () { document.getElementById('wp-buyer-blacklist-message-preview').textContent = this.value || '<?php echo esc_js( $this->default_buyer_block_message() ); ?>'; });</script>
			<?php else : ?>
				<div id="wp-buyer-blacklist-admin" data-tab="<?php echo esc_attr( $tab ); ?>" data-export-url="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wp_buyer_blacklist_export' ), 'wp_buyer_blacklist_export' ) ); ?>" data-example-csv-url="<?php echo esc_url( WP_BUYER_BLACKLIST_URL . 'assets/buyer-blacklist-example.csv' ); ?>" data-dossier-export-url="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wp_buyer_blacklist_dossier_export' ), 'wp_buyer_blacklist_dossier_export' ) ); ?>"></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Restores supported settings to their default values.
	 *
	 * @return void
	 */
	public function reset_to_default_settings(): void {
		delete_option( 'wp_buyer_blacklist_allow_woocommerce_managers' );
		delete_option( 'wp_buyer_blacklist_delete_data_on_uninstall' );
		delete_option( 'wp_buyer_blacklist_buyer_message' );
	}

	/**
	 * Handles a request to restore the default settings.
	 *
	 * @return void
	 */
	public function reset_settings(): void {
		if ( ! $this->can_manage_privacy_data() ) {
			wp_die( esc_html__( 'You are not allowed to reset Blacklist settings.', 'wp-buyer-blacklist' ), 403 );
		}

		check_admin_referer( 'wp_buyer_blacklist_reset_settings' );
		$this->reset_to_default_settings();
		wp_safe_redirect( add_query_arg( 'wp_buyer_blacklist_settings', 'reset', admin_url( 'admin.php?page=wp-buyer-blacklist&tab=settings' ) ) );
		exit;
	}

	/**
	 * Blocks a matching Buyer at classic checkout validation.
	 *
	 * @param array<string, mixed> $data   Submitted checkout data.
	 * @param WP_Error             $errors Checkout validation errors.
	 * @return void
	 */
	public function validate_classic_checkout( array $data, WP_Error $errors ): void {
		$email = isset( $data['billing_email'] ) && is_string( $data['billing_email'] ) ? $data['billing_email'] : '';

		if ( $this->blocker->blocks( $email ) || $this->blocks_checkout_address( 'billing_address', $data ) || $this->blocks_checkout_address( 'shipping_address', $data ) ) {
			$errors->add( 'wp_buyer_blacklist_blocked', $this->buyer_block_message() );
		}
	}

	/**
	 * Blocks a matching Buyer before Checkout Block or Store API payment.
	 *
	 * @param \WC_Order $order  Checkout order.
	 * @param WP_Error  $errors Checkout validation errors.
	 * @return void
	 */
	public function validate_store_api_checkout( \WC_Order $order, WP_Error $errors ): void {
		$billing_address  = array(
			'address_line_1' => $order->get_billing_address_1(),
			'address_line_2' => $order->get_billing_address_2(),
			'city'           => $order->get_billing_city(),
			'state'          => $order->get_billing_state(),
			'postcode'       => $order->get_billing_postcode(),
			'country'        => $order->get_billing_country(),
		);
		$shipping_address = array(
			'address_line_1' => $order->get_shipping_address_1(),
			'address_line_2' => $order->get_shipping_address_2(),
			'city'           => $order->get_shipping_city(),
			'state'          => $order->get_shipping_state(),
			'postcode'       => $order->get_shipping_postcode(),
			'country'        => $order->get_shipping_country(),
		);
		if ( $this->blocker->blocks( $order->get_billing_email() ) || $this->address_blocker->blocks( 'billing_address', $billing_address, WC()->countries->get_countries() ) || $this->address_blocker->blocks( 'shipping_address', $shipping_address, WC()->countries->get_countries() ) ) {
			$errors->add( 'wp_buyer_blacklist_blocked', $this->buyer_block_message() );
		}
	}

	/**
	 * Checks a complete classic-checkout address.
	 *
	 * @param string               $match_type Address Match type.
	 * @param array<string, mixed> $data       Submitted checkout data.
	 * @return bool Whether the address blocks checkout.
	 */
	private function blocks_checkout_address( string $match_type, array $data ): bool {
		$prefix = 'billing_address' === $match_type ? 'billing_' : 'shipping_';
		$fields = array(
			'address_line_1' => isset( $data[ $prefix . 'address_1' ] ) && is_string( $data[ $prefix . 'address_1' ] ) ? $data[ $prefix . 'address_1' ] : '',
			'address_line_2' => isset( $data[ $prefix . 'address_2' ] ) && is_string( $data[ $prefix . 'address_2' ] ) ? $data[ $prefix . 'address_2' ] : '',
			'city'           => isset( $data[ $prefix . 'city' ] ) && is_string( $data[ $prefix . 'city' ] ) ? $data[ $prefix . 'city' ] : '',
			'state'          => isset( $data[ $prefix . 'state' ] ) && is_string( $data[ $prefix . 'state' ] ) ? $data[ $prefix . 'state' ] : '',
			'postcode'       => isset( $data[ $prefix . 'postcode' ] ) && is_string( $data[ $prefix . 'postcode' ] ) ? $data[ $prefix . 'postcode' ] : '',
			'country'        => isset( $data[ $prefix . 'country' ] ) && is_string( $data[ $prefix . 'country' ] ) ? $data[ $prefix . 'country' ] : '',
		);

		return $this->address_blocker->blocks( $match_type, $fields, WC()->countries->get_countries() );
	}

	/**
	 * Gets the generic Buyer block message.
	 *
	 * @return string
	 */
	private function buyer_block_message(): string {
		$default = $this->default_buyer_block_message();
		$message = get_option( 'wp_buyer_blacklist_buyer_message', $default );

		return is_string( $message ) && '' !== $message ? $message : $default;
	}

	/**
	 * Gets the translated default Buyer block message.
	 *
	 * @return string
	 */
	private function default_buyer_block_message(): string {
		return __( 'We’re unable to process your order. Please contact us for assistance.', 'wp-buyer-blacklist' );
	}

	/** Registers the private entry API. @return void */
	public function register_rest_routes(): void {
		register_rest_route(
			'wp-buyer-blacklist/v1',
			'/entry-summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_entry_summary' ),
				'permission_callback' => array( $this, 'can_manage_entries' ),
			)
		);
		register_rest_route(
			'wp-buyer-blacklist/v1',
			'/email-entries',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_email_entries' ),
					'permission_callback' => array( $this, 'can_manage_entries' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_email_entry' ),
					'permission_callback' => array( $this, 'can_manage_entries' ),
				),
			)
		);
		register_rest_route(
			'wp-buyer-blacklist/v1',
			'/address-entries',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_address_entry' ),
					'permission_callback' => array( $this, 'can_manage_entries' ),
				),
			)
		);
		register_rest_route(
			'wp-buyer-blacklist/v1',
			'/csv-import/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'validate_csv_import' ),
				'permission_callback' => array( $this, 'can_manage_entries' ),
			)
		);
		register_rest_route(
			'wp-buyer-blacklist/v1',
			'/csv-import/confirm',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'confirm_csv_import' ),
				'permission_callback' => array( $this, 'can_manage_entries' ),
			)
		);
		register_rest_route(
			'wp-buyer-blacklist/v1',
			'/import-history',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_import_history' ),
				'permission_callback' => array( $this, 'can_manage_entries' ),
			)
		);
		register_rest_route(
			'wp-buyer-blacklist/v1',
			'/email-entries/(?P<id>\\d+)/blocked-attempts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_email_entry_blocked_attempts' ),
				'permission_callback' => array( $this, 'can_manage_entries' ),
			)
		);
		register_rest_route(
			'wp-buyer-blacklist/v1',
			'/email-entries/(?P<id>\\d+)/buyer-context',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_buyer_context' ),
				'permission_callback' => array( $this, 'can_manage_entries' ),
			)
		);
		register_rest_route(
			'wp-buyer-blacklist/v1',
			'/email-entries/(?P<id>\\d+)/dossier',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_identity_dossier' ),
				'permission_callback' => array( $this, 'can_manage_privacy_data' ),
			)
		);
		register_rest_route(
			'wp-buyer-blacklist/v1',
			'/email-entries/(?P<id>\\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_email_entry_audit_events' ),
					'permission_callback' => array( $this, 'can_manage_entries' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_email_entry' ),
					'permission_callback' => array( $this, 'can_manage_entries' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_email_entry' ),
					'permission_callback' => array( $this, 'can_manage_privacy_data' ),
				),
			)
		);
	}

	/**
	 * Checks the Blacklist operator capability.
	 *
	 * @return bool
	 */
	public function can_manage_entries(): bool {
		return $this->operator_access->can_manage_entries();
	}

	/**
	 * Checks the privacy administration capability.
	 *
	 * @return bool
	 */
	public function can_manage_privacy_data(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Sends a full Blacklist CSV export to an administrator.
	 *
	 * @return void
	 */
	public function download_csv_export(): void {
		if ( ! $this->can_manage_privacy_data() ) {
			wp_die( esc_html__( 'You are not allowed to export Blacklist data.', 'wp-buyer-blacklist' ), 403 );
		}
		check_admin_referer( 'wp_buyer_blacklist_export' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="buyer-blacklist.csv"' );
		echo $this->entries->export_csv(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV values are encoded by fputcsv().
		exit;
	}

	/**
	 * Sends one identity dossier as JSON to an administrator.
	 *
	 * @return void
	 */
	public function download_identity_dossier(): void {
		if ( ! $this->can_manage_privacy_data() ) {
			wp_die( esc_html__( 'You are not allowed to export Blacklist data.', 'wp-buyer-blacklist' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- identity_dossier_for_download() verifies this request nonce.
		$entry_id = isset( $_GET['entry_id'] ) ? absint( wp_unslash( $_GET['entry_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- identity_dossier_for_download() verifies this request nonce.
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		$dossier = $this->identity_dossier_for_download( $entry_id, $nonce );
		if ( is_wp_error( $dossier ) ) {
			wp_die( esc_html( $dossier->get_error_message() ), (int) $dossier->get_error_data()['status'] );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="buyer-blacklist-dossier-' . $entry_id . '.json"' );
		echo wp_json_encode( $dossier, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() encodes every dossier value.
		exit;
	}

	/**
	 * Gets an identity dossier for an administrator download.
	 *
	 * @param int    $entry_id Blacklist entry ID.
	 * @param string $nonce    Admin action nonce.
	 * @return array<string, mixed>|WP_Error Identity dossier or download error.
	 */
	public function identity_dossier_for_download( int $entry_id, string $nonce ): array|WP_Error {
		if ( ! $this->can_manage_privacy_data() ) {
			return new WP_Error( 'wp_buyer_blacklist_forbidden', __( 'You are not allowed to export Blacklist data.', 'wp-buyer-blacklist' ), array( 'status' => 403 ) );
		}
		if ( ! wp_verify_nonce( $nonce, 'wp_buyer_blacklist_dossier_export' ) ) {
			return new WP_Error( 'wp_buyer_blacklist_invalid_nonce', __( 'The download link has expired. Refresh the page and try again.', 'wp-buyer-blacklist' ), array( 'status' => 403 ) );
		}

		$dossier = $this->entries->identity_dossier( $entry_id );
		if ( null === $dossier ) {
			return new WP_Error( 'wp_buyer_blacklist_not_found', __( 'The Blacklist entry could not be found.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		return $dossier;
	}

	/**
	 * Gets an identity dossier as structured JSON.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_identity_dossier( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$dossier = $this->entries->identity_dossier( absint( $request['id'] ) );
		if ( null === $dossier ) {
			return new \WP_Error( 'wp_buyer_blacklist_not_found', __( 'The Blacklist entry could not be found.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		return new \WP_REST_Response( $dossier );
	}

	/**
	 * Registers the WordPress personal-data exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Personal-data exporters.
	 * @return array<string, array<string, mixed>> Personal-data exporters.
	 */
	public function register_personal_data_exporter( array $exporters ): array {
		$exporters['wp-buyer-blacklist'] = array(
			'exporter_friendly_name' => __( 'Buyer Blacklist', 'wp-buyer-blacklist' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Exports data for email identities through WordPress privacy tools.
	 *
	 * @param string $email_address Buyer email address.
	 * @param int    $page          Export page.
	 * @return array{data: list<array<string, mixed>>, done: bool} Personal data.
	 */
	public function export_personal_data( string $email_address, int $page ): array {
		unset( $page );
		$data = array();
		foreach ( $this->entries->identity_dossiers_for_email( $email_address ) as $dossier ) {
			$entry  = $dossier['entry'];
			$data[] = array(
				'group_id'    => 'wp-buyer-blacklist',
				'group_label' => __( 'Buyer Blacklist', 'wp-buyer-blacklist' ),
				'item_id'     => 'entry-' . $entry['id'],
				'data'        => array(
					array(
						'name'  => __( 'Email', 'wp-buyer-blacklist' ),
						'value' => $entry['value'],
					),
					array(
						'name'  => __( 'Reason', 'wp-buyer-blacklist' ),
						'value' => $entry['reason'],
					),
					array(
						'name'  => __( 'Blocked attempts', 'wp-buyer-blacklist' ),
						'value' => implode( "\n", array_map( fn( $attempt ) => $attempt['outcome'] . ' at ' . $attempt['created_at'], $dossier['blocked_attempts'] ) ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Registers the WordPress personal-data eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Personal-data erasers.
	 * @return array<string, array<string, mixed>> Personal-data erasers.
	 */
	public function register_personal_data_eraser( array $erasers ): array {
		$erasers['wp-buyer-blacklist'] = array(
			'eraser_friendly_name' => __( 'Buyer Blacklist', 'wp-buyer-blacklist' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Erases data for email identities through WordPress privacy tools.
	 *
	 * @param string $email_address Buyer email address.
	 * @param int    $page          Erasure page.
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool} Erasure result.
	 */
	public function erase_personal_data( string $email_address, int $page ): array {
		unset( $page );
		$removed = false;
		foreach ( $this->entries->identity_dossiers_for_email( $email_address ) as $dossier ) {
			$removed = $this->entries->erase( (int) $dossier['entry']['id'] ) || $removed;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Validates a CSV file before the operator confirms its import.
	 *
	 * @param \WP_REST_Request $request Request containing CSV text.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function validate_csv_import( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$csv = $request->get_param( 'csv' );
		if ( ! is_string( $csv ) ) {
			return new \WP_Error( 'wp_buyer_blacklist_invalid_csv', __( 'Select a CSV file to import.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}

		try {
			$result = CsvImport::validate( $csv, WC()->countries->get_countries() );
		} catch ( InvalidArgumentException $exception ) {
			return new \WP_Error( 'wp_buyer_blacklist_invalid_csv', $exception->getMessage(), array( 'status' => 400 ) );
		}

		foreach ( $result['accepted'] as $index => $entry ) {
			if ( $this->entries->exists( $entry['match_type'], $entry['canonical_hash'] ) ) {
				$result['duplicates'][] = array(
					'row'    => $entry['row'],
					'reason' => __( 'Duplicate entry.', 'wp-buyer-blacklist' ),
				);
				unset( $result['accepted'][ $index ] );
				continue;
			}
			$reason                                 = sanitize_textarea_field( $entry['reason'] );
			$result['accepted'][ $index ]['reason'] = '' === trim( $reason ) ? __( 'Imported blacklist entry', 'wp-buyer-blacklist' ) : $reason;
		}
		$result['accepted']  = array_values( $result['accepted'] );
		$result['file_name'] = sanitize_file_name( (string) $request->get_param( 'filename' ) );
		$token               = wp_generate_uuid4();
		set_transient( $this->csv_import_transient_key( $token ), $result, HOUR_IN_SECONDS );

		return new \WP_REST_Response( $this->csv_import_result( $result, $token ) );
	}

	/**
	 * Imports the rows accepted during CSV validation.
	 *
	 * @param \WP_REST_Request $request Confirmation token request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function confirm_csv_import( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$token  = $request->get_param( 'token' );
		$result = is_string( $token ) ? get_transient( $this->csv_import_transient_key( $token ) ) : false;
		if ( ! is_array( $result ) || ! isset( $result['accepted'], $result['duplicates'], $result['rejected'] ) ) {
			return new \WP_Error( 'wp_buyer_blacklist_invalid_import', __( 'Validate the CSV file before importing it.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}

		$created = 0;
		foreach ( array_chunk( $result['accepted'], 100 ) as $batch ) {
			foreach ( $batch as $entry ) {
				$status = $this->entries->create_imported( $entry );
				if ( 'failed' === $status ) {
					return new \WP_Error( 'wp_buyer_blacklist_import_failed', __( 'The import stopped because of a technical failure.', 'wp-buyer-blacklist' ), array( 'status' => 500 ) );
				}
				if ( 'duplicate' === $status ) {
					$result['duplicates'][] = array(
						'row'    => $entry['row'],
						'reason' => __( 'Duplicate entry.', 'wp-buyer-blacklist' ),
					);
					continue;
				}
				++$created;
			}
		}
		delete_transient( $this->csv_import_transient_key( $token ) );
		$this->entries->record_import( (string) ( $result['file_name'] ?? __( 'CSV import', 'wp-buyer-blacklist' ) ), count( $result['accepted'] ) + count( $result['duplicates'] ) + count( $result['rejected'] ), $created, count( $result['duplicates'] ), count( $result['rejected'] ) );

		return new \WP_REST_Response( $this->csv_import_result( $result, '', $created ) );
	}

	/**
	 * Gets the transient key for the current operator's validated import.
	 *
	 * @param string $token Import confirmation token.
	 * @return string
	 */
	private function csv_import_transient_key( string $token ): string {
		return 'wp_buyer_blacklist_import_' . get_current_user_id() . '_' . $token;
	}

	/**
	 * Formats a validation review or completed import result.
	 *
	 * @param array{accepted: list<array<string, mixed>>, duplicates: list<array{row: int, reason: string}>, rejected: list<array{row: int, reason: string}>} $result Import result.
	 * @param string                                                                                                                                          $token  Confirmation token.
	 * @param int|null                                                                                                                                        $created Created entry count.
	 * @return array<string, mixed>
	 */
	private function csv_import_result( array $result, string $token, ?int $created = null ): array {
		return array(
			'token'      => $token,
			'valid'      => count( $result['accepted'] ),
			'created'    => $created,
			'duplicates' => $result['duplicates'],
			'rejected'   => $result['rejected'],
		);
	}

	/**
	 * Gets email entries for the admin application.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_email_entries(): array {
		return $this->entries->all();
	}

	/**
	 * Gets totals for the Manage entries summary cards.
	 *
	 * @return array{total_entries: int, active_entries: int, inactive_entries: int, blocked_attempts: int}
	 */
	public function get_entry_summary(): array {
		return $this->entries->summary();
	}

	/** Gets protected import history. @return list<array<string, string>> */
	public function get_import_history(): array {
		return $this->entries->recent_imports();
	}

	/**
	 * Creates an email entry through the REST API.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_email_entry( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$reason = $this->reason_from_request( $request );
		if ( is_wp_error( $reason ) ) {
			return $reason;
		}
		try {
			$entry = EmailEntry::from_email( (string) $request->get_param( 'email' ) );
		} catch ( InvalidArgumentException $exception ) {
			return new \WP_Error( 'wp_buyer_blacklist_invalid_email', __( 'Enter a valid email address.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}
		if ( ! $this->entries->create( $entry, $reason ) ) {
			return new \WP_Error( 'wp_buyer_blacklist_duplicate', __( 'That email already has a Blacklist entry.', 'wp-buyer-blacklist' ), array( 'status' => 409 ) );
		}
		return new \WP_REST_Response( array( 'message' => __( 'Email entry created.', 'wp-buyer-blacklist' ) ), 201 );
	}

	/**
	 * Creates an address entry through the REST API.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_address_entry( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$reason = $this->reason_from_request( $request );
		if ( is_wp_error( $reason ) ) {
			return $reason;
		}

		$fields = array();
		foreach ( array( 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country' ) as $field ) {
			$value            = $request->get_param( $field );
			$fields[ $field ] = is_string( $value ) ? $value : '';
		}

		try {
			$entry = AddressEntry::from_fields( (string) $request->get_param( 'match_type' ), $fields, WC()->countries->get_countries() );
		} catch ( InvalidArgumentException $exception ) {
			return new \WP_Error( 'wp_buyer_blacklist_invalid_address', __( 'Enter a complete address with a valid country.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}
		if ( ! $this->entries->create_address( $entry, $reason ) ) {
			return new \WP_Error( 'wp_buyer_blacklist_duplicate', __( 'That address already has a Blacklist entry.', 'wp-buyer-blacklist' ), array( 'status' => 409 ) );
		}

		return new \WP_REST_Response( array( 'message' => __( 'Address entry created.', 'wp-buyer-blacklist' ) ), 201 );
	}

	/**
	 * Gets an entry's Audit events.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return list<array<string, mixed>>
	 */
	public function get_email_entry_audit_events( \WP_REST_Request $request ): array {
		return $this->entries->audit_events( absint( $request['id'] ) );
	}

	/**
	 * Gets an entry's Blocked attempts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return list<array<string, string>>
	 */
	public function get_email_entry_blocked_attempts( \WP_REST_Request $request ): array {
		return $this->entries->blocked_attempts( absint( $request['id'] ) );
	}

	/**
	 * Gets current Buyer data when an entry identifies one registered Buyer.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_Error Buyer context or not-found error.
	 */
	public function get_buyer_context( \WP_REST_Request $request ): array|WP_Error {
		$dossier = $this->entries->identity_dossier( absint( $request['id'] ) );
		if ( null === $dossier ) {
			return new \WP_Error( 'wp_buyer_blacklist_not_found', __( 'The Blacklist entry could not be found.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		$entry = $dossier['entry'];
		$user  = 'customer_id' === $entry['match_type'] ? get_userdata( absint( $entry['value'] ) ) : ( 'email' === $entry['match_type'] ? get_user_by( 'email', $entry['value'] ) : false );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'buyer'   => null,
				'message' => __( 'No uniquely identified registered Buyer.', 'wp-buyer-blacklist' ),
			);
		}

		$customer = new \WC_Customer( $user->ID );
		return array(
			'buyer' => array(
				'id'               => $user->ID,
				'name'             => trim( $user->first_name . ' ' . $user->last_name ),
				'email'            => $user->user_email,
				'billing_address'  => $this->customer_address( $customer, 'billing' ),
				'shipping_address' => $this->customer_address( $customer, 'shipping' ),
			),
		);
	}

	/**
	 * Gets a current customer address.
	 *
	 * @param \WC_Customer $customer Customer.
	 * @param string       $prefix   Address prefix.
	 * @return array<string, string> Address fields.
	 */
	private function customer_address( \WC_Customer $customer, string $prefix ): array {
		return array(
			'address_line_1' => $customer->{"get_{$prefix}_address_1"}(),
			'address_line_2' => $customer->{"get_{$prefix}_address_2"}(),
			'city'           => $customer->{"get_{$prefix}_city"}(),
			'state'          => $customer->{"get_{$prefix}_state"}(),
			'postcode'       => $customer->{"get_{$prefix}_postcode"}(),
			'country'        => $customer->{"get_{$prefix}_country"}(),
		);
	}

	/**
	 * Updates an entry reason or status.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_email_entry( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$entry_id = absint( $request['id'] );
		if ( $this->has_identity_update( $request ) ) {
			return new \WP_Error( 'wp_buyer_blacklist_identity_immutable', __( 'A Blacklist entry identity cannot be changed. Deactivate it and create a replacement entry instead.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}
		if ( null !== $request->get_param( 'reason' ) && null !== $request->get_param( 'is_active' ) ) {
			return new \WP_Error( 'wp_buyer_blacklist_multiple_updates', __( 'Update either the reason or status in one request.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}
		if ( null !== $request->get_param( 'reason' ) ) {
			$reason = $this->reason_from_request( $request );
			if ( is_wp_error( $reason ) ) {
				return $reason;
			}
			$updated = $this->entries->update_reason( $entry_id, $reason );
		} elseif ( null !== $request->get_param( 'is_active' ) ) {
			$updated = $this->entries->set_active( $entry_id, rest_sanitize_boolean( $request->get_param( 'is_active' ) ) );
		} else {
			return new \WP_Error( 'wp_buyer_blacklist_missing_update', __( 'Provide a reason or status to update.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}

		if ( ! $updated ) {
			return new \WP_Error( 'wp_buyer_blacklist_update_failed', __( 'The Blacklist entry could not be updated.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		return new \WP_REST_Response( array( 'message' => __( 'Blacklist entry updated.', 'wp-buyer-blacklist' ) ) );
	}

	/**
	 * Checks whether a request attempts to change an immutable identity.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool Whether the request contains an identity field.
	 */
	private function has_identity_update( \WP_REST_Request $request ): bool {
		foreach ( array( 'match_type', 'value', 'email', 'customer_id', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country', 'canonical_value', 'canonical_hash' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Deletes an entry after confirmation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_email_entry( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( true !== rest_sanitize_boolean( $request->get_param( 'confirm' ) ) ) {
			return new \WP_Error( 'wp_buyer_blacklist_delete_not_confirmed', __( 'Confirm deletion before removing this Blacklist entry.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
		}
		if ( ! $this->entries->delete( absint( $request['id'] ) ) ) {
			return new \WP_Error( 'wp_buyer_blacklist_delete_failed', __( 'The Blacklist entry could not be deleted.', 'wp-buyer-blacklist' ), array( 'status' => 404 ) );
		}

		return new \WP_REST_Response( array( 'message' => __( 'Blacklist entry deleted.', 'wp-buyer-blacklist' ) ) );
	}

	/**
	 * Gets a required reason from a REST request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string
	 */
	private function reason_from_request( \WP_REST_Request $request ): string {
		return sanitize_textarea_field( (string) $request->get_param( 'reason' ) );
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

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
	 * Creates the plugin.
	 *
	 * @param EmailEntryRepository $entries Email entry storage.
	 */
	public function __construct( EmailEntryRepository $entries ) {
		$this->entries = $entries;
		$this->blocker = new EmailCheckoutBlocker( $entries );
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_classic_checkout' ), 10, 2 );
		add_action( 'woocommerce_checkout_validate_order_before_payment', array( $this, 'validate_store_api_checkout' ), 10, 2 );
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
			'manage_woocommerce',
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
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This read-only redirect status does not change plugin data.
		?>
		<div class="wrap"><div id="wp-buyer-blacklist-admin"></div></div>
		<?php
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

		if ( $this->blocker->blocks( $email ) ) {
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
		if ( $this->blocker->blocks( $order->get_billing_email() ) ) {
			$errors->add( 'wp_buyer_blacklist_blocked', $this->buyer_block_message() );
		}
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
	}

	/**
	 * Checks the Blacklist operator capability.
	 *
	 * @return bool
	 */
	public function can_manage_entries(): bool {
		return current_user_can( 'manage_woocommerce' );
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
	 * Creates an email entry through the REST API.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_email_entry( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$reason = sanitize_textarea_field( (string) $request->get_param( 'reason' ) );
		if ( '' === trim( $reason ) ) {
			return new \WP_Error( 'wp_buyer_blacklist_missing_reason', __( 'Enter a reason for this Blacklist entry.', 'wp-buyer-blacklist' ), array( 'status' => 400 ) );
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
	 * Enqueues the React admin application.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'woocommerce_page_wp-buyer-blacklist' !== $hook ) {
			return;
		}
		$asset = file_exists( WP_BUYER_BLACKLIST_DIR . 'build/index.asset.php' ) ? require WP_BUYER_BLACKLIST_DIR . 'build/index.asset.php' : array(
			'dependencies' => array(),
			'version'      => '0.1.0',
		);
		wp_enqueue_script( 'wp-buyer-blacklist-admin', WP_BUYER_BLACKLIST_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
	}
}

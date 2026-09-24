<?php
/**
 * Orders screen enrollment.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Enrollment;

use WP_Buyer_Blacklist\Access\OperatorAccess;

/**
 * Adds Blacklist enrollment actions to WooCommerce Order rows, details and bulk actions.
 */
final class OrderScreen {

	/**
	 * Standalone enrollment page.
	 *
	 * @var EnrollmentPage
	 */
	private EnrollmentPage $page;

	/**
	 * Blacklist operator access policy.
	 *
	 * @var OperatorAccess
	 */
	private OperatorAccess $operator_access;

	/**
	 * Creates the Orders screen enrollment.
	 *
	 * @param EnrollmentPage $page            Standalone enrollment page.
	 * @param OperatorAccess $operator_access Blacklist operator access policy.
	 */
	public function __construct( EnrollmentPage $page, OperatorAccess $operator_access ) {
		$this->page            = $page;
		$this->operator_access = $operator_access;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_order_row_action' ), 10, 2 );
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_detail_action' ), 10, 2 );
		add_action( 'woocommerce_order_action_wp_buyer_blacklist_enroll', array( $this, 'redirect_order_detail_action' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_order_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_order_bulk_action' ), 10, 3 );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_order_bulk_action' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_order_bulk_action' ), 10, 3 );
	}

	/**
	 * Adds an action to WooCommerce Order rows.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param \WC_Order             $order   Order.
	 * @return array<string, string>
	 */
	public function add_order_row_action( array $actions, \WC_Order $order ): array {
		if ( $this->operator_access->can_manage_entries() ) {
			$actions['wp_buyer_blacklist_enroll'] = array(
				'url'    => $this->page->enrollment_url( 'order', array( $order->get_id() ) ),
				'name'   => __( 'Enroll in Blacklist', 'wp-buyer-blacklist' ),
				'action' => 'wp-buyer-blacklist-enroll',
			);
		}

		return $actions;
	}

	/**
	 * Adds an Order-detail action.
	 *
	 * @param array<string, string> $actions Existing actions.
	 * @param \WC_Order             $order   Order.
	 * @return array<string, string>
	 */
	public function add_order_detail_action( array $actions, \WC_Order $order ): array {
		if ( $order->get_id() <= 0 || ! $this->operator_access->can_manage_entries() ) {
			return $actions;
		}
		$actions['wp_buyer_blacklist_enroll'] = __( 'Enroll in Blacklist', 'wp-buyer-blacklist' );
		return $actions;
	}

	/**
	 * Sends an Order-detail action to the enrollment page.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function redirect_order_detail_action( \WC_Order $order ): void {
		if ( ! $this->operator_access->can_manage_entries() ) {
			wp_die( esc_html__( 'You cannot enroll Blacklist entries.', 'wp-buyer-blacklist' ), 403 );
		}
		wp_safe_redirect( $this->page->enrollment_url( 'order', array( $order->get_id() ) ) );
		exit;
	}

	/**
	 * Adds the Order bulk action.
	 *
	 * @param array<string, string> $actions Existing bulk actions.
	 * @return array<string, string>
	 */
	public function add_order_bulk_action( array $actions ): array {
		if ( $this->operator_access->can_manage_entries() ) {
			$actions['wp_buyer_blacklist_enroll'] = __( 'Enroll in Blacklist', 'wp-buyer-blacklist' );
		}
		return $actions;
	}

	/**
	 * Sends selected Orders to the enrollment page.
	 *
	 * @param string     $redirect_to Redirect URL.
	 * @param string     $action      Bulk action.
	 * @param array<int> $ids        Selected order identifiers.
	 * @return string
	 */
	public function handle_order_bulk_action( string $redirect_to, string $action, array $ids ): string {
		return 'wp_buyer_blacklist_enroll' === $action && $this->operator_access->can_manage_entries() ? $this->page->enrollment_url( 'order', $ids ) : $redirect_to;
	}
}

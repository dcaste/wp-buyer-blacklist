<?php
/**
 * Checkout enforcement.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Enforcement;

use WP_Buyer_Blacklist\Settings\BuyerBlockMessage;
use WP_Error;

/**
 * Blocks matching Buyers at classic checkout and before Store API payment.
 */
final class CheckoutEnforcement {

	/**
	 * Email checkout blocking decision.
	 *
	 * @var EmailCheckoutBlocker
	 */
	private EmailCheckoutBlocker $email_blocker;

	/**
	 * Address checkout blocking decision.
	 *
	 * @var AddressCheckoutBlocker
	 */
	private AddressCheckoutBlocker $address_blocker;

	/**
	 * Message shown to a blocked Buyer.
	 *
	 * @var BuyerBlockMessage
	 */
	private BuyerBlockMessage $buyer_block_message;

	/**
	 * Creates checkout enforcement.
	 *
	 * @param EmailCheckoutBlocker   $email_blocker       Email checkout blocking decision.
	 * @param AddressCheckoutBlocker $address_blocker     Address checkout blocking decision.
	 * @param BuyerBlockMessage      $buyer_block_message Message shown to a blocked Buyer.
	 */
	public function __construct( EmailCheckoutBlocker $email_blocker, AddressCheckoutBlocker $address_blocker, BuyerBlockMessage $buyer_block_message ) {
		$this->email_blocker       = $email_blocker;
		$this->address_blocker     = $address_blocker;
		$this->buyer_block_message = $buyer_block_message;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_classic_checkout' ), 10, 2 );
		add_action( 'woocommerce_checkout_validate_order_before_payment', array( $this, 'validate_store_api_checkout' ), 10, 2 );
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

		if ( $this->email_blocker->blocks( $email ) || $this->blocks_checkout_address( 'billing_address', $data ) || $this->blocks_checkout_address( 'shipping_address', $data ) ) {
			$errors->add( 'wp_buyer_blacklist_blocked', $this->buyer_block_message->current() );
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
		if ( $this->email_blocker->blocks( $order->get_billing_email() ) || $this->address_blocker->blocks( 'billing_address', $billing_address, WC()->countries->get_countries() ) || $this->address_blocker->blocks( 'shipping_address', $shipping_address, WC()->countries->get_countries() ) ) {
			$errors->add( 'wp_buyer_blacklist_blocked', $this->buyer_block_message->current() );
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
}

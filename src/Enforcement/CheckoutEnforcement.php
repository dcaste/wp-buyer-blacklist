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
	 * Address fields of an entry, keyed to WooCommerce's checkout field suffixes.
	 *
	 * @var array<string, string>
	 */
	private const ADDRESS_FIELDS = array(
		'address_line_1' => 'address_1',
		'address_line_2' => 'address_2',
		'city'           => 'city',
		'state'          => 'state',
		'postcode'       => 'postcode',
		'country'        => 'country',
	);

	/**
	 * Email checkout blocking decision.
	 *
	 * @var EmailCheckoutBlocker
	 */
	private EmailCheckoutBlocker $email_blocker;

	/**
	 * Customer-ID checkout blocking decision.
	 *
	 * @var CustomerIdCheckoutBlocker
	 */
	private CustomerIdCheckoutBlocker $customer_id_blocker;

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
	 * @param EmailCheckoutBlocker      $email_blocker       Email checkout blocking decision.
	 * @param CustomerIdCheckoutBlocker $customer_id_blocker Customer-ID checkout blocking decision.
	 * @param AddressCheckoutBlocker    $address_blocker     Address checkout blocking decision.
	 * @param BuyerBlockMessage         $buyer_block_message Message shown to a blocked Buyer.
	 */
	public function __construct( EmailCheckoutBlocker $email_blocker, CustomerIdCheckoutBlocker $customer_id_blocker, AddressCheckoutBlocker $address_blocker, BuyerBlockMessage $buyer_block_message ) {
		$this->email_blocker       = $email_blocker;
		$this->customer_id_blocker = $customer_id_blocker;
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
		$email     = isset( $data['billing_email'] ) && is_string( $data['billing_email'] ) ? $data['billing_email'] : '';
		$read      = static fn( string $key ): mixed => $data[ $key ] ?? '';
		$countries = WC()->countries->get_countries();

		$this->block_if_any(
			$errors,
			array(
				$this->email_blocker->blocks( $email ),
				$this->customer_id_blocker->blocks( get_current_user_id() ),
				$this->blocks_address( 'billing', $read, $countries ),
				$this->blocks_address( 'shipping', $read, $countries ),
			)
		);
	}

	/**
	 * Blocks a matching Buyer before Checkout Block or Store API payment.
	 *
	 * @param \WC_Order $order  Checkout order.
	 * @param WP_Error  $errors Checkout validation errors.
	 * @return void
	 */
	public function validate_store_api_checkout( \WC_Order $order, WP_Error $errors ): void {
		$read      = static fn( string $key ): mixed => $order->{'get_' . $key}();
		$countries = WC()->countries->get_countries();

		$this->block_if_any(
			$errors,
			array(
				$this->email_blocker->blocks( $order->get_billing_email() ),
				$this->customer_id_blocker->blocks( $order->get_customer_id() ),
				$this->blocks_address( 'billing', $read, $countries ),
				$this->blocks_address( 'shipping', $read, $countries ),
			)
		);
	}

	/**
	 * Adds the Buyer block message when any Match type blocked checkout.
	 *
	 * Callers pass every decision already evaluated, not joined with ||,
	 * because each blocker records Blocked attempts as it runs.
	 *
	 * @param WP_Error $errors    Checkout validation errors.
	 * @param bool[]   $decisions One blocking decision per Match type.
	 * @return void
	 */
	private function block_if_any( WP_Error $errors, array $decisions ): void {
		if ( in_array( true, $decisions, true ) ) {
			$errors->add( 'wp_buyer_blacklist_blocked', $this->buyer_block_message->current() );
		}
	}

	/**
	 * Checks one checkout address against address entries.
	 *
	 * @param string                $type      billing or shipping.
	 * @param callable              $read      Returns a checkout field by its WooCommerce key, such as billing_city.
	 * @param array<string, string> $countries WooCommerce countries.
	 * @return bool Whether the address blocks checkout.
	 */
	private function blocks_address( string $type, callable $read, array $countries ): bool {
		$fields = array();
		foreach ( self::ADDRESS_FIELDS as $field => $suffix ) {
			$value            = $read( $type . '_' . $suffix );
			$fields[ $field ] = is_string( $value ) ? $value : '';
		}

		return $this->address_blocker->blocks( $type . '_address', $fields, $countries );
	}
}

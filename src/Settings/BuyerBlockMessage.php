<?php
/**
 * The message a blocked Buyer sees at checkout.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Settings;

/**
 * Reads the configured Buyer block message and its default.
 */
final class BuyerBlockMessage {

	/**
	 * Gets the Buyer block message to show at checkout.
	 *
	 * @return string Configured message, or the default when none is stored.
	 */
	public function current(): string {
		$default = $this->default();
		$message = get_option( 'wp_buyer_blacklist_buyer_message', $default );

		return is_string( $message ) && '' !== $message ? $message : $default;
	}

	/**
	 * Gets the translated default Buyer block message.
	 *
	 * @return string
	 */
	public function default(): string {
		return __( 'We’re unable to process your order. Please contact us for assistance.', 'wp-buyer-blacklist' );
	}
}

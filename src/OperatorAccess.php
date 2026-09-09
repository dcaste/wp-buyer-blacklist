<?php
/**
 * Determines who may operate the Blacklist.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

/**
 * Applies the administrator-controlled Blacklist operator policy.
 */
final class OperatorAccess {

	/**
	 * Checks whether the current user may manage Blacklist entries.
	 *
	 * @return bool Whether the current user is a Blacklist operator.
	 */
	public function can_manage_entries(): bool {
		return current_user_can( 'manage_options' ) || ( $this->woocommerce_managers_allowed() && current_user_can( 'manage_woocommerce' ) );
	}

	/**
	 * Gets the WordPress capability used to register operator screens.
	 *
	 * @return string Capability for the current policy.
	 */
	public function menu_capability(): string {
		return ! $this->woocommerce_managers_allowed() || current_user_can( 'manage_options' ) ? 'manage_options' : 'manage_woocommerce';
	}

	/**
	 * Checks whether WooCommerce managers may manage Blacklist entries.
	 *
	 * @return bool Whether WooCommerce managers are allowed.
	 */
	private function woocommerce_managers_allowed(): bool {
		return (bool) get_option( 'wp_buyer_blacklist_allow_woocommerce_managers', true );
	}
}

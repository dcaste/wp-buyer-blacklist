<?php
/**
 * Settings tab of the admin page.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Settings;

/**
 * Renders the settings form and its message preview script.
 */
final class SettingsTab {

	/**
	 * Buyer block message.
	 *
	 * @var BuyerBlockMessage
	 */
	private BuyerBlockMessage $buyer_block_message;

	/**
	 * Creates the Settings tab.
	 *
	 * @param BuyerBlockMessage $buyer_block_message Buyer block message.
	 */
	public function __construct( BuyerBlockMessage $buyer_block_message ) {
		$this->buyer_block_message = $buyer_block_message;
	}

	/**
	 * Renders the settings form and attaches its message preview script.
	 *
	 * @return void
	 */
	public function render(): void {
		wp_add_inline_script(
			'wp-buyer-blacklist-admin',
			"document.getElementById('wp-buyer-blacklist-buyer-message').addEventListener('input', function () { document.getElementById('wp-buyer-blacklist-message-preview').textContent = this.value || '" . esc_js( $this->buyer_block_message->default() ) . "'; });"
		);

		// The template runs in a static closure so it sees only these values, not $this.
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- The required template reads every parameter.
		( static function ( bool $settings_reset, mixed $buyer_message, mixed $allow_woocommerce_managers, mixed $delete_data_on_uninstall ): void {
			require WP_BUYER_BLACKLIST_DIR . 'templates/settings/page.php';
		} )(
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This displays a result from the nonce-protected reset action.
			isset( $_GET['wp_buyer_blacklist_settings'] ) && 'reset' === sanitize_key( wp_unslash( $_GET['wp_buyer_blacklist_settings'] ) ),
			get_option( 'wp_buyer_blacklist_buyer_message', $this->buyer_block_message->default() ),
			get_option( 'wp_buyer_blacklist_allow_woocommerce_managers', true ),
			get_option( 'wp_buyer_blacklist_delete_data_on_uninstall', false )
		);
	}
}

<?php
/**
 * Buyer Blacklist settings.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Settings;

/**
 * Registers the plugin settings and restores their defaults.
 */
final class BlacklistSettings {

	/**
	 * Buyer block message.
	 *
	 * @var BuyerBlockMessage
	 */
	private BuyerBlockMessage $buyer_block_message;

	/**
	 * Creates the settings module.
	 *
	 * @param BuyerBlockMessage $buyer_block_message Buyer block message.
	 */
	public function __construct( BuyerBlockMessage $buyer_block_message ) {
		$this->buyer_block_message = $buyer_block_message;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wp_buyer_blacklist_reset_settings', array( $this, 'reset_settings' ) );
	}

	/**
	 * Registers the plugin settings.
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
				'default'           => $this->buyer_block_message->default(),
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

		return '' === trim( $message ) ? $this->buyer_block_message->default() : $message;
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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to reset Blacklist settings.', 'wp-buyer-blacklist' ), 403 );
		}

		check_admin_referer( 'wp_buyer_blacklist_reset_settings' );
		$this->reset_to_default_settings();
		// Queue the confirmation the way options.php does after a save, so the Settings tab shows it through settings_errors().
		add_settings_error( 'wp_buyer_blacklist', 'settings_reset', __( 'Settings restored to their defaults.', 'wp-buyer-blacklist' ), 'success' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', admin_url( 'admin.php?page=wp-buyer-blacklist&tab=settings' ) ) );
		exit;
	}
}

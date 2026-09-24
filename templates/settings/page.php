<?php
/**
 * Settings tab of the Buyer Blacklist admin page.
 *
 * @package WP_Buyer_Blacklist
 *
 * Option values arrive as stored, so checked() compares them the way WordPress does.
 *
 * @var bool   $settings_reset             Whether the settings were just restored to their defaults.
 * @var mixed  $buyer_message              Stored Buyer block message.
 * @var mixed  $allow_woocommerce_managers Stored "allow WooCommerce managers" option.
 * @var mixed  $delete_data_on_uninstall   Stored "delete data on uninstall" option.
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( $settings_reset ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings restored to their defaults.', 'wp-buyer-blacklist' ); ?></p></div>
<?php endif; ?>
<form class="wpbb-settings" action="options.php" method="post">
	<?php settings_fields( 'wp_buyer_blacklist' ); ?>
	<section class="wpbb-settings-card wpbb-settings-message-card">
		<header>
			<h2><?php esc_html_e( 'Buyer-facing block message', 'wp-buyer-blacklist' ); ?></h2>
			<p class="description"><?php esc_html_e( 'This message is shown when a Buyer is blocked from placing an order.', 'wp-buyer-blacklist' ); ?></p>
		</header>
		<div class="wpbb-settings-message-content">
			<div>
				<p><label for="wp-buyer-blacklist-buyer-message"><?php esc_html_e( 'Message', 'wp-buyer-blacklist' ); ?></label><textarea aria-describedby="wp-buyer-blacklist-buyer-message-description" class="large-text" id="wp-buyer-blacklist-buyer-message" name="wp_buyer_blacklist_buyer_message" rows="3"><?php echo esc_textarea( $buyer_message ); ?></textarea></p>
				<p class="description" id="wp-buyer-blacklist-buyer-message-description"><?php esc_html_e( 'Keep the message clear and professional. Explain what the Buyer can do next.', 'wp-buyer-blacklist' ); ?></p>
			</div>
			<div class="wpbb-settings-preview">
				<strong><?php esc_html_e( 'Preview', 'wp-buyer-blacklist' ); ?></strong>
				<p class="description"><?php esc_html_e( 'This is how the message appears to a blocked Buyer during checkout.', 'wp-buyer-blacklist' ); ?></p>
				<div class="wpbb-message-preview" aria-live="polite"><span class="wpbb-message-preview-icon" aria-hidden="true">!</span><p id="wp-buyer-blacklist-message-preview"><?php echo esc_html( $buyer_message ); ?></p></div>
			</div>
		</div>
	</section>
	<section class="wpbb-settings-card wpbb-settings-permissions-card">
		<header>
			<h2><?php esc_html_e( 'Permissions', 'wp-buyer-blacklist' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Control who can manage the Blacklist in your store.', 'wp-buyer-blacklist' ); ?></p>
		</header>
		<input name="wp_buyer_blacklist_allow_woocommerce_managers" type="hidden" value="0" />
		<label class="wpbb-toggle"><input aria-describedby="wp-buyer-blacklist-allow-woocommerce-managers-description" id="wp-buyer-blacklist-allow-woocommerce-managers" name="wp_buyer_blacklist_allow_woocommerce_managers" type="checkbox" value="1" <?php checked( $allow_woocommerce_managers ); ?> /> <?php esc_html_e( 'Allow WooCommerce managers to manage Blacklist entries', 'wp-buyer-blacklist' ); ?></label>
		<p class="description" id="wp-buyer-blacklist-allow-woocommerce-managers-description"><?php esc_html_e( 'Gives users with WooCommerce management permission access to entries, CSV imports, and Blocked attempts.', 'wp-buyer-blacklist' ); ?></p>
	</section>
	<section class="wpbb-settings-card wpbb-settings-cleanup-card">
		<header>
			<h2><?php esc_html_e( 'Data and cleanup', 'wp-buyer-blacklist' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Manage what happens to Blacklist data when the plugin is removed.', 'wp-buyer-blacklist' ); ?></p>
		</header>
		<input name="wp_buyer_blacklist_delete_data_on_uninstall" type="hidden" value="0" />
		<label class="wpbb-toggle"><input aria-describedby="wp-buyer-blacklist-delete-data-description" id="wp-buyer-blacklist-delete-data" name="wp_buyer_blacklist_delete_data_on_uninstall" type="checkbox" value="1" <?php checked( $delete_data_on_uninstall ); ?> /> <?php esc_html_e( 'Delete all Blacklist data when the plugin is uninstalled', 'wp-buyer-blacklist' ); ?></label>
		<p class="description" id="wp-buyer-blacklist-delete-data-description"><?php esc_html_e( 'Permanently deletes all Blacklist entries, import history, and Blocked attempt data when the plugin is uninstalled. This cannot be undone.', 'wp-buyer-blacklist' ); ?></p>
		<div class="wpbb-settings-warning">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<div>
				<strong><?php esc_html_e( 'This will permanently delete your data.', 'wp-buyer-blacklist' ); ?></strong>
				<p><?php esc_html_e( 'Export your entries first if you need a backup before uninstalling the plugin.', 'wp-buyer-blacklist' ); ?></p>
			</div>
		</div>
	</section>
	<div class="wpbb-settings-actions">
		<?php submit_button( __( 'Save changes', 'wp-buyer-blacklist' ), 'primary', 'submit', false ); ?><button class="button" type="submit" form="wp-buyer-blacklist-reset-settings" onclick="return window.confirm('<?php echo esc_js( __( 'Reset all Buyer Blacklist settings to their defaults?', 'wp-buyer-blacklist' ) ); ?>');"><span class="wpbb-settings-reset-icon" aria-hidden="true"></span><?php esc_html_e( 'Reset to defaults', 'wp-buyer-blacklist' ); ?></button>
	</div>
</form>
<?php // The Reset button sits in the settings form's action row but submits this form, so each form posts only its own nonce. ?>
<form id="wp-buyer-blacklist-reset-settings" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
	<input type="hidden" name="action" value="wp_buyer_blacklist_reset_settings" />
	<?php wp_nonce_field( 'wp_buyer_blacklist_reset_settings' ); ?>
</form>

<?php
/**
 * Users-list enrollment and manage dialogs.
 *
 * @package WP_Buyer_Blacklist
 */

defined( 'ABSPATH' ) || exit;
?>
		<dialog id="wp-buyer-blacklist-enroll-customer-dialog" aria-labelledby="wp-buyer-blacklist-enroll-customer-title">
			<button type="button" class="wpbb-dialog-close" aria-label="<?php esc_attr_e( 'Close dialog', 'wp-buyer-blacklist' ); ?>" commandfor="wp-buyer-blacklist-enroll-customer-dialog" command="close"><span class="wpbb-modal-icon is-close" aria-hidden="true"></span></button>
			<form class="wp-buyer-blacklist-enrollment-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<h2 id="wp-buyer-blacklist-enroll-customer-title"><?php esc_html_e( 'Enroll Buyer in Blacklist', 'wp-buyer-blacklist' ); ?></h2>
				<?php wp_nonce_field( 'wp_buyer_blacklist_enroll_customer' ); ?>
				<input type="hidden" name="action" value="wp_buyer_blacklist_enroll_customer">
				<input type="hidden" name="customer_id" value="">
				<h3><?php esc_html_e( 'Current Buyer', 'wp-buyer-blacklist' ); ?></h3>
				<dl>
					<dt><?php esc_html_e( 'Name', 'wp-buyer-blacklist' ); ?></dt><dd data-buyer-field="name"></dd>
					<dt><?php esc_html_e( 'WordPress user ID', 'wp-buyer-blacklist' ); ?></dt><dd data-buyer-field="id"></dd>
					<dt><?php esc_html_e( 'Email', 'wp-buyer-blacklist' ); ?></dt><dd data-buyer-field="email"></dd>
					<dt><?php esc_html_e( 'Billing address', 'wp-buyer-blacklist' ); ?></dt><dd data-buyer-field="billing_address"></dd>
					<dt><?php esc_html_e( 'Shipping address', 'wp-buyer-blacklist' ); ?></dt><dd data-buyer-field="shipping_address"></dd>
				</dl>
				<p><label for="wp-buyer-blacklist-customer-match-type"><?php esc_html_e( 'Match type', 'wp-buyer-blacklist' ); ?></label><select id="wp-buyer-blacklist-customer-match-type" name="match_type" required></select></p>
				<p><label for="wp-buyer-blacklist-customer-reason"><?php esc_html_e( 'Reason', 'wp-buyer-blacklist' ); ?></label><textarea class="large-text" id="wp-buyer-blacklist-customer-reason" name="reason" required></textarea></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create entry', 'wp-buyer-blacklist' ); ?></button> <button type="button" class="button" data-close-dialog><?php esc_html_e( 'Cancel', 'wp-buyer-blacklist' ); ?></button></p>
			</form>
		</dialog>
		<dialog id="wp-buyer-blacklist-manage-customer-dialog" aria-labelledby="wp-buyer-blacklist-manage-customer-title">
			<button type="button" class="wpbb-dialog-close" aria-label="<?php esc_attr_e( 'Close dialog', 'wp-buyer-blacklist' ); ?>" commandfor="wp-buyer-blacklist-manage-customer-dialog" command="close"><span class="wpbb-modal-icon is-close" aria-hidden="true"></span></button>
			<h2 id="wp-buyer-blacklist-manage-customer-title"><?php esc_html_e( 'Manage Blacklist entry', 'wp-buyer-blacklist' ); ?></h2>
			<p><label for="wp-buyer-blacklist-matching-entry"><?php esc_html_e( 'Matching Blacklist entry', 'wp-buyer-blacklist' ); ?></label><select id="wp-buyer-blacklist-matching-entry"></select></p>
			<div data-buyer-context></div>
			<p><label for="wp-buyer-blacklist-manage-reason"><?php esc_html_e( 'Reason', 'wp-buyer-blacklist' ); ?></label><textarea class="large-text" id="wp-buyer-blacklist-manage-reason" required></textarea></p>
			<p><button type="button" class="button button-primary" data-save-entry><?php esc_html_e( 'Save reason', 'wp-buyer-blacklist' ); ?></button> <button type="button" class="button" data-toggle-entry></button></p>
			<p role="status" data-manage-result></p>
		</dialog>
		<?php

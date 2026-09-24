<?php
/**
 * Enrollment confirmation form for selected Customers or Orders.
 *
 * @package WP_Buyer_Blacklist
 *
 * @var string   $source_type   Source type: customer or order.
 * @var int[]    $ids           Selected source identifiers.
 * @var callable $render_result Prints the result of the previous submission.
 */

defined( 'ABSPATH' ) || exit;
?>
		<div class="wrap"><h1><?php esc_html_e( 'Enroll in Blacklist', 'wp-buyer-blacklist' ); ?></h1><?php $render_result(); ?><form class="wp-buyer-blacklist-enrollment-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wp_buyer_blacklist_enroll_source' ); ?><input type="hidden" name="action" value="wp_buyer_blacklist_enroll_source"><input type="hidden" name="source_type" value="<?php echo esc_attr( $source_type ); ?>">
			<?php
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Templates are required from a closure, so this loop variable is local.
			foreach ( $ids as $source_id ) :
				?>
				<input type="hidden" name="source_ids[]" value="<?php echo esc_attr( (string) $source_id ); ?>"><?php endforeach; ?>
			<p><label><?php esc_html_e( 'Match type', 'wp-buyer-blacklist' ); ?><select name="match_type"><option value="email"><?php esc_html_e( 'Email', 'wp-buyer-blacklist' ); ?></option><option value="customer_id"><?php esc_html_e( 'Customer ID', 'wp-buyer-blacklist' ); ?></option><option value="billing_address"><?php esc_html_e( 'Billing address', 'wp-buyer-blacklist' ); ?></option><option value="shipping_address"><?php esc_html_e( 'Shipping address', 'wp-buyer-blacklist' ); ?></option></select></label></p>
			<p><label><?php esc_html_e( 'Reason', 'wp-buyer-blacklist' ); ?><textarea class="large-text" name="reason" required></textarea></label></p><p><?php esc_html_e( 'Email and Customer ID need a value. Addresses need every address field. Sources without the selected value are skipped.', 'wp-buyer-blacklist' ); ?></p><p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create entries', 'wp-buyer-blacklist' ); ?></button></p>
		</form></div>
		<?php

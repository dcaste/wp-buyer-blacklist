<?php
/**
 * Users-list cell for a Customer with matching Blacklist entries.
 *
 * @package WP_Buyer_Blacklist
 *
 * @var array<string, mixed>       $buyer   Current Buyer data for the dialogs.
 * @var list<array<string, string>> $entries Matching Blacklist entries.
 * @var bool                        $active  Whether any matching entry is active.
 */

defined( 'ABSPATH' ) || exit;
?>
<span class="wp-buyer-blacklist-status-badge <?php echo $active ? 'is-active' : 'is-inactive'; ?>"<?php echo $active ? ' tabindex="0" aria-label="' . esc_attr__( 'Blacklisted. This Buyer cannot place new orders.', 'wp-buyer-blacklist' ) . '" data-wpbb-tooltip="' . esc_attr__( 'This Buyer is blacklisted from placing new orders in your store.', 'wp-buyer-blacklist' ) . '"' : ''; ?>><?php $active ? esc_html_e( 'Blacklisted', 'wp-buyer-blacklist' ) : esc_html_e( 'Inactive', 'wp-buyer-blacklist' ); ?></span><br><button type="button" class="button-link wp-buyer-blacklist-manage-customer" data-buyer="<?php echo esc_attr( wp_json_encode( $buyer ) ); ?>" data-entries="<?php echo esc_attr( wp_json_encode( $entries ) ); ?>"><?php esc_html_e( 'Manage', 'wp-buyer-blacklist' ); ?></button><?php

<?php
/**
 * Users-list cell for a Customer with no matching Blacklist entry.
 *
 * @package WP_Buyer_Blacklist
 *
 * @var array<string, mixed> $buyer Current Buyer data for the enrollment dialog.
 */

defined( 'ABSPATH' ) || exit;
?>
<button type="button" class="button-link wp-buyer-blacklist-enroll-customer" data-buyer="<?php echo esc_attr( wp_json_encode( $buyer ) ); ?>"><?php esc_html_e( 'Enroll', 'wp-buyer-blacklist' ); ?></button><?php

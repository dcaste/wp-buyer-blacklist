<?php
/**
 * Enrollment row on a Customer's profile screen.
 *
 * @package WP_Buyer_Blacklist
 *
 * @var string $enrollment_url Enrollment page URL for this Customer.
 */

defined( 'ABSPATH' ) || exit;
?>
<tr><th><?php esc_html_e( 'Buyer Blacklist', 'wp-buyer-blacklist' ); ?></th><td><a href="<?php echo esc_attr( esc_url_raw( $enrollment_url ) ); ?>"><?php esc_html_e( 'Enroll in Blacklist', 'wp-buyer-blacklist' ); ?></a></td></tr><?php

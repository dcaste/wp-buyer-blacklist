<?php
/**
 * Result of a Users-list enrollment.
 *
 * @package WP_Buyer_Blacklist
 *
 * @var string $type    Notice type: success, warning or error.
 * @var string $message Translated result message.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div><?php

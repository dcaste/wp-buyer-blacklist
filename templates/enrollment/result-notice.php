<?php
/**
 * Result of the previous enrollment page submission.
 *
 * @package WP_Buyer_Blacklist
 *
 * @var int $created     Entries created.
 * @var int $duplicates  Duplicate entries skipped.
 * @var int $unavailable Sources skipped for lack of a usable value.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="notice notice-success"><p><?php echo esc_html( sprintf( /* translators: 1: entries created, 2: duplicate entries skipped, 3: unavailable sources skipped. */ __( '%1$d created, %2$d duplicates skipped, %3$d unavailable sources skipped.', 'wp-buyer-blacklist' ), $created, $duplicates, $unavailable ) ); ?></p></div><?php

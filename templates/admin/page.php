<?php
/**
 * Buyer Blacklist admin page shell with its section tabs.
 *
 * @package WP_Buyer_Blacklist
 *
 * @var string   $tab                 Selected tab: manage, import-export or settings.
 * @var bool     $can_manage_settings Whether the current user may open the Settings tab.
 * @var callable $render_settings_tab Prints the Settings tab.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Buyer Blacklist', 'wp-buyer-blacklist' ); ?></h1>
	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Buyer Blacklist sections', 'wp-buyer-blacklist' ); ?>">
		<a class="nav-tab <?php echo 'manage' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-buyer-blacklist' ) ); ?>"><?php esc_html_e( 'Manage entries', 'wp-buyer-blacklist' ); ?></a>
		<a class="nav-tab <?php echo 'import-export' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-buyer-blacklist&tab=import-export' ) ); ?>"><?php esc_html_e( 'Import and export', 'wp-buyer-blacklist' ); ?></a>
		<?php if ( $can_manage_settings ) : ?>
			<a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-buyer-blacklist&tab=settings' ) ); ?>"><?php esc_html_e( 'Settings', 'wp-buyer-blacklist' ); ?></a>
		<?php endif; ?>
	</nav>
	<?php if ( 'settings' === $tab ) : ?>
		<?php $render_settings_tab(); ?>
	<?php else : ?>
		<div id="wp-buyer-blacklist-admin" data-tab="<?php echo esc_attr( $tab ); ?>" data-export-url="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wp_buyer_blacklist_export' ), 'wp_buyer_blacklist_export' ) ); ?>" data-users-url="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" data-example-csv-url="<?php echo esc_url( WP_BUYER_BLACKLIST_URL . 'assets/buyer-blacklist-example.csv' ); ?>" data-dossier-export-url="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wp_buyer_blacklist_dossier_export' ), 'wp_buyer_blacklist_dossier_export' ) ); ?>"></div>
	<?php endif; ?>
</div>

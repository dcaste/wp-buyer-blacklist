<?php
/**
 * Fires admin_init inside a test.
 *
 * @package WP_Buyer_Blacklist
 */

/**
 * Fires admin_init without the side effects that break under PHPUnit.
 */
trait AdminInit {

	/**
	 * Fires admin_init so the plugin registers its settings.
	 *
	 * @return void
	 */
	private function run_admin_init(): void {
		// Core sends a Referrer-Policy header here, which fails once PHPUnit has printed output.
		remove_action( 'admin_init', 'wp_admin_headers' );
		// WooCommerce adds its privacy policy text on admin_init, which WordPress rejects outside wp-admin.
		$this->setExpectedIncorrectUsage( 'wp_add_privacy_policy_content' );
		do_action( 'admin_init' );
	}
}

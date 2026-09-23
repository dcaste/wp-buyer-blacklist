<?php
/**
 * Renders wp-admin screens inside a test.
 *
 * @package WP_Buyer_Blacklist
 */

/**
 * Builds the admin menu and renders plugin pages through their page hooks.
 */
trait AdminScreens {

	/**
	 * Builds the admin menu for the current user, with WooCommerce's top-level menu in place.
	 *
	 * @return void
	 */
	private function build_admin_menu(): void {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Each build starts from an empty admin menu.
		$GLOBALS['menu']               = array();
		$GLOBALS['submenu']            = array();
		$GLOBALS['_wp_submenu_nopriv'] = array();
		// WooCommerce registers this top-level menu in wp-admin; it gives submenu pages their woocommerce_page_ hook names.
		$GLOBALS['admin_page_hooks'] = array( 'woocommerce' => 'woocommerce' );
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
		do_action( 'admin_menu', '' );
	}

	/**
	 * Renders a registered admin page as the current user.
	 *
	 * @param string               $page_hook Page hook name, such as woocommerce_page_wp-buyer-blacklist.
	 * @param array<string, mixed> $query     Query parameters of the page request.
	 * @return string Rendered markup.
	 */
	private function render_admin_page( string $page_hook, array $query = array() ): string {
		$this->build_admin_menu();
		$_GET = $query;

		try {
			return $this->render_at(
				'/wp-admin/admin.php?' . http_build_query( $query ),
				static function () use ( $page_hook ): void {
					do_action( $page_hook );
				}
			);
		} finally {
			$_GET = array();
		}
	}

	/**
	 * Captures what a callback prints while the request URI is set to a given path.
	 *
	 * @param string   $request_uri Request URI, which nonce fields use as their referer.
	 * @param callable $render      Code that prints markup.
	 * @return string Printed markup.
	 */
	private function render_at( string $request_uri, callable $render ): string {
		$previous               = $_SERVER['REQUEST_URI'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Restored unchanged below.
		$_SERVER['REQUEST_URI'] = $request_uri;

		ob_start();
		try {
			$render();
		} finally {
			$markup = (string) ob_get_clean();
			if ( null === $previous ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $previous;
			}
		}

		return $markup;
	}
}

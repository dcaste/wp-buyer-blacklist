<?php
/**
 * Runs admin-post.php actions inside a test.
 *
 * @package WP_Buyer_Blacklist
 */

/**
 * Submits admin-post actions and captures the redirect that ends them.
 */
trait AdminPostRequests {

	/**
	 * Submits an admin-post action as the current user.
	 *
	 * @param string               $action admin-post action name.
	 * @param array<string, mixed> $fields Posted form fields.
	 * @return string The URL the handler redirected to.
	 */
	private function submit_admin_post( string $action, array $fields ): string {
		$_POST    = $fields;
		$_REQUEST = $fields;

		try {
			return $this->capture_redirect(
				static function () use ( $action ): void {
					do_action( 'admin_post_' . $action );
				}
			);
		} finally {
			$_POST    = array();
			$_REQUEST = array();
		}
	}

	/**
	 * Runs a handler that redirects and exits, and returns where it redirected.
	 *
	 * @param callable $handler Code that ends in wp_safe_redirect() and exit.
	 * @return string The redirect location.
	 */
	private function capture_redirect( callable $handler ): string {
		// Handlers exit after redirecting, so stop them at the redirect instead.
		$stop_at_redirect = static function ( string $location ): string {
			throw new RedirectException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The location never reaches output.
		};
		add_filter( 'wp_redirect', $stop_at_redirect );

		try {
			$handler();
		} catch ( RedirectException $redirect ) {
			return $redirect->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $stop_at_redirect );
		}

		$this->fail( 'The handler did not redirect.' );
	}
}

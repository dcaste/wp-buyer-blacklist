<?php
/**
 * Standalone Blacklist enrollment page.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Enrollment;

use WP_Buyer_Blacklist\Access\OperatorAccess;

/**
 * Confirms and creates entries for Customers or Orders selected on another screen.
 */
final class EnrollmentPage {

	/**
	 * Shared enrollment operation.
	 *
	 * @var Enroller
	 */
	private Enroller $enroller;

	/**
	 * Blacklist operator access policy.
	 *
	 * @var OperatorAccess
	 */
	private OperatorAccess $operator_access;

	/**
	 * Creates the page.
	 *
	 * @param Enroller       $enroller        Shared enrollment operation.
	 * @param OperatorAccess $operator_access Blacklist operator access policy.
	 */
	public function __construct( Enroller $enroller, OperatorAccess $operator_access ) {
		$this->enroller        = $enroller;
		$this->operator_access = $operator_access;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_wp_buyer_blacklist_enroll_source', array( $this, 'create_entries' ) );
	}

	/**
	 * Registers the hidden enrollment page.
	 *
	 * @return void
	 */
	public function register_page(): void {
		add_submenu_page( null, __( 'Enroll in Blacklist', 'wp-buyer-blacklist' ), __( 'Enroll in Blacklist', 'wp-buyer-blacklist' ), $this->operator_access->menu_capability(), 'wp-buyer-blacklist-enroll', array( $this, 'render_page' ) );
	}

	/**
	 * Renders source enrollment confirmation.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! $this->operator_access->can_manage_entries() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This request only renders an enrollment confirmation form.
		$source_type = isset( $_GET['source_type'] ) && is_string( $_GET['source_type'] ) ? sanitize_key( wp_unslash( $_GET['source_type'] ) ) : '';
		$ids         = $this->source_ids_from_request();
		if ( ! in_array( $source_type, array( 'customer', 'order' ), true ) || array() === $ids ) {
			( static function (): void {
				require WP_BUYER_BLACKLIST_DIR . 'templates/enrollment/no-sources.php';
			} )();
			return;
		}

		// The template runs in a static closure so it sees only these values, not $this.
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- The required template reads every parameter.
		( static function ( string $source_type, array $ids, callable $render_result ): void {
			require WP_BUYER_BLACKLIST_DIR . 'templates/enrollment/page.php';
		} )( $source_type, $ids, $this->render_result( ... ) );
	}

	/**
	 * Renders the result of a source enrollment submission.
	 *
	 * @return void
	 */
	private function render_result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These values only display the prior submission result.
		if ( ! isset( $_GET['created'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These values only display the prior submission result.
		$created = absint( $_GET['created'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These values only display the prior submission result.
		$duplicates = absint( $_GET['duplicates'] ?? 0 );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These values only display the prior submission result.
		$unavailable = absint( $_GET['unavailable'] ?? 0 );
		// The template runs in a static closure so it sees only these values, not $this.
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- The required template reads every parameter.
		( static function ( int $created, int $duplicates, int $unavailable ): void {
			require WP_BUYER_BLACKLIST_DIR . 'templates/enrollment/result-notice.php';
		} )( $created, $duplicates, $unavailable );
	}

	/**
	 * Creates entries and reports the bulk result.
	 *
	 * @return void
	 */
	public function create_entries(): void {
		if ( ! $this->operator_access->can_manage_entries() ) {
			wp_die( esc_html__( 'You cannot enroll Blacklist entries.', 'wp-buyer-blacklist' ) );
		}
		check_admin_referer( 'wp_buyer_blacklist_enroll_source' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer above validates this form submission.
		$source_type = isset( $_POST['source_type'] ) && is_string( $_POST['source_type'] ) ? sanitize_key( wp_unslash( $_POST['source_type'] ) ) : '';
		$match_type  = isset( $_POST['match_type'] ) && is_string( $_POST['match_type'] ) ? sanitize_key( wp_unslash( $_POST['match_type'] ) ) : '';
		$reason      = isset( $_POST['reason'] ) && is_string( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$created     = 0;
		$duplicates  = 0;
		$unavailable = 0;
		$source_ids  = $this->source_ids_from_request( $_POST );
		foreach ( $source_ids as $source_id ) {
			$result = $this->enroller->create_entry( $source_type, $source_id, $match_type, $reason );
			if ( 'created' === $result ) {
				++$created;
			} elseif ( 'duplicate' === $result ) {
				++$duplicates;
			} else {
				++$unavailable;
			}
		}
		$url = add_query_arg(
			array(
				'page'        => 'wp-buyer-blacklist-enroll',
				'source_type' => $source_type,
				'source_ids'  => $source_ids,
				'created'     => $created,
				'duplicates'  => $duplicates,
				'unavailable' => $unavailable,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Builds an enrollment-page URL.
	 *
	 * @param string     $source_type Source type.
	 * @param array<int> $ids        Source identifiers.
	 * @return string
	 */
	public function enrollment_url( string $source_type, array $ids ): string {
		return add_query_arg(
			array(
				'page'        => 'wp-buyer-blacklist-enroll',
				'source_type' => $source_type,
				'source_ids'  => array_map( 'absint', $ids ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Gets valid source IDs from request data.
	 *
	 * @param array<string, mixed>|null $request Request data.
	 * @return list<int>
	 */
	private function source_ids_from_request( ?array $request = null ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET requests only select sources for the confirmation form.
		$request = $request ?? $_GET;
		$ids     = isset( $request['source_ids'] ) && is_array( $request['source_ids'] ) ? $request['source_ids'] : array();
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}
}

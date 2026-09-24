<?php
/**
 * Users screen enrollment.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist\Enrollment;

use WP_Buyer_Blacklist\Access\OperatorAccess;
use WP_Buyer_Blacklist\EmailEntryRepository;

/**
 * Adds Blacklist status, enrollment and management to the Users list and user profiles.
 */
final class CustomerScreen {

	/**
	 * Entry storage.
	 *
	 * @var EmailEntryRepository
	 */
	private EmailEntryRepository $entries;

	/**
	 * Shared enrollment operation.
	 *
	 * @var Enroller
	 */
	private Enroller $enroller;

	/**
	 * Standalone enrollment page.
	 *
	 * @var EnrollmentPage
	 */
	private EnrollmentPage $page;

	/**
	 * Blacklist operator access policy.
	 *
	 * @var OperatorAccess
	 */
	private OperatorAccess $operator_access;

	/**
	 * Creates the Users screen enrollment.
	 *
	 * @param EmailEntryRepository $entries         Entry storage.
	 * @param Enroller             $enroller        Shared enrollment operation.
	 * @param EnrollmentPage       $page            Standalone enrollment page.
	 * @param OperatorAccess       $operator_access Blacklist operator access policy.
	 */
	public function __construct( EmailEntryRepository $entries, Enroller $enroller, EnrollmentPage $page, OperatorAccess $operator_access ) {
		$this->entries         = $entries;
		$this->enroller        = $enroller;
		$this->page            = $page;
		$this->operator_access = $operator_access;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'manage_users_columns', array( $this, 'add_customer_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_customer_column' ), 10, 3 );
		add_action( 'admin_footer-users.php', array( $this, 'render_customer_enrollment_modal' ) );
		add_action( 'admin_notices', array( $this, 'render_customer_enrollment_notice' ) );
		add_filter( 'bulk_actions-users', array( $this, 'add_customer_bulk_action' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_customer_bulk_action' ), 10, 3 );
		add_action( 'show_user_profile', array( $this, 'render_customer_detail_action' ) );
		add_action( 'edit_user_profile', array( $this, 'render_customer_detail_action' ) );
		add_action( 'admin_post_wp_buyer_blacklist_enroll_customer', array( $this, 'create_customer_entry' ) );
	}

	/**
	 * Adds the Blacklist status column to the Users list.
	 *
	 * @param array<string, string> $columns Existing list columns.
	 * @return array<string, string> List columns.
	 */
	public function add_customer_column( array $columns ): array {
		$columns['wp_buyer_blacklist'] = __( 'Buyer Blacklist', 'wp-buyer-blacklist' );
		return $columns;
	}

	/**
	 * Renders the Blacklist status for a WooCommerce Customer.
	 *
	 * @param string $output  Existing cell markup.
	 * @param string $column  Current column name.
	 * @param int    $user_id WordPress user ID.
	 * @return string Cell markup.
	 */
	public function render_customer_column( string $output, string $column, int $user_id ): string {
		if ( 'wp_buyer_blacklist' !== $column || ! $this->operator_access->can_manage_entries() ) {
			return $output;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User || ! in_array( 'customer', $user->roles, true ) ) {
			return $output;
		}

		$entries = $this->enroller->matching_customer_entries( $user_id );
		if ( array() === $entries ) {
			return '<button type="button" class="button-link wp-buyer-blacklist-enroll-customer" data-buyer="' . esc_attr( wp_json_encode( $this->customer_modal_data( $user_id ) ) ) . '">' . esc_html__( 'Enroll', 'wp-buyer-blacklist' ) . '</button>';
		}
		$entry_ids = array_column( $entries, 'id' );
		$entries   = array_values(
			array_filter(
				$this->entries->all(),
				static fn( array $entry ): bool => in_array( $entry['id'], $entry_ids, true )
			)
		);

		$active = in_array( '1', array_column( $entries, 'is_active' ), true );
		$status = $active ? __( 'Blacklisted', 'wp-buyer-blacklist' ) : __( 'Inactive', 'wp-buyer-blacklist' );

		$description = $active ? ' tabindex="0" aria-label="' . esc_attr__( 'Blacklisted. This Buyer cannot place new orders.', 'wp-buyer-blacklist' ) . '" data-wpbb-tooltip="' . esc_attr__( 'This Buyer is blacklisted from placing new orders in your store.', 'wp-buyer-blacklist' ) . '"' : '';
		return '<span class="wp-buyer-blacklist-status-badge ' . ( $active ? 'is-active' : 'is-inactive' ) . '"' . $description . '>' . esc_html( $status ) . '</span><br><button type="button" class="button-link wp-buyer-blacklist-manage-customer" data-buyer="' . esc_attr( wp_json_encode( $this->customer_modal_data( $user_id ) ) ) . '" data-entries="' . esc_attr( wp_json_encode( $entries ) ) . '">' . esc_html__( 'Manage', 'wp-buyer-blacklist' ) . '</button>';
	}

	/**
	 * Renders the Users-list enrollment modal.
	 *
	 * @return void
	 */
	public function render_customer_enrollment_modal(): void {
		if ( ! $this->operator_access->can_manage_entries() ) {
			return;
		}

		// The template runs in a static closure so it cannot reach $this.
		( static function (): void {
			require WP_BUYER_BLACKLIST_DIR . 'templates/enrollment/customer-modal.php';
		} )();
	}

	/**
	 * Creates an entry from a current WooCommerce Customer.
	 *
	 * @param int    $customer_id WordPress user ID.
	 * @param string $match_type  Selected Match type.
	 * @param string $reason      Operator reason.
	 * @return string created, duplicate, unavailable, or denied.
	 */
	public function enroll_customer( int $customer_id, string $match_type, string $reason ): string {
		if ( ! $this->operator_access->can_manage_entries() ) {
			return 'denied';
		}

		$user = get_userdata( $customer_id );
		if ( ! $user instanceof \WP_User || ! in_array( 'customer', $user->roles, true ) ) {
			return 'unavailable';
		}

		return $this->enroller->create_entry( 'customer', $customer_id, $match_type, $reason );
	}

	/**
	 * Handles the Users-list enrollment form submission.
	 *
	 * @return void
	 */
	public function create_customer_entry(): void {
		if ( ! $this->operator_access->can_manage_entries() ) {
			wp_die( esc_html__( 'You cannot enroll Blacklist entries.', 'wp-buyer-blacklist' ), 403 );
		}

		check_admin_referer( 'wp_buyer_blacklist_enroll_customer' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer above validates this form submission.
		$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer above validates this form submission.
		$match_type = isset( $_POST['match_type'] ) && is_string( $_POST['match_type'] ) ? sanitize_key( wp_unslash( $_POST['match_type'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer above validates this form submission.
		$reason = isset( $_POST['reason'] ) && is_string( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$result = $this->enroll_customer( $customer_id, $match_type, $reason );

		wp_safe_redirect( add_query_arg( 'wp_buyer_blacklist_enrollment', $result, admin_url( 'users.php' ) ) );
		exit;
	}

	/**
	 * Renders the result of a Users-list enrollment submission.
	 *
	 * @return void
	 */
	public function render_customer_enrollment_notice(): void {
		global $pagenow;
		if ( 'users.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This query parameter only displays the prior submission result.
		$result   = isset( $_GET['wp_buyer_blacklist_enrollment'] ) && is_string( $_GET['wp_buyer_blacklist_enrollment'] ) ? sanitize_key( wp_unslash( $_GET['wp_buyer_blacklist_enrollment'] ) ) : '';
		$messages = array(
			'created'     => array( 'success', __( 'Blacklist entry created.', 'wp-buyer-blacklist' ) ),
			'duplicate'   => array( 'warning', __( 'A Blacklist entry with that Match type and value already exists.', 'wp-buyer-blacklist' ) ),
			'unavailable' => array( 'error', __( 'The selected Match type has no usable current value for this Buyer.', 'wp-buyer-blacklist' ) ),
		);
		if ( ! isset( $messages[ $result ] ) ) {
			return;
		}

		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $result ][0] ), esc_html( $messages[ $result ][1] ) );
	}

	/**
	 * Builds the current Buyer data shown in the Users-list modal.
	 *
	 * @param int $customer_id WordPress user ID.
	 * @return array<string, mixed>
	 */
	private function customer_modal_data( int $customer_id ): array {
		$user     = get_userdata( $customer_id );
		$customer = new \WC_Customer( $customer_id );
		$billing  = $this->enroller->source_address( $customer, 'billing_address' );
		$shipping = $this->enroller->source_address( $customer, 'shipping_address' );

		return array(
			'id'               => $customer_id,
			'name'             => $user instanceof \WP_User ? $user->display_name : '',
			'email'            => $customer->get_email(),
			'billing_address'  => $this->enroller->format_address( $billing ),
			'shipping_address' => $this->enroller->format_address( $shipping ),
			'match_types'      => array(
				'email'            => $this->enroller->is_valid_email( $customer->get_email() ),
				'customer_id'      => $customer_id > 0,
				'billing_address'  => $this->enroller->is_valid_address( 'billing_address', $billing ),
				'shipping_address' => $this->enroller->is_valid_address( 'shipping_address', $shipping ),
			),
		);
	}

	/**
	 * Adds the Customer bulk action.
	 *
	 * @param array<string, string> $actions Existing bulk actions.
	 * @return array<string, string>
	 */
	public function add_customer_bulk_action( array $actions ): array {
		if ( $this->operator_access->can_manage_entries() ) {
			$actions['wp_buyer_blacklist_enroll'] = __( 'Enroll in Blacklist', 'wp-buyer-blacklist' );
		}
		return $actions;
	}

	/**
	 * Sends selected Customers to the enrollment page.
	 *
	 * @param string     $redirect_to Redirect URL.
	 * @param string     $action      Bulk action.
	 * @param array<int> $ids        Selected user identifiers.
	 * @return string
	 */
	public function handle_customer_bulk_action( string $redirect_to, string $action, array $ids ): string {
		return 'wp_buyer_blacklist_enroll' === $action && $this->operator_access->can_manage_entries() ? $this->page->enrollment_url( 'customer', $ids ) : $redirect_to;
	}

	/**
	 * Adds a Customer-detail action.
	 *
	 * @param \WP_User $user Customer user.
	 * @return void
	 */
	public function render_customer_detail_action( \WP_User $user ): void {
		if ( ! in_array( 'customer', $user->roles, true ) || ! $this->operator_access->can_manage_entries() ) {
			return;
		}
		?>
		<tr><th><?php esc_html_e( 'Buyer Blacklist', 'wp-buyer-blacklist' ); ?></th><td><?php echo wp_kses_post( $this->page->enrollment_link( 'customer', array( $user->ID ), __( 'Enroll in Blacklist', 'wp-buyer-blacklist' ) ) ); ?></td></tr>
		<?php
	}
}

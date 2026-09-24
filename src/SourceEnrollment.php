<?php
/**
 * Enrolls identity snapshots from WooCommerce administration.
 *
 * @package WP_Buyer_Blacklist
 */

namespace WP_Buyer_Blacklist;

use InvalidArgumentException;
use WP_Buyer_Blacklist\Access\OperatorAccess;
use WP_Buyer_Blacklist\Entries\AddressEntry;
use WP_Buyer_Blacklist\Entries\CustomerIdEntry;
use WP_Buyer_Blacklist\Entries\EmailEntry;

/**
 * Adds Customer and Order Blacklist enrollment workflows.
 */
final class SourceEnrollment {

	/**
	 * Entry storage.
	 *
	 * @var EmailEntryRepository
	 */
	private EmailEntryRepository $entries;

	/**
	 * Blacklist operator access policy.
	 *
	 * @var OperatorAccess
	 */
	private OperatorAccess $operator_access;

	/**
	 * Creates the workflow.
	 *
	 * @param EmailEntryRepository $entries         Entry storage.
	 * @param OperatorAccess       $operator_access Blacklist operator access policy.
	 */
	public function __construct( EmailEntryRepository $entries, OperatorAccess $operator_access ) {
		$this->entries         = $entries;
		$this->operator_access = $operator_access;
	}

	/**
	 * Registers WordPress and WooCommerce administration hooks.
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
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_order_row_action' ), 10, 2 );
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_detail_action' ), 10, 2 );
		add_action( 'woocommerce_order_action_wp_buyer_blacklist_enroll', array( $this, 'redirect_order_detail_action' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_order_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_order_bulk_action' ), 10, 3 );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_order_bulk_action' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_order_bulk_action' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_wp_buyer_blacklist_enroll_source', array( $this, 'create_entries' ) );
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

		$entries = $this->matching_customer_entries( $user_id );
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
		?>
		<dialog id="wp-buyer-blacklist-enroll-customer-dialog" aria-labelledby="wp-buyer-blacklist-enroll-customer-title">
			<form class="wp-buyer-blacklist-enrollment-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<h2 id="wp-buyer-blacklist-enroll-customer-title"><?php esc_html_e( 'Enroll Buyer in Blacklist', 'wp-buyer-blacklist' ); ?></h2>
				<?php wp_nonce_field( 'wp_buyer_blacklist_enroll_customer' ); ?>
				<input type="hidden" name="action" value="wp_buyer_blacklist_enroll_customer">
				<input type="hidden" name="customer_id" value="">
				<h3><?php esc_html_e( 'Current Buyer', 'wp-buyer-blacklist' ); ?></h3>
				<dl>
					<dt><?php esc_html_e( 'Name', 'wp-buyer-blacklist' ); ?></dt><dd data-buyer-field="name"></dd>
					<dt><?php esc_html_e( 'WordPress user ID', 'wp-buyer-blacklist' ); ?></dt><dd data-buyer-field="id"></dd>
					<dt><?php esc_html_e( 'Email', 'wp-buyer-blacklist' ); ?></dt><dd data-buyer-field="email"></dd>
					<dt><?php esc_html_e( 'Billing address', 'wp-buyer-blacklist' ); ?></dt><dd data-buyer-field="billing_address"></dd>
					<dt><?php esc_html_e( 'Shipping address', 'wp-buyer-blacklist' ); ?></dt><dd data-buyer-field="shipping_address"></dd>
				</dl>
				<p><label for="wp-buyer-blacklist-customer-match-type"><?php esc_html_e( 'Match type', 'wp-buyer-blacklist' ); ?></label><select id="wp-buyer-blacklist-customer-match-type" name="match_type" required></select></p>
				<p><label for="wp-buyer-blacklist-customer-reason"><?php esc_html_e( 'Reason', 'wp-buyer-blacklist' ); ?></label><textarea class="large-text" id="wp-buyer-blacklist-customer-reason" name="reason" required></textarea></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create entry', 'wp-buyer-blacklist' ); ?></button> <button type="button" class="button" data-close-dialog><?php esc_html_e( 'Cancel', 'wp-buyer-blacklist' ); ?></button></p>
			</form>
		</dialog>
		<dialog id="wp-buyer-blacklist-manage-customer-dialog" aria-labelledby="wp-buyer-blacklist-manage-customer-title">
			<h2 id="wp-buyer-blacklist-manage-customer-title"><?php esc_html_e( 'Manage Blacklist entry', 'wp-buyer-blacklist' ); ?></h2>
			<p><label for="wp-buyer-blacklist-matching-entry"><?php esc_html_e( 'Matching Blacklist entry', 'wp-buyer-blacklist' ); ?></label><select id="wp-buyer-blacklist-matching-entry"></select></p>
			<div data-buyer-context></div>
			<p><label for="wp-buyer-blacklist-manage-reason"><?php esc_html_e( 'Reason', 'wp-buyer-blacklist' ); ?></label><textarea class="large-text" id="wp-buyer-blacklist-manage-reason" required></textarea></p>
			<p><button type="button" class="button button-primary" data-save-entry><?php esc_html_e( 'Save reason', 'wp-buyer-blacklist' ); ?></button> <button type="button" class="button" data-toggle-entry></button> <button type="button" class="button" data-close-manage-dialog><?php esc_html_e( 'Close', 'wp-buyer-blacklist' ); ?></button></p>
			<p role="status" data-manage-result></p>
		</dialog>
		<script>
			( function() {
				const dialog = document.getElementById( 'wp-buyer-blacklist-enroll-customer-dialog' );
				const manageDialog = document.getElementById( 'wp-buyer-blacklist-manage-customer-dialog' );
				if ( ! dialog ) return;
				document.querySelectorAll( '.wp-buyer-blacklist-enroll-customer' ).forEach( ( button ) => button.addEventListener( 'click', () => {
					const buyer = JSON.parse( button.dataset.buyer || '{}' );
					if ( ! buyer.id ) return;
					dialog.querySelector( '[name="customer_id"]' ).value = buyer.id;
					Object.entries( { name: buyer.name, id: buyer.id, email: buyer.email, billing_address: buyer.billing_address, shipping_address: buyer.shipping_address } ).forEach( ( [ field, value ] ) => { dialog.querySelector( `[data-buyer-field="${ field }"]` ).textContent = value; } );
					const matchType = dialog.querySelector( '[name="match_type"]' );
					matchType.replaceChildren();
					Object.entries( { email: 'Email', customer_id: 'Customer ID', billing_address: 'Billing address', shipping_address: 'Shipping address' } ).forEach( ( [ value, label ] ) => { const option = new Option( buyer.match_types[ value ] ? label : `${ label } (unavailable)`, value, false, value === 'email' && buyer.match_types.email ); option.disabled = ! buyer.match_types[ value ]; matchType.add( option ); } );
					if ( ! matchType.value ) matchType.selectedIndex = Array.from( matchType.options ).findIndex( ( option ) => ! option.disabled );
					dialog.showModal();
				} ) );
				dialog.querySelector( '[data-close-dialog]' ).addEventListener( 'click', () => dialog.close() );
				if ( ! manageDialog ) return;
				let entries = [];
				const selector = manageDialog.querySelector( 'select' );
				const reason = manageDialog.querySelector( 'textarea' );
				const result = manageDialog.querySelector( '[data-manage-result]' );
				const selected = () => entries.find( ( entry ) => String( entry.id ) === selector.value );
				const renderEntry = async () => {
					const entry = selected();
					if ( ! entry ) return;
					reason.value = entry.reason;
					manageDialog.querySelector( '[data-toggle-entry]' ).textContent = entry.is_active === '1' ? 'Deactivate' : 'Activate';
					result.textContent = '';
					try {
						const context = await window.wp.apiFetch( { path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }/buyer-context` } );
						manageDialog.querySelector( '[data-buyer-context]' ).textContent = context.buyer ? `Current Buyer: ${ context.buyer.name } (#${ context.buyer.id })` : context.message;
					} catch ( error ) { manageDialog.querySelector( '[data-buyer-context]' ).textContent = ''; }
				};
				document.querySelectorAll( '.wp-buyer-blacklist-manage-customer' ).forEach( ( button ) => button.addEventListener( 'click', () => {
					entries = JSON.parse( button.dataset.entries || '[]' );
					selector.replaceChildren( ...entries.map( ( entry ) => new Option( `${ entry.match_type }: ${ entry.value } (${ entry.is_active === '1' ? 'Active' : 'Inactive' })`, entry.id ) ) );
					renderEntry(); manageDialog.showModal();
				} ) );
				selector.addEventListener( 'change', renderEntry );
				manageDialog.querySelector( '[data-save-entry]' ).addEventListener( 'click', async () => { const entry = selected(); try { await window.wp.apiFetch( { path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }`, method: 'PATCH', data: { reason: reason.value } } ); entry.reason = reason.value; result.textContent = 'Blacklist entry updated.'; } catch ( error ) { result.textContent = error.message; } } );
				manageDialog.querySelector( '[data-toggle-entry]' ).addEventListener( 'click', async () => { const entry = selected(); try { await window.wp.apiFetch( { path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }`, method: 'PATCH', data: { is_active: entry.is_active !== '1' } } ); entry.is_active = entry.is_active === '1' ? '0' : '1'; renderEntry(); result.textContent = 'Blacklist entry updated.'; } catch ( error ) { result.textContent = error.message; } } );
				manageDialog.querySelector( '[data-close-manage-dialog]' ).addEventListener( 'click', () => manageDialog.close() );
			} )();
		</script>
		<?php
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

		return $this->create_entry( 'customer', $customer_id, $match_type, $reason );
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
		$billing  = $this->source_address( $customer, 'billing_address' );
		$shipping = $this->source_address( $customer, 'shipping_address' );

		return array(
			'id'               => $customer_id,
			'name'             => $user instanceof \WP_User ? $user->display_name : '',
			'email'            => $customer->get_email(),
			'billing_address'  => $this->format_address( $billing ),
			'shipping_address' => $this->format_address( $shipping ),
			'match_types'      => array(
				'email'            => $this->is_valid_email( $customer->get_email() ),
				'customer_id'      => $customer_id > 0,
				'billing_address'  => $this->is_valid_address( 'billing_address', $billing ),
				'shipping_address' => $this->is_valid_address( 'shipping_address', $shipping ),
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
		return 'wp_buyer_blacklist_enroll' === $action && $this->operator_access->can_manage_entries() ? $this->enrollment_url( 'customer', $ids ) : $redirect_to;
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
		<tr><th><?php esc_html_e( 'Buyer Blacklist', 'wp-buyer-blacklist' ); ?></th><td><?php echo wp_kses_post( $this->enrollment_link( 'customer', array( $user->ID ), __( 'Enroll in Blacklist', 'wp-buyer-blacklist' ) ) ); ?></td></tr>
		<?php
	}

	/**
	 * Adds an action to WooCommerce Order rows.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param \WC_Order             $order   Order.
	 * @return array<string, string>
	 */
	public function add_order_row_action( array $actions, \WC_Order $order ): array {
		if ( $this->operator_access->can_manage_entries() ) {
			$actions['wp_buyer_blacklist_enroll'] = array(
				'url'    => $this->enrollment_url( 'order', array( $order->get_id() ) ),
				'name'   => __( 'Enroll in Blacklist', 'wp-buyer-blacklist' ),
				'action' => 'wp-buyer-blacklist-enroll',
			);
		}

		return $actions;
	}

	/**
	 * Adds an Order-detail action.
	 *
	 * @param array<string, string> $actions Existing actions.
	 * @param \WC_Order             $order   Order.
	 * @return array<string, string>
	 */
	public function add_order_detail_action( array $actions, \WC_Order $order ): array {
		if ( $order->get_id() <= 0 || ! $this->operator_access->can_manage_entries() ) {
			return $actions;
		}
		$actions['wp_buyer_blacklist_enroll'] = __( 'Enroll in Blacklist', 'wp-buyer-blacklist' );
		return $actions;
	}

	/**
	 * Sends an Order-detail action to the enrollment page.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function redirect_order_detail_action( \WC_Order $order ): void {
		if ( ! $this->operator_access->can_manage_entries() ) {
			wp_die( esc_html__( 'You cannot enroll Blacklist entries.', 'wp-buyer-blacklist' ), 403 );
		}
		wp_safe_redirect( $this->enrollment_url( 'order', array( $order->get_id() ) ) );
		exit;
	}

	/**
	 * Adds the Order bulk action.
	 *
	 * @param array<string, string> $actions Existing bulk actions.
	 * @return array<string, string>
	 */
	public function add_order_bulk_action( array $actions ): array {
		if ( $this->operator_access->can_manage_entries() ) {
			$actions['wp_buyer_blacklist_enroll'] = __( 'Enroll in Blacklist', 'wp-buyer-blacklist' );
		}
		return $actions;
	}

	/**
	 * Sends selected Orders to the enrollment page.
	 *
	 * @param string     $redirect_to Redirect URL.
	 * @param string     $action      Bulk action.
	 * @param array<int> $ids        Selected order identifiers.
	 * @return string
	 */
	public function handle_order_bulk_action( string $redirect_to, string $action, array $ids ): string {
		return 'wp_buyer_blacklist_enroll' === $action && $this->operator_access->can_manage_entries() ? $this->enrollment_url( 'order', $ids ) : $redirect_to;
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
			echo '<div class="wrap"><p>' . esc_html__( 'Select at least one Customer or Order to enroll.', 'wp-buyer-blacklist' ) . '</p></div>';
			return;
		}
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Enroll in Blacklist', 'wp-buyer-blacklist' ); ?></h1><?php $this->render_result(); ?><form class="wp-buyer-blacklist-enrollment-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wp_buyer_blacklist_enroll_source' ); ?><input type="hidden" name="action" value="wp_buyer_blacklist_enroll_source"><input type="hidden" name="source_type" value="<?php echo esc_attr( $source_type ); ?>">
			<?php
			foreach ( $ids as $id ) :
				?>
				<input type="hidden" name="source_ids[]" value="<?php echo esc_attr( (string) $id ); ?>"><?php endforeach; ?>
			<p><label><?php esc_html_e( 'Match type', 'wp-buyer-blacklist' ); ?><select name="match_type"><option value="email"><?php esc_html_e( 'Email', 'wp-buyer-blacklist' ); ?></option><option value="customer_id"><?php esc_html_e( 'Customer ID', 'wp-buyer-blacklist' ); ?></option><option value="billing_address"><?php esc_html_e( 'Billing address', 'wp-buyer-blacklist' ); ?></option><option value="shipping_address"><?php esc_html_e( 'Shipping address', 'wp-buyer-blacklist' ); ?></option></select></label></p>
			<p><label><?php esc_html_e( 'Reason', 'wp-buyer-blacklist' ); ?><textarea class="large-text" name="reason" required></textarea></label></p><p><?php esc_html_e( 'Email and Customer ID need a value. Addresses need every address field. Sources without the selected value are skipped.', 'wp-buyer-blacklist' ); ?></p><p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create entries', 'wp-buyer-blacklist' ); ?></button></p>
		</form></div>
		<?php
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
		printf(
			'<div class="notice notice-success"><p>%s</p></div>',
			// translators: 1: entries created, 2: duplicate entries skipped, 3: unavailable sources skipped.
			esc_html( sprintf( __( '%1$d created, %2$d duplicates skipped, %3$d unavailable sources skipped.', 'wp-buyer-blacklist' ), $created, $duplicates, $unavailable ) )
		);
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
			$result = $this->create_entry( $source_type, $source_id, $match_type, $reason );
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
	 * Creates one immutable identity snapshot.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source identifier.
	 * @param string $match_type  Match type.
	 * @param string $reason      Operator reason.
	 * @return string Result status.
	 */
	private function create_entry( string $source_type, int $source_id, string $match_type, string $reason ): string {
		if ( '' === trim( $reason ) || ! in_array( $match_type, array( 'email', 'customer_id', 'billing_address', 'shipping_address' ), true ) ) {
			return 'unavailable';
		}
		$source = 'customer' === $source_type ? new \WC_Customer( $source_id ) : wc_get_order( $source_id );
		if ( ! $source instanceof \WC_Customer && ! $source instanceof \WC_Order ) {
			return 'unavailable';
		}
		try {
			if ( 'email' === $match_type ) {
				$entry = EmailEntry::from_email( $source instanceof \WC_Order ? $source->get_billing_email() : $source->get_email() );
				return $this->entries->create( $entry, $reason ) ? 'created' : 'duplicate';
			}
			if ( 'customer_id' === $match_type ) {
				$customer_id = $source instanceof \WC_Order ? $source->get_customer_id() : $source->get_id();
				return $this->entries->create_customer_id( CustomerIdEntry::from_customer_id( $customer_id ), $reason ) ? 'created' : 'duplicate';
			}
			$fields = $this->source_address( $source, $match_type );
			return $this->entries->create_address( AddressEntry::from_fields( $match_type, $fields, WC()->countries->get_countries() ), $reason ) ? 'created' : 'duplicate';
		} catch ( InvalidArgumentException $exception ) {
			return 'unavailable';
		}
	}

	/**
	 * Gets one source address in Blacklist field names.
	 *
	 * @param \WC_Customer|\WC_Order $source     Customer or Order source.
	 * @param string                 $match_type Address Match type.
	 * @return array<string, string>
	 */
	private function source_address( \WC_Customer|\WC_Order $source, string $match_type ): array {
		return array(
			'address_line_1' => 'billing_address' === $match_type ? $source->get_billing_address_1() : $source->get_shipping_address_1(),
			'address_line_2' => 'billing_address' === $match_type ? $source->get_billing_address_2() : $source->get_shipping_address_2(),
			'city'           => 'billing_address' === $match_type ? $source->get_billing_city() : $source->get_shipping_city(),
			'state'          => 'billing_address' === $match_type ? $source->get_billing_state() : $source->get_shipping_state(),
			'postcode'       => 'billing_address' === $match_type ? $source->get_billing_postcode() : $source->get_shipping_postcode(),
			'country'        => 'billing_address' === $match_type ? $source->get_billing_country() : $source->get_shipping_country(),
		);
	}

	/**
	 * Formats an Address for display.
	 *
	 * @param array<string, string> $address Address fields.
	 * @return string
	 */
	private function format_address( array $address ): string {
		return implode( ', ', array_filter( $address ) );
	}

	/**
	 * Checks whether an email can create an entry.
	 *
	 * @param string $email Buyer email address.
	 * @return bool
	 */
	private function is_valid_email( string $email ): bool {
		try {
			EmailEntry::from_email( $email );
			return true;
		} catch ( InvalidArgumentException $exception ) {
			return false;
		}
	}

	/**
	 * Checks whether an Address can create an entry.
	 *
	 * @param string                $match_type Address Match type.
	 * @param array<string, string> $address   Address fields.
	 * @return bool
	 */
	private function is_valid_address( string $match_type, array $address ): bool {
		try {
			AddressEntry::from_fields( $match_type, $address, WC()->countries->get_countries() );
			return true;
		} catch ( InvalidArgumentException $exception ) {
			return false;
		}
	}

	/**
	 * Finds active entries that match a WooCommerce Customer.
	 *
	 * @param int $customer_id WooCommerce Customer ID.
	 * @return list<array{id: string, is_active: string}> Matching entries.
	 */
	private function matching_customer_entries( int $customer_id ): array {
		$customer   = new \WC_Customer( $customer_id );
		$entries    = $this->entries->matching_entries( 'customer_id', hash( 'sha256', (string) $customer_id ) );
		$email_hash = '';

		try {
			$email_hash = EmailEntry::from_email( $customer->get_email() )->canonical_hash();
		} catch ( InvalidArgumentException $exception ) {
			$email_hash = '';
		}

		if ( '' !== $email_hash ) {
			$entries = array_merge( $entries, $this->entries->matching_entries( 'email', $email_hash ) );
		}

		foreach ( array( 'billing_address', 'shipping_address' ) as $match_type ) {
			$fields = $this->source_address( $customer, $match_type );
			try {
				$address = AddressEntry::from_fields( $match_type, $fields, WC()->countries->get_countries() );
				$entries = array_merge( $entries, $this->entries->matching_entries( $match_type, $address->canonical_hash() ) );
			} catch ( InvalidArgumentException $exception ) {
				continue;
			}
		}

		return $entries;
	}

	/**
	 * Builds an enrollment action link.
	 *
	 * @param string     $source_type Source type.
	 * @param array<int> $ids        Source identifiers.
	 * @param string     $label       Link label.
	 * @return string
	 */
	private function enrollment_link( string $source_type, array $ids, string $label ): string {
		return '<a href="' . esc_url( $this->enrollment_url( $source_type, $ids ) ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Builds an enrollment-page URL.
	 *
	 * @param string     $source_type Source type.
	 * @param array<int> $ids        Source identifiers.
	 * @return string
	 */
	private function enrollment_url( string $source_type, array $ids ): string {
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

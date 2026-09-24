<?php
/**
 * Users-list enrollment and manage dialogs with their script.
 *
 * @package WP_Buyer_Blacklist
 */

defined( 'ABSPATH' ) || exit;
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

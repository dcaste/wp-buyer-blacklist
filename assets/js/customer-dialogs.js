/**
 * Opens the Users-list dialogs for enrolling a Buyer and managing matching entries.
 *
 * The dialogs are printed on admin_footer-users.php, after footer scripts, so
 * this waits for the whole page to be parsed.
 */
document.addEventListener( 'DOMContentLoaded', () => {
	const { __, sprintf } = window.wp.i18n;
	const dialog = document.getElementById(
		'wp-buyer-blacklist-enroll-customer-dialog'
	);
	if ( ! dialog ) {
		return;
	}
	const manageDialog = document.getElementById(
		'wp-buyer-blacklist-manage-customer-dialog'
	);
	document
		.querySelectorAll( '.wp-buyer-blacklist-enroll-customer' )
		.forEach( ( button ) =>
			button.addEventListener( 'click', () => {
				const buyer = JSON.parse( button.dataset.buyer || '{}' );
				if ( ! buyer.id ) {
					return;
				}
				dialog.querySelector( '[name="customer_id"]' ).value = buyer.id;
				Object.entries( {
					name: buyer.name,
					id: buyer.id,
					email: buyer.email,
					billing_address: buyer.billing_address,
					shipping_address: buyer.shipping_address,
				} ).forEach( ( [ field, value ] ) => {
					dialog.querySelector(
						`[data-buyer-field="${ field }"]`
					).textContent = value;
				} );
				const matchType = dialog.querySelector( '[name="match_type"]' );
				matchType.replaceChildren();
				Object.entries( {
					email: __( 'Email', 'wp-buyer-blacklist' ),
					customer_id: __( 'Customer ID', 'wp-buyer-blacklist' ),
					billing_address: __( 'Billing address', 'wp-buyer-blacklist' ),
					shipping_address: __( 'Shipping address', 'wp-buyer-blacklist' ),
				} ).forEach( ( [ value, label ] ) => {
					const option = new window.Option(
						buyer.match_types[ value ]
							? label
							: sprintf(
									/* translators: %s: Match type name. */
									__( '%s (unavailable)', 'wp-buyer-blacklist' ),
									label
								),
						value,
						false,
						value === 'email' && buyer.match_types.email
					);
					option.disabled = ! buyer.match_types[ value ];
					matchType.add( option );
				} );
				if ( ! matchType.value ) {
					matchType.selectedIndex = Array.from(
						matchType.options
					).findIndex( ( option ) => ! option.disabled );
				}
				dialog.showModal();
			} )
		);
	dialog
		.querySelector( '[data-close-dialog]' )
		.addEventListener( 'click', () => dialog.close() );
	if ( ! manageDialog ) {
		return;
	}
	let entries = [];
	let manageButton = null;
	const selector = manageDialog.querySelector( 'select' );
	const reason = manageDialog.querySelector( 'textarea' );
	const result = manageDialog.querySelector( '[data-manage-result]' );
	const showResult = ( message, status = '' ) => {
		result.textContent = message;
		result.classList.toggle( 'is-success', status === 'success' );
		result.classList.toggle( 'is-error', status === 'error' );
	};
	const selected = () =>
		entries.find( ( entry ) => String( entry.id ) === selector.value );
	const renderOptions = () => {
		const value = selector.value;
		selector.replaceChildren(
			...entries.map(
				( entry ) =>
					new window.Option(
						sprintf(
							/* translators: 1: Match type, 2: entry value, 3: entry status. */
							__( '%1$s: %2$s (%3$s)', 'wp-buyer-blacklist' ),
							entry.match_type,
							entry.value,
							entry.is_active === '1'
								? __( 'Active', 'wp-buyer-blacklist' )
								: __( 'Inactive', 'wp-buyer-blacklist' )
						),
						entry.id
					)
			)
		);
		if ( value ) {
			selector.value = value;
		}
	};
	// Keeps the Users-list row in step with changes made in the dialog.
	const renderRow = () => {
		manageButton.dataset.entries = JSON.stringify( entries );
		const badge = manageButton.parentElement.querySelector(
			'.wp-buyer-blacklist-status-badge'
		);
		if ( ! badge ) {
			return;
		}
		const active = entries.some( ( entry ) => entry.is_active === '1' );
		badge.classList.toggle( 'is-active', active );
		badge.classList.toggle( 'is-inactive', ! active );
		badge.textContent = active
			? __( 'Blacklisted', 'wp-buyer-blacklist' )
			: __( 'Inactive', 'wp-buyer-blacklist' );
		if ( active ) {
			badge.setAttribute( 'tabindex', '0' );
			badge.setAttribute(
				'aria-label',
				__( 'Blacklisted. This Buyer cannot place new orders.', 'wp-buyer-blacklist' )
			);
			badge.setAttribute(
				'data-wpbb-tooltip',
				__(
						'This Buyer is blacklisted from placing new orders in your store.',
						'wp-buyer-blacklist'
					)
			);
		} else {
			badge.removeAttribute( 'tabindex' );
			badge.removeAttribute( 'aria-label' );
			badge.removeAttribute( 'data-wpbb-tooltip' );
		}
	};
	const renderEntry = async () => {
		const entry = selected();
		if ( ! entry ) {
			return;
		}
		reason.value = entry.reason;
		manageDialog.querySelector( '[data-toggle-entry]' ).textContent =
			entry.is_active === '1'
				? __( 'Deactivate', 'wp-buyer-blacklist' )
				: __( 'Activate', 'wp-buyer-blacklist' );
		showResult( '' );
		try {
			const context = await window.wp.apiFetch( {
				path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }/buyer-context`,
			} );
			manageDialog.querySelector( '[data-buyer-context]' ).textContent =
				context.buyer
					? sprintf(
							/* translators: 1: Buyer name, 2: Buyer ID. */
							__( 'Current Buyer: %1$s (#%2$d)', 'wp-buyer-blacklist' ),
							context.buyer.name,
							context.buyer.id
						)
					: context.message;
		} catch {
			manageDialog.querySelector( '[data-buyer-context]' ).textContent =
				'';
		}
	};
	document
		.querySelectorAll( '.wp-buyer-blacklist-manage-customer' )
		.forEach( ( button ) =>
			button.addEventListener( 'click', () => {
				manageButton = button;
				entries = JSON.parse( button.dataset.entries || '[]' );
				selector.value = '';
				renderOptions();
				renderEntry();
				manageDialog.showModal();
			} )
		);
	selector.addEventListener( 'change', renderEntry );
	manageDialog
		.querySelector( '[data-save-entry]' )
		.addEventListener( 'click', async () => {
			const entry = selected();
			try {
				await window.wp.apiFetch( {
					path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }`,
					method: 'PATCH',
					data: { reason: reason.value },
				} );
				entry.reason = reason.value;
				renderRow();
				showResult(
						__( 'Blacklist entry updated.', 'wp-buyer-blacklist' ),
						'success'
					);
			} catch ( error ) {
				showResult( error.message, 'error' );
			}
		} );
	manageDialog
		.querySelector( '[data-toggle-entry]' )
		.addEventListener( 'click', async () => {
			const entry = selected();
			try {
				await window.wp.apiFetch( {
					path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }`,
					method: 'PATCH',
					data: { is_active: entry.is_active !== '1' },
				} );
				entry.is_active = entry.is_active === '1' ? '0' : '1';
				renderOptions();
				renderRow();
				renderEntry();
				showResult(
						__( 'Blacklist entry updated.', 'wp-buyer-blacklist' ),
						'success'
					);
			} catch ( error ) {
				showResult( error.message, 'error' );
			}
		} );
} );

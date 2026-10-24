/**
 * Tests the Users-list dialog script.
 */

const buyer = {
	id: 7,
	name: 'Test Buyer',
	email: 'buyer@example.com',
	billing_address: '1 Test Street, Testville',
	shipping_address: '',
	match_types: {
		email: true,
		customer_id: true,
		billing_address: true,
		shipping_address: false,
	},
};

const dialogs = `
	<button type="button" class="wp-buyer-blacklist-enroll-customer" data-buyer='${ JSON.stringify(
		buyer
	) }'>Enroll</button>
	<dialog id="wp-buyer-blacklist-enroll-customer-dialog">
		<form>
			<input type="hidden" name="customer_id" value="">
			<dd data-buyer-field="name"></dd>
			<dd data-buyer-field="id"></dd>
			<dd data-buyer-field="email"></dd>
			<dd data-buyer-field="billing_address"></dd>
			<dd data-buyer-field="shipping_address"></dd>
			<select name="match_type"></select>
			<button type="button" data-close-dialog>Cancel</button>
		</form>
	</dialog>
	<dialog id="wp-buyer-blacklist-manage-customer-dialog">
		<select></select>
		<div data-buyer-context></div>
		<textarea></textarea>
		<button type="button" data-save-entry>Save reason</button>
		<button type="button" data-toggle-entry></button>
		<p data-manage-result></p>
	</dialog>
`;

describe( 'customer dialogs', () => {
	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = '';
		// jsdom has no modal dialogs.
		window.HTMLDialogElement.prototype.showModal = jest.fn();
		window.HTMLDialogElement.prototype.close = jest.fn();
	} );

	it( 'binds the Enroll button once the page has loaded', () => {
		window.wp = { i18n: require( '@wordpress/i18n' ) };
		// The footer script runs before WordPress prints the dialogs.
		require( '../../assets/js/customer-dialogs' );
		document.body.innerHTML = dialogs;
		document.dispatchEvent( new window.Event( 'DOMContentLoaded' ) );

		document.querySelector( '.wp-buyer-blacklist-enroll-customer' ).click();

		const dialog = document.getElementById(
			'wp-buyer-blacklist-enroll-customer-dialog'
		);
		expect( dialog.showModal ).toHaveBeenCalled();
		expect( dialog.querySelector( '[name="customer_id"]' ).value ).toBe(
			'7'
		);
		expect(
			dialog.querySelector( '[data-buyer-field="email"]' ).textContent
		).toBe( 'buyer@example.com' );
		const options = Array.from(
			dialog.querySelector( '[name="match_type"]' ).options
		);
		expect( options.map( ( option ) => option.textContent ) ).toEqual( [
			'Email',
			'Customer ID',
			'Billing address',
			'Shipping address (unavailable)',
		] );
		expect( options[ 3 ].disabled ).toBe( true );
		expect( dialog.querySelector( '[name="match_type"]' ).value ).toBe(
			'email'
		);
	} );

	it( 'updates the Users-list status after the modal changes it', async () => {
		const entries = [
			{
				id: '3',
				match_type: 'email',
				value: 'buyer@example.com',
				reason: 'Fraud',
				is_active: '1',
			},
		];
		window.wp = {
			i18n: require( '@wordpress/i18n' ),
			apiFetch: jest.fn( () => Promise.resolve( { buyer: null } ) ),
		};
		require( '../../assets/js/customer-dialogs' );
		document.body.innerHTML = `<td>
			<span class="wp-buyer-blacklist-status-badge is-active" tabindex="0" aria-label="Blacklisted." data-wpbb-tooltip="Blacklisted.">Blacklisted</span><br>
			<button type="button" class="wp-buyer-blacklist-manage-customer" data-entries='${ JSON.stringify(
				entries
			) }'>Manage</button>
		</td>${ dialogs }`;
		document.dispatchEvent( new window.Event( 'DOMContentLoaded' ) );
		const manage = document.querySelector(
			'.wp-buyer-blacklist-manage-customer'
		);
		manage.click();
		const toggle = document.querySelector( '[data-toggle-entry]' );

		toggle.click();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		const badge = document.querySelector(
			'.wp-buyer-blacklist-status-badge'
		);
		expect( badge.textContent ).toBe( 'Inactive' );
		expect( badge.classList ).toContain( 'is-inactive' );
		expect( badge.classList ).not.toContain( 'is-active' );
		expect( badge.hasAttribute( 'data-wpbb-tooltip' ) ).toBe( false );
		expect( badge.hasAttribute( 'tabindex' ) ).toBe( false );
		expect( JSON.parse( manage.dataset.entries )[ 0 ].is_active ).toBe(
			'0'
		);
		expect(
			document.querySelector(
				'#wp-buyer-blacklist-manage-customer-dialog select'
			).options[ 0 ].textContent
		).toBe( 'email: buyer@example.com (Inactive)' );

		toggle.click();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( badge.textContent ).toBe( 'Blacklisted' );
		expect( badge.classList ).toContain( 'is-active' );
		expect( badge.getAttribute( 'data-wpbb-tooltip' ) ).toBe(
			'This Buyer is blacklisted from placing new orders in your store.'
		);
	} );

	it( 'marks the status message as success or error', async () => {
		const entries = [
			{
				id: '3',
				match_type: 'email',
				value: 'buyer@example.com',
				reason: 'Fraud',
				is_active: '1',
			},
		];
		window.wp = {
			i18n: require( '@wordpress/i18n' ),
			apiFetch: jest.fn( ( { method } ) =>
				method === 'PATCH'
					? Promise.resolve( {} )
					: Promise.resolve( { buyer: null } )
			),
		};
		require( '../../assets/js/customer-dialogs' );
		document.body.innerHTML = `<button type="button" class="wp-buyer-blacklist-manage-customer" data-entries='${ JSON.stringify(
			entries
		) }'>Manage</button>${ dialogs }`;
		document.dispatchEvent( new window.Event( 'DOMContentLoaded' ) );
		document.querySelector( '.wp-buyer-blacklist-manage-customer' ).click();
		const result = document.querySelector( '[data-manage-result]' );

		document.querySelector( '[data-toggle-entry]' ).click();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( result.textContent ).toBe( 'Blacklist entry updated.' );
		expect( result.classList ).toContain( 'is-success' );

		window.wp.apiFetch.mockImplementation( ( { method } ) =>
			method === 'PATCH'
				? Promise.reject( new Error( 'Update failed.' ) )
				: Promise.resolve( { buyer: null } )
		);
		document.querySelector( '[data-save-entry]' ).click();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( result.textContent ).toBe( 'Update failed.' );
		expect( result.classList ).toContain( 'is-error' );
		expect( result.classList ).not.toContain( 'is-success' );
	} );
} );

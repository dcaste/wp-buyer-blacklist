import apiFetch from '@wordpress/api-fetch';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from '../../src/admin/index';

jest.mock( '@wordpress/api-fetch', () => jest.fn().mockResolvedValue( [] ) );

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

test( 'renders WordPress-style Manage entries controls', async () => {
	const container = document.createElement( 'div' );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	expect( container.textContent ).toContain( 'Add entry' );
	expect( container.querySelector( 'input[type="email"]' ) ).not.toBeNull();
	expect( container.querySelector( 'table.widefat' ) ).not.toBeNull();
	expect( container.textContent ).toContain( 'Match type' );
	expect( container.textContent ).toContain( 'Choose the Buyer detail to match during checkout.' );
	expect( container.textContent ).toContain( 'Enter the full email address to block.' );
	expect( container.textContent ).not.toContain( 'Import CSV' );
	expect( container.textContent ).toContain( 'Total entries' );
	expect( container.textContent ).toContain( 'Blocked attempts' );
} );

test( 'filters entries by status and Match type', async () => {
	( apiFetch as jest.Mock ).mockResolvedValue( [
		{ id: '1', value: 'buyer@example.com', reason: 'Test entry', is_active: '1', match_type: 'email', created_at: '' },
		{ id: '2', value: '{"city":"Testville"}', reason: 'Address', is_active: '0', match_type: 'billing_address', created_at: '' },
	] );
	const container = document.createElement( 'div' );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	const status = container.querySelector( '#wp-buyer-blacklist-status-filter' ) as HTMLSelectElement;
	await act( async () => {
		status.value = 'inactive';
		status.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	} );
	expect( container.textContent ).toContain( 'Address' );
	expect( container.textContent ).not.toContain( 'Test entry' );
} );

test( 'paginates entries in groups of 20', async () => {
	( apiFetch as jest.Mock ).mockResolvedValue( Array.from( { length: 21 }, ( _, index ) => ( { id: String( index ), value: `buyer${ index }@example.com`, reason: 'Test entry', is_active: index === 0 ? '0' : '1', match_type: 'email', created_at: '' } ) ) );
	const container = document.createElement( 'div' );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	expect( container.querySelectorAll( 'tbody tr' ) ).toHaveLength( 20 );
	expect( container.querySelectorAll( '.wp-buyer-blacklist-status-badge.is-inactive' ) ).toHaveLength( 1 );
	expect( container.querySelector( '.tablenav-pages' )?.getAttribute( 'style' ) ).toContain( 'float: left' );
	expect( container.textContent ).toContain( 'Next' );
} );

test( 'validates a CSV file before it offers import confirmation', async () => {
	( apiFetch as jest.Mock ).mockImplementation( ( { path } ) => path === '/wp-buyer-blacklist/v1/csv-import/validate' ? Promise.resolve( { token: 'token', valid: 1, created: null, duplicates: [], rejected: [] } ) : Promise.resolve( [] ) );
	const container = document.createElement( 'div' );
	container.id = 'wp-buyer-blacklist-admin';
	container.dataset.tab = 'import-export';
	document.body.append( container );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	const fileInput = container.querySelector( 'input[type="file"]' ) as HTMLInputElement;
	Object.defineProperty( fileInput, 'files', { value: [ { name: 'entries.csv', size: 42, text: () => Promise.resolve( 'match_type,email\nemail,buyer@example.com\n' ) } ] } );
	await act( async () => { fileInput.dispatchEvent( new Event( 'change', { bubbles: true } ) ); } );
	expect( apiFetch ).toHaveBeenCalledWith( expect.objectContaining( { path: '/wp-buyer-blacklist/v1/csv-import/validate' } ) );
	expect( container.textContent ).toContain( 'Confirm import' );
	container.remove();
} );

test( 'offers a downloadable CSV import example', async () => {
	const container = document.createElement( 'div' );
	container.id = 'wp-buyer-blacklist-admin';
	container.dataset.tab = 'import-export';
	container.dataset.exampleCsvUrl = '/wp-content/plugins/wp-buyer-blacklist/assets/buyer-blacklist-example.csv';
	document.body.append( container );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	expect( container.querySelector( 'a[href="/wp-content/plugins/wp-buyer-blacklist/assets/buyer-blacklist-example.csv"]' )?.textContent ).toBe( 'Download example CSV' );
	expect( container.textContent ).toContain( 'Accepted file type: CSV (.csv) · Maximum file size: 5 MB' );
	expect( container.textContent ).toContain( 'Drag and drop your CSV file here' );
	expect( container.textContent ).toContain( 'Need a template?' );
	expect( container.textContent ).toContain( 'View the status of your recent import activities.' );
	expect( container.querySelector( '.wpbb-button-icon.is-download' ) ).not.toBeNull();
	expect( container.querySelector( 'input[type="search"]' ) ).toBeNull();
	container.remove();
} );

test( 'renders structured address fields when an address Match type is selected', async () => {
	const container = document.createElement( 'div' );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	const matchType = container.querySelector( '#wp-buyer-blacklist-match-type' ) as HTMLSelectElement;
	await act( async () => {
		matchType.value = 'billing_address';
		matchType.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	} );
	expect( container.querySelector( '#wp-buyer-blacklist-address_line_1' ) ).not.toBeNull();
	expect( container.querySelector( '#wp-buyer-blacklist-country' ) ).not.toBeNull();
	expect( container.querySelector( 'label[for="wp-buyer-blacklist-address_line_1"]' )?.textContent ).toBe( 'Address line 1' );
	expect( container.querySelector( 'label[for="wp-buyer-blacklist-country"]' )?.textContent ).toBe( 'Country' );
	expect( container.textContent ).toContain( 'Enter the building number and street name.' );
	expect( container.textContent ).toContain( 'Enter the two-letter country code, such as US.' );
} );

test( 'loads Audit history and Blocked attempts when an entry is managed', async () => {
	( apiFetch as jest.Mock ).mockImplementation( ( { path } ) => {
		if ( path === '/wp-buyer-blacklist/v1/email-entries' ) {
			return Promise.resolve( [ { id: '1', value: 'buyer@example.com', reason: 'Test entry', is_active: '1', created_at: '2026-01-01 00:00:00' } ] );
		}
		if ( path === '/wp-buyer-blacklist/v1/email-entries/1/blocked-attempts' ) {
			return Promise.resolve( [ { id: '1', identity_value: 'buyer@example.com', outcome: 'blocked', created_at: '2026-01-01 00:00:00' } ] );
		}
		if ( path === '/wp-buyer-blacklist/v1/email-entries/1/buyer-context' ) {
			return Promise.resolve( { buyer: { id: 1, name: 'Ada Lovelace', email: 'buyer@example.com', billing_address: { address_line_1: '1 Test Street', address_line_2: '', city: 'Testville', state: 'CA', postcode: '90210', country: 'US' }, shipping_address: { address_line_1: '2 Test Street', address_line_2: '', city: 'Testville', state: 'CA', postcode: '90210', country: 'US' } } } );
		}
		return Promise.resolve( [ { action: 'created', actor_id: '1', actor_name: 'Admin', created_at: '2026-01-01 00:00:00' } ] );
	} );
	const container = document.createElement( 'div' );
	container.id = 'wp-buyer-blacklist-admin';
	container.dataset.dossierExportUrl = '/wp-admin/admin-post.php?action=wp_buyer_blacklist_dossier_export&_wpnonce=nonce';
	document.body.append( container );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	await act( async () => { Array.from( container.querySelectorAll( 'button' ) ).find( ( button ) => button.textContent === 'Manage' )?.click(); } );
	expect( container.textContent ).toContain( 'Audit history' );
	expect( container.textContent ).toContain( 'created by Admin' );
	expect( container.textContent ).toContain( 'Blocked attempts' );
	expect( container.textContent ).toContain( 'Billing address1 Test Street, Testville, CA, 90210, US' );
	expect( container.textContent ).toContain( 'Shipping address2 Test Street, Testville, CA, 90210, US' );
	expect( container.textContent ).toContain( 'blocked buyer@example.com' );
	expect( container.querySelector( 'a[href="/wp-admin/admin-post.php?action=wp_buyer_blacklist_dossier_export&_wpnonce=nonce&entry_id=1"]' )?.textContent ).toBe( 'Export dossier' );
	expect( container.querySelector( 'dialog' ) ).not.toBeNull();
	expect( Array.from( container.querySelectorAll( 'button' ) ).find( ( button ) => button.textContent === 'Manage' )?.getAttribute( 'commandfor' ) ).toBe( 'wp-buyer-blacklist-entry-dialog' );
	const deleteButton = container.querySelector( '.wp-buyer-blacklist-delete-entry' );
	expect( deleteButton?.classList ).toContain( 'wp-buyer-blacklist-delete-entry' );
	expect( deleteButton?.closest( '.wpbb-entry-dialog-footer' ) ).not.toBeNull();
	expect( container.querySelector( '.wpbb-entry-dialog-close' )?.getAttribute( 'aria-label' ) ).toBe( 'Close dialog' );
	expect( container.querySelector( '.wpbb-modal-icon.is-mail' ) ).not.toBeNull();
	expect( container.querySelector( '.wpbb-modal-icon.is-user' ) ).not.toBeNull();
	expect( container.querySelector( '.wpbb-modal-icon.is-pin' ) ).not.toBeNull();
	expect( container.querySelector( '.wpbb-button-icon.is-trash' ) ).not.toBeNull();
	container.remove();
} );

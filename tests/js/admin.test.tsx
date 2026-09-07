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
} );

test( 'paginates entries in groups of 20', async () => {
	( apiFetch as jest.Mock ).mockResolvedValue( Array.from( { length: 21 }, ( _, index ) => ( { id: String( index ), value: `buyer${ index }@example.com`, reason: 'Test entry', is_active: '1', match_type: 'email', created_at: '' } ) ) );
	const container = document.createElement( 'div' );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	expect( container.querySelectorAll( 'tbody tr' ) ).toHaveLength( 20 );
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
	Object.defineProperty( fileInput, 'files', { value: [ { text: () => Promise.resolve( 'match_type,email\nemail,buyer@example.com\n' ) } ] } );
	await act( async () => { fileInput.dispatchEvent( new Event( 'change', { bubbles: true } ) ); } );
	expect( apiFetch ).toHaveBeenCalledWith( expect.objectContaining( { path: '/wp-buyer-blacklist/v1/csv-import/validate' } ) );
	expect( container.textContent ).toContain( 'Confirm import' );
	container.remove();
} );

test( 'renders structured address fields when an address Match type is selected', async () => {
	const container = document.createElement( 'div' );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	const matchType = container.querySelector( 'select[aria-label="Match type"]' ) as HTMLSelectElement;
	await act( async () => {
		matchType.value = 'billing_address';
		matchType.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	} );
	expect( container.querySelector( 'input[aria-label="Address line 1"]' ) ).not.toBeNull();
	expect( container.querySelector( 'input[aria-label="Country"]' ) ).not.toBeNull();
} );

test( 'loads Audit history and Blocked attempts when an entry is managed', async () => {
	( apiFetch as jest.Mock ).mockImplementation( ( { path } ) => {
		if ( path === '/wp-buyer-blacklist/v1/email-entries' ) {
			return Promise.resolve( [ { id: '1', value: 'buyer@example.com', reason: 'Test entry', is_active: '1', created_at: '2026-01-01 00:00:00' } ] );
		}
		if ( path === '/wp-buyer-blacklist/v1/email-entries/1/blocked-attempts' ) {
			return Promise.resolve( [ { id: '1', identity_value: 'buyer@example.com', outcome: 'blocked', created_at: '2026-01-01 00:00:00' } ] );
		}
		return Promise.resolve( [ { action: 'created', actor_id: '1', actor_name: 'Admin', created_at: '2026-01-01 00:00:00' } ] );
	} );
	const container = document.createElement( 'div' );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	await act( async () => { Array.from( container.querySelectorAll( 'button' ) ).find( ( button ) => button.textContent === 'Manage' )?.click(); } );
	expect( container.textContent ).toContain( 'Audit history' );
	expect( container.textContent ).toContain( 'created by Admin' );
	expect( container.textContent ).toContain( 'Blocked attempts' );
	expect( container.textContent ).toContain( 'blocked buyer@example.com' );
	expect( container.querySelector( 'a[href="/wp-json/wp-buyer-blacklist/v1/email-entries/1/dossier"]' )?.textContent ).toBe( 'Export dossier' );
} );

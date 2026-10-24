import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from '../../assets/admin/index';

jest.mock( '@wordpress/api-fetch', () => jest.fn().mockResolvedValue( [] ) );
jest.mock( '@wordpress/a11y', () => ( { speak: jest.fn() } ) );

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

test( 'points operators to the Users screen for Customer ID entries', async () => {
	const container = document.createElement( 'div' );
	container.id = 'wp-buyer-blacklist-admin';
	container.dataset.usersUrl = 'http://example.org/wp-admin/users.php';
	document.body.append( container );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	const description = container.querySelector( '#wp-buyer-blacklist-match-type-description' );
	expect( description?.textContent ).toContain( 'To block by Customer ID, enroll the Customer from the Users screen.' );
	expect( description?.querySelector( 'a' )?.getAttribute( 'href' ) ).toBe( 'http://example.org/wp-admin/users.php' );
	expect( description?.querySelector( 'a' )?.textContent ).toBe( 'Users screen' );
	expect( Array.from( ( container.querySelector( '#wp-buyer-blacklist-match-type' ) as HTMLSelectElement ).options ).map( ( option ) => option.value ) ).toEqual( [ 'email', 'billing_address', 'shipping_address' ] );
	container.remove();
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

test( 'filters entries down to Customer ID entries', async () => {
	( apiFetch as jest.Mock ).mockResolvedValue( [
		{ id: '1', value: 'buyer@example.com', reason: 'Email entry', is_active: '1', match_type: 'email', created_at: '' },
		{ id: '2', value: '42', reason: 'Customer entry', is_active: '1', match_type: 'customer_id', created_at: '' },
	] );
	const container = document.createElement( 'div' );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	const type = container.querySelector( '#wp-buyer-blacklist-type-filter' ) as HTMLSelectElement;
	await act( async () => {
		type.value = 'customer_id';
		type.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	} );
	expect( type.value ).toBe( 'customer_id' );
	expect( container.textContent ).toContain( 'Customer entry' );
	expect( container.textContent ).not.toContain( 'Email entry' );
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
	container.dataset.canManagePrivacyData = '1';
	document.body.append( container );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	await act( async () => { Array.from( container.querySelectorAll( 'button' ) ).find( ( button ) => button.textContent === 'Manage' )?.click(); } );
	expect( container.textContent ).toContain( 'Audit history' );
	expect( container.textContent ).toContain( 'created by Admin' );
	expect( container.textContent ).toContain( 'Blocked attempts' );
	expect( container.textContent ).toContain( 'Billing address1 Test Street, Testville, CA, 90210, US' );
	expect( container.textContent ).toContain( 'Shipping address2 Test Street, Testville, CA, 90210, US' );
	const attemptItems = container.querySelectorAll( '.wpbb-blocked-attempts ol > li' );
	expect( attemptItems ).toHaveLength( 1 );
	expect( attemptItems[ 0 ].textContent ).toBe( '2026-01-01 00:00:00' );
	expect( container.querySelector( 'a[href="/wp-admin/admin-post.php?action=wp_buyer_blacklist_dossier_export&_wpnonce=nonce&entry_id=1"]' )?.textContent ).toBe( 'Export dossier' );
	expect( container.querySelector( 'dialog' ) ).not.toBeNull();
	expect( Array.from( container.querySelectorAll( 'button' ) ).find( ( button ) => button.textContent === 'Manage' )?.getAttribute( 'commandfor' ) ).toBe( 'wp-buyer-blacklist-entry-dialog' );
	const deleteButton = container.querySelector( '.wp-buyer-blacklist-delete-entry' );
	expect( deleteButton?.classList ).toContain( 'wp-buyer-blacklist-delete-entry' );
	expect( deleteButton?.closest( '.wpbb-entry-dialog-footer' ) ).not.toBeNull();
	expect( container.querySelector( '.wpbb-entry-dialog-close' )?.getAttribute( 'aria-label' ) ).toBe( 'Close dialog' );
	expect( container.querySelector( '.wpbb-entry-summary' ) ).toBeNull();
	const buyerCard = container.querySelector( '.wpbb-buyer-details-card' );
	expect( buyerCard?.querySelector( '.wpbb-buyer-entry' )?.textContent ).toBe( 'Emailbuyer@example.com' );
	expect( buyerCard?.textContent ).toContain( 'Ada Lovelace (#1)' );
	expect( container.querySelector( '.wpbb-buyer-details > h3 .wpbb-buyer-avatar .wpbb-modal-icon.is-user' ) ).not.toBeNull();
	expect( container.querySelector( 'a[href$="&entry_id=1"]' )?.classList ).toContain( 'button' );
	expect( container.querySelector( '.wpbb-modal-icon.is-user' ) ).not.toBeNull();
	expect( container.querySelector( '.wpbb-modal-icon.is-pin' ) ).not.toBeNull();
	expect( container.querySelector( '.wpbb-button-icon.is-trash' ) ).not.toBeNull();
	container.remove();
} );

test( 'hides Administrator-only entry actions from Blacklist operators', async () => {
	( apiFetch as jest.Mock ).mockImplementation( ( { path } ) => path === '/wp-buyer-blacklist/v1/email-entries' ? Promise.resolve( [ { id: '1', value: 'buyer@example.com', reason: 'Test entry', is_active: '1', created_at: '2026-01-01 00:00:00' } ] ) : Promise.resolve( [] ) );
	const container = document.createElement( 'div' );
	container.id = 'wp-buyer-blacklist-admin';
	container.dataset.dossierExportUrl = '/wp-admin/admin-post.php?action=wp_buyer_blacklist_dossier_export&_wpnonce=nonce';
	document.body.append( container );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	await act( async () => { Array.from( container.querySelectorAll( 'button' ) ).find( ( button ) => button.textContent === 'Manage' )?.click(); } );
	expect( container.textContent ).toContain( 'Audit history' );
	expect( container.querySelector( '.wp-buyer-blacklist-delete-entry' ) ).toBeNull();
	expect( container.textContent ).not.toContain( 'Export dossier' );
	container.remove();
} );

test( 'hides the Blacklist export from Blacklist operators', async () => {
	( apiFetch as jest.Mock ).mockImplementation( () => Promise.resolve( [] ) );
	const container = document.createElement( 'div' );
	container.id = 'wp-buyer-blacklist-admin';
	container.dataset.tab = 'import-export';
	container.dataset.exportUrl = '/export';
	document.body.append( container );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	expect( container.textContent ).toContain( 'Import Blacklist entries' );
	expect( container.textContent ).not.toContain( 'Export Blacklist entries' );
	container.remove();
} );

test( 'shows the entry identity without a Buyer and no primary tile in empty Blocked attempts', async () => {
	( apiFetch as jest.Mock ).mockImplementation( ( { path } ) => {
		if ( path === '/wp-buyer-blacklist/v1/email-entries' ) {
			return Promise.resolve( [ { id: '1', value: '{"address_line_1":"1 Test Street","address_line_2":"","city":"Testville","state":"CA","postcode":"90210","country":"US"}', reason: 'Test entry', is_active: '1', match_type: 'billing_address', created_at: '2026-01-01 00:00:00' } ] );
		}
		if ( path === '/wp-buyer-blacklist/v1/email-entries/1/buyer-context' ) {
			return Promise.resolve( { buyer: null, message: 'No uniquely identified registered Buyer.' } );
		}
		return Promise.resolve( [] );
	} );
	const container = document.createElement( 'div' );
	container.id = 'wp-buyer-blacklist-admin';
	document.body.append( container );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	await act( async () => { Array.from( container.querySelectorAll( 'button' ) ).find( ( button ) => button.textContent === 'Manage' )?.click(); } );
	const buyerCard = container.querySelector( '.wpbb-buyer-details-card' );
	expect( buyerCard?.querySelector( '.wpbb-buyer-entry' )?.textContent ).toContain( 'Billing address' );
	expect( buyerCard?.querySelector( '.wpbb-buyer-entry' )?.textContent ).toContain( '1 Test Street' );
	expect( buyerCard?.textContent ).toContain( 'No uniquely identified registered Buyer.' );
	expect( container.querySelector( '.wpbb-no-attempts' ) ).not.toBeNull();
	expect( container.querySelector( '.wpbb-blocked-attempts .wpbb-icon-tile.is-primary' ) ).toBeNull();
	container.remove();
} );

const twoEntries = [
	{ id: '1', value: 'first@example.com', reason: 'First', is_active: '1', match_type: 'email', created_at: '2026-01-01 00:00:00' },
	{ id: '2', value: 'second@example.com', reason: 'Second', is_active: '1', match_type: 'email', created_at: '2026-01-02 00:00:00' },
];
const audit = ( action: string ) => [ { action, actor_id: '1', actor_name: 'Admin', created_at: '2026-01-01 00:00:00' } ];
// Rows are sorted newest first, so find the row by its value instead of its position.
const manage = ( container: HTMLElement, value: string ) => ( Array.from( container.querySelectorAll( 'tbody tr' ) ).find( ( row ) => row.textContent?.includes( value ) )?.querySelector( 'button' ) as HTMLButtonElement ).click();

/**
 * Answers entry requests from a table of handlers, keyed by path. Unlisted paths return an empty list.
 */
const mockApi = ( handlers: Record< string, () => Promise< unknown > > ) => {
	( apiFetch as jest.Mock ).mockImplementation( ( { path }: { path: string } ) => ( handlers[ path ] ?? ( () => Promise.resolve( [] ) ) )() );
};

const renderApp = async () => {
	const container = document.createElement( 'div' );
	container.id = 'wp-buyer-blacklist-admin';
	document.body.append( container );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	return container;
};

describe( 'entry dialog details', () => {
	beforeEach( () => { ( speak as jest.Mock ).mockClear(); } );

	test( 'clears the previous entry details and shows a loading state while another entry loads', async () => {
		let release: ( value: unknown ) => void = () => {};
		mockApi( {
			'/wp-buyer-blacklist/v1/email-entries': () => Promise.resolve( twoEntries ),
			'/wp-buyer-blacklist/v1/email-entries/1': () => Promise.resolve( audit( 'first-entry-event' ) ),
			'/wp-buyer-blacklist/v1/email-entries/2': () => new Promise( ( resolve ) => { release = resolve; } ),
		} );
		const container = await renderApp();
		await act( async () => { manage( container, 'first@example.com' ); } );
		expect( container.textContent ).toContain( 'first-entry-event' );
		await act( async () => { manage( container, 'second@example.com' ); } );
		expect( container.textContent ).not.toContain( 'first-entry-event' );
		expect( container.querySelector( '.wpbb-entry-dialog-panel' )?.textContent ).toContain( 'Loading…' );
		await act( async () => { release( audit( 'second-entry-event' ) ); } );
		expect( container.textContent ).toContain( 'second-entry-event' );
		container.remove();
	} );

	test( 'ignores details that arrive after another entry was selected', async () => {
		let releaseFirst: ( value: unknown ) => void = () => {};
		mockApi( {
			'/wp-buyer-blacklist/v1/email-entries': () => Promise.resolve( twoEntries ),
			'/wp-buyer-blacklist/v1/email-entries/1': () => new Promise( ( resolve ) => { releaseFirst = resolve; } ),
			'/wp-buyer-blacklist/v1/email-entries/2': () => Promise.resolve( audit( 'second-entry-event' ) ),
		} );
		const container = await renderApp();
		await act( async () => { manage( container, 'first@example.com' ); } );
		await act( async () => { manage( container, 'second@example.com' ); } );
		await act( async () => { releaseFirst( audit( 'first-entry-event' ) ); } );
		expect( container.textContent ).toContain( 'second-entry-event' );
		expect( container.textContent ).not.toContain( 'first-entry-event' );
		container.remove();
	} );

	test( 'shows an error instead of stale data when the details fail to load', async () => {
		mockApi( {
			'/wp-buyer-blacklist/v1/email-entries': () => Promise.resolve( twoEntries ),
			'/wp-buyer-blacklist/v1/email-entries/1': () => Promise.resolve( audit( 'first-entry-event' ) ),
			'/wp-buyer-blacklist/v1/email-entries/2': () => Promise.reject( new Error( 'Details request failed.' ) ),
		} );
		const container = await renderApp();
		await act( async () => { manage( container, 'first@example.com' ); } );
		await act( async () => { manage( container, 'second@example.com' ); } );
		const dialog = container.querySelector( 'dialog' );
		expect( dialog?.textContent ).toContain( 'Details request failed.' );
		expect( dialog?.textContent ).toContain( 'This information could not be loaded.' );
		expect( dialog?.textContent ).not.toContain( 'first-entry-event' );
		expect( speak ).toHaveBeenCalledWith( 'Details request failed.', 'assertive' );
		container.remove();
	} );

	test( 'shows a message when the entries fail to load', async () => {
		mockApi( { '/wp-buyer-blacklist/v1/email-entries': () => Promise.reject( new Error( 'Entries request failed.' ) ) } );
		const container = await renderApp();
		expect( container.querySelector( '.wpbb-notice' )?.textContent ).toBe( 'Entries request failed.' );
		expect( speak ).toHaveBeenCalledWith( 'Entries request failed.', 'assertive' );
		container.remove();
	} );
} );

describe( 'status messages', () => {
	beforeEach( () => { ( speak as jest.Mock ).mockClear(); } );

	test( 'announces each result and shows dialog results inside the dialog', async () => {
		mockApi( {
			'/wp-buyer-blacklist/v1/email-entries': () => Promise.resolve( twoEntries ),
			'/wp-buyer-blacklist/v1/email-entries/1': () => Promise.resolve( audit( 'first-entry-event' ) ),
		} );
		const container = await renderApp();
		await act( async () => { manage( container, 'first@example.com' ); } );
		const save = Array.from( container.querySelectorAll( 'button' ) ).find( ( button ) => button.textContent === 'Save changes' ) as HTMLButtonElement;
		await act( async () => { save.click(); } );
		const dialog = container.querySelector( 'dialog' );
		expect( dialog?.querySelector( '.wpbb-entry-dialog-notice' )?.textContent ).toBe( 'Blacklist entry updated.' );
		expect( container.querySelector( 'main > .wpbb-notice' ) ).toBeNull();
		expect( speak ).toHaveBeenCalledWith( 'Blacklist entry updated.', 'polite' );
		await act( async () => { save.click(); } );
		expect( speak ).toHaveBeenCalledTimes( 2 );
		container.remove();
	} );

	test( 'announces dialog errors assertively and keeps them in the dialog', async () => {
		( apiFetch as jest.Mock ).mockImplementation( ( { path, method }: { path: string; method?: string } ) => {
			if ( 'PATCH' === method ) return Promise.reject( new Error( 'Update failed.' ) );
			return Promise.resolve( '/wp-buyer-blacklist/v1/email-entries' === path ? twoEntries : [] );
		} );
		const container = await renderApp();
		await act( async () => { manage( container, 'first@example.com' ); } );
		const save = Array.from( container.querySelectorAll( 'button' ) ).find( ( button ) => button.textContent === 'Save changes' ) as HTMLButtonElement;
		await act( async () => { save.click(); } );
		expect( container.querySelector( 'dialog .wpbb-entry-dialog-notice' )?.textContent ).toBe( 'Update failed.' );
		expect( speak ).toHaveBeenCalledWith( 'Update failed.', 'assertive' );
		container.remove();
	} );
} );

test( 'marks the reason of a new entry as required', async () => {
	const container = await renderApp();
	const reason = container.querySelector( '#wp-buyer-blacklist-reason' ) as HTMLTextAreaElement;
	expect( reason.required ).toBe( true );
	expect( container.querySelector( 'label[for="wp-buyer-blacklist-reason"]' )?.textContent ).toBe( 'Reason' );
	container.remove();
} );

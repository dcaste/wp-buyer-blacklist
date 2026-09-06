import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from '../../src/admin/index';

jest.mock( '@wordpress/api-fetch', () => jest.fn().mockResolvedValue( [] ) );

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

test( 'renders the email enrollment form', async () => {
	const container = document.createElement( 'div' );
	await act( async () => { createRoot( container ).render( <App /> ); } );
	expect( container.textContent ).toContain( 'Add entry' );
	expect( container.querySelector( 'input[type="email"]' ) ).not.toBeNull();
} );

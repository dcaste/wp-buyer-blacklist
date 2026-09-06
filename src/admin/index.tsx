import apiFetch from '@wordpress/api-fetch';
import { createRoot, useEffect, useState } from '@wordpress/element';

type Entry = { id: string; value: string; reason: string; is_active: string; created_at: string };

export function App() {
	const [ entries, setEntries ] = useState< Entry[] >( [] );
	const [ email, setEmail ] = useState( '' );
	const [ reason, setReason ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const load = () => apiFetch< Entry[] >( { path: '/wp-buyer-blacklist/v1/email-entries' } ).then( setEntries );
	useEffect( () => { load(); }, [] );
	const submit = async ( event: React.FormEvent ) => {
		event.preventDefault();
		try { await apiFetch( { path: '/wp-buyer-blacklist/v1/email-entries', method: 'POST', data: { email, reason } } ); setEmail( '' ); setReason( '' ); setMessage( 'Email entry created.' ); load(); } catch ( error: any ) { setMessage( error.message ); }
	};
	return <><h1>Buyer Blacklist</h1><form onSubmit={ submit }><p><label>Email <input aria-label="Email" type="email" required value={ email } onChange={ ( event ) => setEmail( event.target.value ) } /></label></p><p><label>Reason <textarea aria-label="Reason" required value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /></label></p><button type="submit">Add entry</button></form>{ message && <p role="status">{ message }</p> }<table><thead><tr><th>Email</th><th>Reason</th><th>Status</th></tr></thead><tbody>{ entries.map( ( entry ) => <tr key={ entry.id }><td>{ entry.value }</td><td>{ entry.reason }</td><td>{ entry.is_active === '1' ? 'Active' : 'Inactive' }</td></tr> ) }</tbody></table></>;
}

const root = document.getElementById( 'wp-buyer-blacklist-admin' );
if ( root ) { createRoot( root ).render( <App /> ); }

import apiFetch from '@wordpress/api-fetch';
import { createRoot, useEffect, useState } from '@wordpress/element';

type Entry = { id: string; value: string; reason: string; is_active: string; created_at: string };
type AuditEvent = { action: string; actor_id: string | null; actor_name: string | null; created_at: string };

export function App() {
	const [ entries, setEntries ] = useState< Entry[] >( [] );
	const [ events, setEvents ] = useState< AuditEvent[] >( [] );
	const [ selectedEntry, setSelectedEntry ] = useState< Entry | null >( null );
	const [ email, setEmail ] = useState( '' );
	const [ reason, setReason ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const load = () => apiFetch< Entry[] >( { path: '/wp-buyer-blacklist/v1/email-entries' } ).then( setEntries );
	const inspect = async ( entry: Entry ) => {
		setSelectedEntry( entry );
		setReason( entry.reason );
		setEvents( await apiFetch< AuditEvent[] >( { path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }` } ) );
	};
	useEffect( () => { load(); }, [] );
	const submit = async ( event: React.FormEvent ) => {
		event.preventDefault();
		try {
			await apiFetch( { path: '/wp-buyer-blacklist/v1/email-entries', method: 'POST', data: { email, reason } } );
			setEmail( '' );
			setReason( '' );
			setMessage( 'Email entry created.' );
			load();
		} catch ( error: any ) {
			setMessage( error.message );
		}
	};
	const update = async ( data: Record< string, string | boolean > ) => {
		if ( ! selectedEntry ) return;
		try {
			await apiFetch( { path: `/wp-buyer-blacklist/v1/email-entries/${ selectedEntry.id }`, method: 'PATCH', data } );
			setMessage( 'Blacklist entry updated.' );
			const refreshedEntries = await apiFetch< Entry[] >( { path: '/wp-buyer-blacklist/v1/email-entries' } );
			setEntries( refreshedEntries );
			const refreshedEntry = refreshedEntries.find( ( entry ) => entry.id === selectedEntry.id );
			if ( refreshedEntry ) await inspect( refreshedEntry );
		} catch ( error: any ) {
			setMessage( error.message );
		}
	};
	const remove = async () => {
		if ( ! selectedEntry || ! window.confirm( `Delete ${ selectedEntry.value }? This cannot be undone.` ) ) return;
		try {
			await apiFetch( { path: `/wp-buyer-blacklist/v1/email-entries/${ selectedEntry.id }`, method: 'DELETE', data: { confirm: true } } );
			setSelectedEntry( null );
			setEvents( [] );
			setMessage( 'Blacklist entry deleted.' );
			load();
		} catch ( error: any ) {
			setMessage( error.message );
		}
	};
	return <><h1>Buyer Blacklist</h1><form onSubmit={ submit }><p><label>Email <input aria-label="Email" type="email" required value={ email } onChange={ ( event ) => setEmail( event.target.value ) } /></label></p><p><label>Reason <textarea aria-label="Reason" required value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /></label></p><button type="submit">Add entry</button></form>{ message && <p role="status">{ message }</p> }<table><thead><tr><th>Email</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead><tbody>{ entries.map( ( entry ) => <tr key={ entry.id }><td>{ entry.value }</td><td>{ entry.reason }</td><td>{ entry.is_active === '1' ? 'Active' : 'Inactive' }</td><td><button onClick={ () => inspect( entry ) }>Manage</button></td></tr> ) }</tbody></table>{ selectedEntry && <section><h2>Manage { selectedEntry.value }</h2><p><label>Reason <textarea aria-label="Edit reason" required value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /></label></p><button onClick={ () => update( { reason } ) }>Save reason</button><button onClick={ () => update( { is_active: selectedEntry.is_active !== '1' } ) }>{ selectedEntry.is_active === '1' ? 'Deactivate' : 'Activate' }</button><button onClick={ remove }>Delete entry</button><h3>Audit history</h3><ul>{ events.map( ( event ) => <li key={ `${ event.created_at }-${ event.action }` }>{ event.action } by { event.actor_name ?? 'System' } at { event.created_at }</li> ) }</ul></section> }</>;
}

const root = document.getElementById( 'wp-buyer-blacklist-admin' );
if ( root ) { createRoot( root ).render( <App /> ); }

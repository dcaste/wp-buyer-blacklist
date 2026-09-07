import apiFetch from '@wordpress/api-fetch';
import { createRoot, useEffect, useState } from '@wordpress/element';

type Entry = { id: string; value: string; reason: string; is_active: string; created_at: string };
type AuditEvent = { action: string; actor_id: string | null; actor_name: string | null; created_at: string };
type BlockedAttempt = { id: string; identity_value: string; outcome: string; created_at: string };
type Address = { address_line_1: string; address_line_2: string; city: string; state: string; postcode: string; country: string };
type ImportResult = { token: string; valid: number; created: number | null; duplicates: { row: number; reason: string }[]; rejected: { row: number; reason: string }[] };

const emptyAddress: Address = { address_line_1: '', address_line_2: '', city: '', state: '', postcode: '', country: '' };

export function App() {
	const [ entries, setEntries ] = useState< Entry[] >( [] );
	const [ events, setEvents ] = useState< AuditEvent[] >( [] );
	const [ attempts, setAttempts ] = useState< BlockedAttempt[] >( [] );
	const [ selectedEntry, setSelectedEntry ] = useState< Entry | null >( null );
	const [ matchType, setMatchType ] = useState( 'email' );
	const [ email, setEmail ] = useState( '' );
	const [ address, setAddress ] = useState< Address >( emptyAddress );
	const [ reason, setReason ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ importResult, setImportResult ] = useState< ImportResult | null >( null );
	const load = () => apiFetch< Entry[] >( { path: '/wp-buyer-blacklist/v1/email-entries' } ).then( setEntries );
	const inspect = async ( entry: Entry ) => {
		setSelectedEntry( entry );
		setReason( entry.reason );
		const [ auditEvents, blockedAttempts ] = await Promise.all( [
			apiFetch< AuditEvent[] >( { path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }` } ),
			apiFetch< BlockedAttempt[] >( { path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }/blocked-attempts` } ),
		] );
		setEvents( auditEvents );
		setAttempts( blockedAttempts );
	};
	useEffect( () => { load(); }, [] );
	const submit = async ( event: React.FormEvent ) => {
		event.preventDefault();
		try {
			const isEmail = matchType === 'email';
			await apiFetch( {
				path: isEmail ? '/wp-buyer-blacklist/v1/email-entries' : '/wp-buyer-blacklist/v1/address-entries',
				method: 'POST',
				data: isEmail ? { email, reason } : { match_type: matchType, ...address, reason },
			} );
			setEmail( '' );
			setAddress( emptyAddress );
			setReason( '' );
			setMessage( isEmail ? 'Email entry created.' : 'Address entry created.' );
			load();
		} catch ( error: any ) {
			setMessage( error.message );
		}
	};
	const validateImport = async ( event: React.ChangeEvent< HTMLInputElement > ) => {
		const file = event.target.files?.[ 0 ];
		if ( ! file ) return;
		try {
			const result = await apiFetch< ImportResult >( { path: '/wp-buyer-blacklist/v1/csv-import/validate', method: 'POST', data: { csv: await file.text() } } );
			setImportResult( result );
		} catch ( error: any ) {
			setMessage( error.message );
		}
	};
	const confirmImport = async () => {
		if ( ! importResult ) return;
		try {
			const result = await apiFetch< ImportResult >( { path: '/wp-buyer-blacklist/v1/csv-import/confirm', method: 'POST', data: { token: importResult.token } } );
			setImportResult( result );
			setMessage( 'CSV import completed.' );
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
		if ( ! selectedEntry || ! window.confirm( `Delete ${ selectedEntry.value}? This cannot be undone.` ) ) return;
		try {
			await apiFetch( { path: `/wp-buyer-blacklist/v1/email-entries/${ selectedEntry.id }`, method: 'DELETE', data: { confirm: true } } );
			setSelectedEntry( null );
			setEvents( [] );
			setAttempts( [] );
			setMessage( 'Blacklist entry deleted.' );
			load();
		} catch ( error: any ) {
			setMessage( error.message );
		}
	};
	const addressField = ( field: keyof Address, label: string, required = true ) => <p><label>{ label } <input aria-label={ label } required={ required } value={ address[ field ] } onChange={ ( event ) => setAddress( { ...address, [ field ]: event.target.value } ) } /></label></p>;
	const isAddress = matchType !== 'email';
	return <><h1>Buyer Blacklist</h1><form onSubmit={ submit }><p><label>Match type <select aria-label="Match type" value={ matchType } onChange={ ( event ) => setMatchType( event.target.value ) }><option value="email">Email</option><option value="billing_address">Billing address</option><option value="shipping_address">Shipping address</option></select></label></p>{ ! isAddress && <p><label>Email <input aria-label="Email" type="email" required value={ email } onChange={ ( event ) => setEmail( event.target.value ) } /></label></p> }{ isAddress && <>{ addressField( 'address_line_1', 'Address line 1' ) }{ addressField( 'address_line_2', 'Address line 2', false ) }{ addressField( 'city', 'City' ) }{ addressField( 'state', 'State' ) }{ addressField( 'postcode', 'Postcode' ) }{ addressField( 'country', 'Country' ) }</> }<p><label>Reason <textarea aria-label="Reason" required value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /></label></p><button type="submit">Add entry</button></form><section><h2>Import CSV</h2><p><label>CSV file <input aria-label="CSV file" type="file" accept=".csv,text/csv" onChange={ validateImport } /></label></p>{ importResult && <><p>{ importResult.valid } valid, { importResult.duplicates.length } duplicate, { importResult.rejected.length } rejected.</p>{ importResult.token && <button onClick={ confirmImport }>Confirm import</button> }<ul>{ [ ...importResult.duplicates, ...importResult.rejected ].map( ( row ) => <li key={ `${ row.row }-${ row.reason }` }>Row { row.row }: { row.reason }</li> ) }</ul>{ importResult.created !== null && <p>Created { importResult.created } entries.</p> }</> }</section>{ message && <p role="status">{ message }</p> }<table><thead><tr><th>Email</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead><tbody>{ entries.map( ( entry ) => <tr key={ entry.id }><td>{ entry.value }</td><td>{ entry.reason }</td><td>{ entry.is_active === '1' ? 'Active' : 'Inactive' }</td><td><button onClick={ () => inspect( entry ) }>Manage</button></td></tr> ) }</tbody></table>{ selectedEntry && <section><h2>Manage { selectedEntry.value }</h2><p><label>Reason <textarea aria-label="Edit reason" required value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /></label></p><button onClick={ () => update( { reason } ) }>Save reason</button><button onClick={ () => update( { is_active: selectedEntry.is_active !== '1' } ) }>{ selectedEntry.is_active === '1' ? 'Deactivate' : 'Activate' }</button><button onClick={ remove }>Delete entry</button><a href={ `/wp-json/wp-buyer-blacklist/v1/email-entries/${ selectedEntry.id }/dossier` }>Export dossier</a><h3>Audit history</h3><ul>{ events.map( ( event ) => <li key={ `${ event.created_at }-${ event.action }` }>{ event.action } by { event.actor_name ?? 'System' } at { event.created_at }</li> ) }</ul><h3>Blocked attempts</h3><ul>{ attempts.map( ( attempt ) => <li key={ attempt.id }>{ attempt.outcome } { attempt.identity_value } at { attempt.created_at }</li> ) }</ul></section> }</>;
}

const root = document.getElementById( 'wp-buyer-blacklist-admin' );
if ( root ) { createRoot( root ).render( <App /> ); }
